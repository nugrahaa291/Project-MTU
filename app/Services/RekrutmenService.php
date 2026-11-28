<?php

namespace App\Services;

use App\Enums\StatusLamaran;
use App\Exceptions\RekrutmenException;
use App\Models\Karyawan;
use App\Models\Lamaran;
use App\Models\Lowongan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RekrutmenService
{
    public function __construct(private KaryawanService $karyawanService) {}

    public function periksa(Lamaran $lamaran, array $verifikasi, ?string $alasan, int $petugas, bool $lanjut): void
    {
        DB::transaction(function () use ($lamaran, $verifikasi, $alasan, $petugas, $lanjut) {
            $lamaran = $this->kunci($lamaran);
            if ($lamaran->lolos_berkas_pada || ! $lamaran->status->bisaPindahKe(StatusLamaran::Ditolak)) {
                throw new RekrutmenException('Pemeriksaan berkas untuk lamaran ini sudah selesai.');
            }

            foreach ($verifikasi as $kolom => $nilai) {
                if ($nilai === 'valid' && (blank($lamaran->{$kolom}) || ! Storage::disk('local')->exists($lamaran->{$kolom}))) {
                    throw new RekrutmenException('Berkas '.Lamaran::DOKUMEN[$kolom].' belum tersedia untuk diverifikasi.');
                }
            }

            if ($lanjut) {
                foreach ($lamaran->lowongan->daftarDokumen() as $dokumen) {
                    $pilihan = isset($dokumen['pilihan']) ? array_keys($dokumen['pilihan']) : [$dokumen['kunci']];
                    if ($dokumen['wajib'] && ! in_array('valid', array_intersect_key($verifikasi, array_flip($pilihan)), true)) {
                        throw new RekrutmenException('Periksa berkas wajib sebelum lanjut ke interview: '.$dokumen['label'].'.');
                    }
                }
                $lamaran->status = StatusLamaran::Seleksi;
                $lamaran->lolos_berkas_pada = now();
            }

            $lamaran->update(['verifikasi_berkas' => $verifikasi, 'alasan_seleksi' => $alasan, 'diperiksa_oleh' => $petugas]);
        });
    }

    public function catatInterview(Lamaran $lamaran, array $data, int $petugas): void
    {
        DB::transaction(function () use ($lamaran, $data, $petugas) {
            $lamaran = $this->kunci($lamaran);
            if ($lamaran->status !== StatusLamaran::Seleksi || ! $lamaran->lolos_berkas_pada || $lamaran->dinilai_pada) {
                throw new RekrutmenException('Hasil interview hanya bisa dicatat untuk pelamar yang sudah lolos berkas dan menunggu interview.');
            }
            $lamaran->update([...$data, 'dinilai_oleh' => $petugas, 'dinilai_pada' => now()]);
        });
    }

    public function tolak(Lamaran $lamaran, string $catatan, int $petugas): void
    {
        DB::transaction(function () use ($lamaran, $catatan, $petugas) {
            $lamaran = $this->kunci($lamaran);
            $this->pindah($lamaran, StatusLamaran::Ditolak);
            if ($lamaran->lolos_berkas_pada && ! $lamaran->dinilai_pada) {
                throw new RekrutmenException('Isi hasil interview sebelum mengambil keputusan akhir.');
            }
            $lamaran->update([
                'status' => StatusLamaran::Ditolak,
                'catatan' => $catatan,
                'diputuskan_oleh' => $petugas,
                'diputuskan_pada' => now(),
            ]);
        });
    }

    /**
     * seleksi -> diterima (Hired), sekaligus membuat akun karyawan.
     *
     * Aman dari "kuota jebol": baris lowongan dikunci selama proses berjalan,
     * jadi dua HRD yang menekan tombol Terima bersamaan akan dilayani bergantian.
     *
     * @return array{0: Karyawan, 1: string} [karyawan, passwordSementara]
     */
    public function terima(Lamaran $lamaran, string $email, string $tanggalMasuk, string $catatan, int $petugas): array
    {
        return DB::transaction(function () use ($lamaran, $email, $tanggalMasuk, $catatan, $petugas) {
            // Ambil ulang dari database dengan kunci, supaya status yang dicek adalah yang terbaru
            $lamaran = $this->kunci($lamaran);
            $this->pindah($lamaran, StatusLamaran::Diterima);
            if (! $lamaran->lolos_berkas_pada || ! $lamaran->dinilai_pada || $lamaran->rekomendasi_interview !== 'lanjut') {
                throw new RekrutmenException('Penerimaan memerlukan verifikasi berkas dan hasil interview dengan rekomendasi lanjut.');
            }

            $lowongan = Lowongan::whereKey($lamaran->lowongan_id)->lockForUpdate()->firstOrFail();
            if ($lowongan->jumlahDiterima() >= $lowongan->kuota) {
                throw new RekrutmenException('Kuota lowongan "'.$lowongan->judul.'" sudah penuh ('.$lowongan->kuota.' orang).');
            }

            [$karyawan, $password] = $this->karyawanService->buatDariLamaran($lamaran, $email, $tanggalMasuk);

            $lamaran->update([
                'status' => StatusLamaran::Diterima,
                'diputuskan_oleh' => $petugas,
                'catatan' => $catatan,
                'diputuskan_pada' => now(),
            ]);

            return [$karyawan, $password];
        });
    }

    private function kunci(Lamaran $lamaran): Lamaran
    {
        return Lamaran::whereKey($lamaran->id)->lockForUpdate()->firstOrFail();
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
