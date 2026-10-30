<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusLamaran;
use App\Exceptions\RekrutmenException;
use App\Http\Controllers\Controller;
use App\Models\Lamaran;
use App\Models\Lowongan;
use App\Services\RekrutmenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LamaranController extends Controller
{
    public function __construct(private RekrutmenService $rekrutmen)
    {
    }

    public function index(Request $request): View
    {
        $lamaran = Lamaran::with('lowongan')
            ->when($request->filled('lowongan_id'), fn ($q) => $q->where('lowongan_id', $request->lowongan_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $kata)->orWhere('nik', 'like', $kata)->orWhere('kode', 'like', $kata));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('hrd.lamaran.index', [
            'lamaran' => $lamaran,
            'daftarLowongan' => Lowongan::orderByDesc('id')->get(['id', 'judul']),
        ]);
    }

    public function show(Lamaran $lamaran): View
    {
        $lamaran->load(['lowongan.jabatan', 'karyawan']);
        $lamaran->lowongan->loadCount([
            'lamaran as diterima_count' => fn ($q) => $q->where('status', StatusLamaran::Diterima->value),
        ]);

        return view('hrd.lamaran.show', compact('lamaran'));
    }

    /** Mengunduh/menampilkan berkas pelamar dari disk privat - hanya untuk HRD yang login. */
    public function dokumen(Lamaran $lamaran, string $jenis): StreamedResponse
    {
        abort_unless(array_key_exists($jenis, Lamaran::DOKUMEN), 404);

        $path = $lamaran->{$jenis};
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function proses(Lamaran $lamaran): RedirectResponse
    {
        return $this->jalankan(function () use ($lamaran) {
            $this->rekrutmen->proses($lamaran);

            return back()->with('success', 'Lamaran dipindah ke tahap seleksi.');
        });
    }

    public function tolak(Request $request, Lamaran $lamaran): RedirectResponse
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);

        return $this->jalankan(function () use ($lamaran, $data) {
            $this->rekrutmen->tolak($lamaran, $data['catatan'] ?? null);

            return back()->with('success', 'Lamaran ditolak.');
        });
    }

    public function terima(Request $request, Lamaran $lamaran): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'tanggal_masuk' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'email.unique' => 'Email ini sudah dipakai akun lain. Gunakan email yang berbeda.',
        ]);

        return $this->jalankan(function () use ($lamaran, $data) {
            [$karyawan, $password] = $this->rekrutmen->terima(
                $lamaran, $data['email'], $data['tanggal_masuk'], $data['catatan'] ?? null,
            );

            // flash() = hanya tersedia untuk SATU permintaan berikutnya, lalu hilang.
            return redirect()->route('hrd.karyawan.show', $karyawan)
                ->with('success', 'Pelamar diterima dan akun karyawan berhasil dibuat.')
                ->with('password_sementara', $password);
        });
    }

    /** Menangkap kesalahan aturan bisnis lalu menampilkannya sebagai pesan, bukan halaman error. */
    private function jalankan(callable $aksi): RedirectResponse
    {
        try {
            return $aksi();
        } catch (RekrutmenException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
