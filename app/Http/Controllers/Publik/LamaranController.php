<?php

namespace App\Http\Controllers\Publik;

use App\Enums\StatusLamaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLamaranRequest;
use App\Models\Lamaran;
use App\Models\Lowongan;
use App\Models\Pengaturan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class LamaranController extends Controller
{
    public function create(Lowongan $lowongan): View|RedirectResponse
    {
        if ($sudah = auth('pelamar')->user()->lamaran()->where('lowongan_id', $lowongan->id)->first()) {
            return redirect()->route('lamaran.berhasil', $sudah);
        }
        if (! $lowongan->bisaDilamar()) {
            return redirect()->route('lowongan.show', $lowongan)
                ->with('error', 'Lowongan ini sedang tidak menerima lamaran.');
        }

        $lowongan->load('jabatan');
        $minimalUsia = (int) Pengaturan::ambil('rekrutmen_minimal_usia', '17');

        return view('publik.lamaran.create', compact('lowongan', 'minimalUsia'));
    }

    public function store(StoreLamaranRequest $request, Lowongan $lowongan): RedirectResponse|JsonResponse
    {
        if (! $lowongan->bisaDilamar()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Lowongan ini sedang tidak menerima lamaran.'], 409);
            }

            return redirect()->route('lowongan.show', $lowongan)
                ->with('error', 'Lowongan ini sedang tidak menerima lamaran.');
        }

        $kode = $this->buatKode();
        $folder = "lamaran/{$kode}"; // disk 'local' = privat (tidak bisa diakses lewat URL)
        $path = [];

        // Kumpulkan berkas yang diunggah: [kolom di database => file]
        $berkas = [];
        foreach (array_keys($lowongan->paketDokumen()) as $kunci) {
            if ($kunci === 'pendukung') {
                // Pelamar memilih salah satu (ijazah / akte lahir / buku nikah)
                if ($request->hasFile('dokumen_pendukung')) {
                    $berkas[$request->validated('jenis_pendukung')] = $request->file('dokumen_pendukung');
                }
            } elseif ($request->hasFile($kunci)) {
                $berkas[$kunci] = $request->file($kunci);
            }
        }

        try {
            foreach ($berkas as $kolom => $file) {
                // Ekstensi diambil dari isi file (bukan nama dari pengguna) supaya aman
                $path[$kolom] = $file->storeAs($folder, $kolom.'.'.$file->extension(), 'local');
            }

            $lamaran = Lamaran::create([
                ...$request->safe()->only(Lamaran::DATA_DIRI),
                ...$path,
                'no_hp' => Lamaran::normalisasiHp($request->validated('no_hp')),
                'pelamar_id' => $request->user('pelamar')->id,
                'kode' => $kode,
                'lowongan_id' => $lowongan->id,
                'status' => StatusLamaran::Diajukan,
            ]);
        } catch (Throwable $e) {
            // Jangan tinggalkan berkas yatim bila penyimpanan database gagal
            Storage::disk('local')->deleteDirectory($folder);
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['nik' => 'Lamaran untuk lowongan ini sudah terdaftar.']);
            }
            throw $e;
        }

        $tujuan = route('lamaran.berhasil', $lamaran);

        return $request->expectsJson()
            ? response()->json(['redirect' => $tujuan])
            : redirect($tujuan);
    }

    public function berhasil(Lamaran $lamaran): View
    {
        abort_unless($lamaran->pelamar_id == auth('pelamar')->id(), 404);
        $lamaran->load('lowongan');

        return view('publik.lamaran.berhasil', compact('lamaran'));
    }

    private function buatKode(): string
    {
        do {
            $kode = 'LMR-'.strtoupper(Str::random(8));
        } while (Lamaran::where('kode', $kode)->exists());

        return $kode;
    }
}
