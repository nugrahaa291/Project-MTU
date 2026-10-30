<?php

namespace App\Http\Controllers\Publik;

use App\Enums\StatusLamaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLamaranRequest;
use App\Models\Lamaran;
use App\Models\Lowongan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LamaranController extends Controller
{
    public function create(Lowongan $lowongan): View|RedirectResponse
    {
        if (! $lowongan->bisaDilamar()) {
            return redirect()->route('lowongan.show', $lowongan)
                ->with('error', 'Lowongan ini sedang tidak menerima lamaran.');
        }

        $lowongan->load('jabatan');

        return view('publik.lamaran.create', compact('lowongan'));
    }

    public function store(StoreLamaranRequest $request, Lowongan $lowongan): RedirectResponse
    {
        if (! $lowongan->bisaDilamar()) {
            return redirect()->route('lowongan.show', $lowongan)
                ->with('error', 'Lowongan ini sedang tidak menerima lamaran.');
        }

        $kode = $this->buatKode();
        $folder = "lamaran/{$kode}"; // disk 'local' = privat (tidak bisa diakses lewat URL)
        $path = [];

        try {
            foreach (array_keys(Lamaran::DOKUMEN) as $jenis) {
                if ($request->hasFile($jenis)) {
                    $berkas = $request->file($jenis);
                    // Ekstensi diambil dari isi file (bukan nama dari pengguna) supaya aman
                    $path[$jenis] = $berkas->storeAs($folder, $jenis.'.'.$berkas->extension(), 'local');
                }
            }

            $lamaran = Lamaran::create([
                ...$request->safe()->except(array_keys(Lamaran::DOKUMEN)),
                ...$path,
                'no_hp' => Lamaran::normalisasiHp($request->validated('no_hp')),
                'kode' => $kode,
                'lowongan_id' => $lowongan->id,
                'status' => StatusLamaran::Diajukan,
            ]);
        } catch (Throwable $e) {
            // Jangan tinggalkan berkas yatim bila penyimpanan database gagal
            Storage::disk('local')->deleteDirectory($folder);
            throw $e;
        }

        return redirect()->route('lamaran.berhasil', $lamaran);
    }

    public function berhasil(Lamaran $lamaran): View
    {
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
