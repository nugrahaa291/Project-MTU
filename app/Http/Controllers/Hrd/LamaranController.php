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
    public function __construct(private RekrutmenService $rekrutmen) {}

    public function index(Request $request): View
    {
        $lamaran = Lamaran::with('lowongan')
            ->where(function ($q) {
                $q->where('status', '!=', StatusLamaran::Diterima->value)
                    ->orWhere(function ($sub) {
                        $sub->where('status', StatusLamaran::Diterima->value)
                            ->where(function ($tgl) {
                                $batas = now()->subDays(3);
                                $tgl->where('diputuskan_pada', '>=', $batas)
                                    ->orWhere(function ($fallback) use ($batas) {
                                        $fallback->whereNull('diputuskan_pada')
                                            ->where('updated_at', '>=', $batas);
                                    });
                            });
                    });
            })
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
        $lamaran->load(['lowongan.jabatan', 'karyawan', 'pemeriksa', 'penilai', 'pengambilKeputusan']);
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

    public function proses(Request $request, Lamaran $lamaran): RedirectResponse
    {
        $lanjut = $request->routeIs('*.lamaran.proses');
        $data = $request->validate([
            'verifikasi' => ['sometimes', 'array:'.implode(',', array_keys($lamaran->berkasTampil()))],
            'verifikasi.*' => ['required', Rule::in(['belum', 'perbaikan', 'valid'])],
            'alasan_seleksi' => [$lanjut ? 'required' : 'nullable', 'string', 'max:1000'],
        ], ['alasan_seleksi.required' => 'Isi alasan kelayakan berdasarkan kualifikasi pelamar.']);

        return $this->jalankan(function () use ($request, $lamaran, $data, $lanjut) {
            $this->rekrutmen->periksa($lamaran, $data['verifikasi'] ?? [], $data['alasan_seleksi'] ?? null, $request->user()->id, $lanjut);

            return back()->with('success', $lanjut ? 'Pelamar lolos berkas dan masuk daftar interview.' : 'Pemeriksaan berkas disimpan.');
        });
    }

    public function interview(Request $request): View
    {
        $tahap = $request->query('tahap') === 'dinilai' ? 'dinilai' : 'menunggu';
        $lamaran = Lamaran::with('lowongan')
            ->when($tahap === 'dinilai',
                fn ($q) => $q->whereNotNull('dinilai_pada')->latest('dinilai_pada'),
                fn ($q) => $q->where('status', StatusLamaran::Seleksi)->whereNotNull('lolos_berkas_pada')
                    ->whereNull('dinilai_pada')->oldest('lolos_berkas_pada'))
            ->orderBy('id')->paginate(15)->withQueryString();

        return view('hrd.lamaran.interview', compact('lamaran', 'tahap'));
    }

    public function simpanInterview(Request $request, Lamaran $lamaran): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_interview' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$lamaran->created_at->toDateString(), 'before_or_equal:today'],
            'pewawancara' => ['required', 'string', 'max:150'],
            'hasil_interview' => ['required', 'string', 'max:2000'],
            'rekomendasi_interview' => ['required', Rule::in(['lanjut', 'tidak_lanjut'])],
        ]);

        return $this->jalankan(function () use ($request, $lamaran, $data) {
            $this->rekrutmen->catatInterview($lamaran, $data, $request->user()->id);

            return back()->with('success', 'Hasil interview disimpan. Keputusan akhir dapat diisi.');
        });
    }

    public function tolak(Request $request, Lamaran $lamaran): RedirectResponse
    {
        $request->mergeIfMissing(['alasan_penolakan' => $request->input('catatan')]);
        $data = $request->validate(['alasan_penolakan' => ['required', 'string', 'max:1000']], [
            'alasan_penolakan.required' => 'Isi alasan penolakan sebelum menyimpan keputusan.',
        ]);

        return $this->jalankan(function () use ($request, $lamaran, $data) {
            $this->rekrutmen->tolak($lamaran, $data['alasan_penolakan'], $request->user()->id);

            return back()->with('success', 'Lamaran ditolak.');
        });
    }

    public function terima(Request $request, Lamaran $lamaran): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'tanggal_masuk' => ['required', 'date'],
            'catatan' => ['required', 'string', 'max:1000'],
        ], [
            'catatan.required' => 'Isi alasan penerimaan sebelum membuat akun karyawan.',
            'email.unique' => 'Email ini sudah dipakai akun lain. Gunakan email yang berbeda.',
        ]);

        return $this->jalankan(function () use ($request, $lamaran, $data) {
            [$karyawan, $password] = $this->rekrutmen->terima(
                $lamaran, $data['email'], $data['tanggal_masuk'], $data['catatan'], $request->user()->id,
            );

            $rute = request()->routeIs('admin.*') ? 'admin.karyawan.show' : 'hrd.karyawan.show';

            // flash() = hanya tersedia untuk SATU permintaan berikutnya, lalu hilang.
            return redirect()->route($rute, $karyawan)
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
