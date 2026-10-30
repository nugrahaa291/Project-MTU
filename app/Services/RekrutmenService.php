<?php

namespace App\Services;

use App\Enums\StatusLamaran;
use App\Exceptions\RekrutmenException;
use App\Models\Karyawan;
use App\Models\Lamaran;
use App\Models\Lowongan;
use Illuminate\Support\Facades\DB;

class RekrutmenService
{
    public function __construct(private KaryawanService $karyawanService)
    {
    }

    /** diajukan -> seleksi */
    public function proses(Lamaran $lamaran): void
    {
        $this->pindah($lamaran, StatusLamaran::Seleksi);
        $lamaran->update(['status' => StatusLamaran::Seleksi]);
    }

    /** diajukan | seleksi -> ditolak */
    public function tolak(Lamaran $lamaran, ?string $catatan): void
    {
        $this->pindah($lamaran, StatusLamaran::Ditolak);
        $lamaran->update([
            'status' => StatusLamaran::Ditolak,
            'catatan' => $catatan,
            'diputuskan_pada' => now(),
        ]);
    }

    /**
     * seleksi -> diterima (Hired), sekaligus membuat akun karyawan.
     *
     * Aman dari "kuota jebol": baris lowongan dikunci selama proses berjalan,
     * jadi dua HRD yang menekan tombol Terima bersamaan akan dilayani bergantian.
     *
     * @return array{0: Karyawan, 1: string}  [karyawan, passwordSementara]
     */
    public function terima(Lamaran $lamaran, string $email, string $tanggalMasuk, ?string $catatan): array
    {
        return DB::transaction(function () use ($lamaran, $email, $tanggalMasuk, $catatan) {
            // Ambil ulang dari database dengan kunci, supaya status yang dicek adalah yang terbaru
            $lamaran = Lamaran::whereKey($lamaran->id)->lockForUpdate()->firstOrFail();
            $this->pindah($lamaran, StatusLamaran::Diterima);

            $lowongan = Lowongan::whereKey($lamaran->lowongan_id)->lockForUpdate()->firstOrFail();
            if ($lowongan->jumlahDiterima() >= $lowongan->kuota) {
                throw new RekrutmenException('Kuota lowongan "'.$lowongan->judul.'" sudah penuh ('.$lowongan->kuota.' orang).');
            }

            [$karyawan, $password] = $this->karyawanService->buatDariLamaran($lamaran, $email, $tanggalMasuk);

            $lamaran->update([
                'status' => StatusLamaran::Diterima,
                'catatan' => $catatan,
                'diputuskan_pada' => now(),
            ]);

            return [$karyawan, $password];
        });
    }

    private function pindah(Lamaran $lamaran, StatusLamaran $tujuan): void
    {
        if (! $lamaran->status->bisaPindahKe($tujuan)) {
            throw new RekrutmenException(
                'Lamaran berstatus "'.$lamaran->status->label().'" tidak bisa dipindah ke "'.$tujuan->label().'".'
            );
        }
    }
}
