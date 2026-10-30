<?php

namespace App\Services;

use App\Enums\StatusAbsensi;
use App\Exceptions\AbsensiException;
use App\Models\Absensi;
use App\Models\KalenderKerja;
use App\Models\Karyawan;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AbsensiService
{
    /** Waktu "sekarang" menurut zona waktu absensi (WIB), bukan zona waktu server. */
    public function sekarang(): CarbonImmutable
    {
        return CarbonImmutable::now(config('absensi.timezone'));
    }

    public function hariKerja(CarbonInterface $tanggal): bool
    {
        if (! in_array($tanggal->dayOfWeekIso, config('absensi.hari_kerja'), true)) {
            return false;
        }

        return ! KalenderKerja::whereDate('tanggal', $tanggal->toDateString())->exists();
    }

    public function masuk(Karyawan $karyawan, float $lat, float $lng, UploadedFile $foto, ?float $akurasi = null, bool $lokasiPalsu = false): Absensi
    {
        $sekarang = $this->sekarang();
        $tanggal = $sekarang->toDateString();

        if (! $karyawan->aktif || $karyawan->tanggal_masuk->toDateString() > $tanggal) {
            throw new AbsensiException('Akun karyawan belum/tidak aktif untuk absensi.', 403, 'karyawan_tidak_aktif');
        }
        if (! $this->hariKerja($sekarang)) {
            throw new AbsensiException('Hari ini bukan hari kerja.', 422, 'bukan_hari_kerja');
        }

        $jarak = $this->validasiLokasi($lat, $lng, $akurasi, $lokasiPalsu);

        $ada = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $tanggal)->first();
        if ($ada) {
            throw new AbsensiException(
                $ada->jam_masuk
                    ? 'Anda sudah absen masuk hari ini.'
                    : 'Hari ini sudah dicatat "'.$ada->status->label().'" oleh HRD.',
                409,
                'sudah_absen',
            );
        }

        // Terlambat bila melewati jam masuk + toleransi; menit keterlambatan dihitung dari jam masuk
        $batas = $sekarang->setTimeFromTimeString(config('absensi.jam_masuk'));
        $menit = max(0, intdiv($sekarang->getTimestamp() - $batas->getTimestamp(), 60));
        $terlambat = $menit > config('absensi.toleransi_menit');

        $path = $foto->storeAs(
            "absensi/{$karyawan->id}/{$tanggal}",
            'masuk-'.$sekarang->format('His').'.'.$foto->extension(),
            'local',
        );

        try {
            return Absensi::create([
                'karyawan_id' => $karyawan->id,
                'tanggal' => $tanggal,
                'status' => $terlambat ? StatusAbsensi::Terlambat : StatusAbsensi::Hadir,
                'menit_terlambat' => $terlambat ? $menit : 0,
                'jam_masuk' => $sekarang->format('H:i:s'),
                'lat_masuk' => $lat,
                'lng_masuk' => $lng,
                'jarak_masuk' => $jarak,
                'foto_masuk' => $path,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan masuk bersamaan: yang kedua ditolak oleh unique(karyawan_id, tanggal)
            Storage::disk('local')->delete($path);
            throw new AbsensiException('Anda sudah absen masuk hari ini.', 409, 'sudah_absen');
        }
    }

    public function pulang(Karyawan $karyawan, float $lat, float $lng, UploadedFile $foto, ?float $akurasi = null, bool $lokasiPalsu = false): Absensi
    {
        $sekarang = $this->sekarang();
        $tanggal = $sekarang->toDateString();

        if (! $karyawan->aktif) {
            throw new AbsensiException('Akun karyawan tidak aktif.', 403, 'karyawan_tidak_aktif');
        }

        $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $tanggal)->first();
        if (! $absensi || ! $absensi->jam_masuk) {
            throw new AbsensiException('Anda belum absen masuk hari ini.', 409, 'belum_masuk');
        }
        if ($absensi->jam_pulang) {
            throw new AbsensiException('Anda sudah absen pulang hari ini.', 409, 'sudah_pulang');
        }

        $jarak = $this->validasiLokasi($lat, $lng, $akurasi, $lokasiPalsu);

        $path = $foto->storeAs(
            "absensi/{$karyawan->id}/{$tanggal}",
            'pulang-'.$sekarang->format('His').'.'.$foto->extension(),
            'local',
        );

        $absensi->update([
            'jam_pulang' => $sekarang->format('H:i:s'),
            'lat_pulang' => $lat,
            'lng_pulang' => $lng,
            'jarak_pulang' => $jarak,
            'foto_pulang' => $path,
        ]);

        return $absensi;
    }

    /**
     * Menandai ALPA untuk karyawan aktif yang tidak punya catatan apa pun pada hari kerja.
     * Dijalankan terjadwal setiap malam (lihat routes/console.php).
     *
     * @return int jumlah karyawan yang ditandai alpa
     */
    public function tandaiAlpa(CarbonInterface $tanggal): int
    {
        if (! $this->hariKerja($tanggal)) {
            return 0;
        }

        $jumlah = 0;

        Karyawan::aktif()
            ->whereDate('tanggal_masuk', '<=', $tanggal->toDateString())
            ->whereDoesntHave('absensi', fn ($q) => $q->whereDate('tanggal', $tanggal->toDateString()))
            ->each(function (Karyawan $k) use ($tanggal, &$jumlah) {
                try {
                    Absensi::create([
                        'karyawan_id' => $k->id,
                        'tanggal' => $tanggal->toDateString(),
                        'status' => StatusAbsensi::Alpa,
                        'keterangan' => 'Tidak ada absensi (ditandai otomatis oleh sistem).',
                    ]);
                    $jumlah++;
                } catch (UniqueConstraintViolationException) {
                    // Baru saja diisi pihak lain; abaikan
                }
            });

        return $jumlah;
    }

    /** @return int jarak ke kantor (meter) bila lokasi sah */
    private function validasiLokasi(float $lat, float $lng, ?float $akurasi, bool $lokasiPalsu): int
    {
        if ($lokasiPalsu) {
            throw new AbsensiException('Terdeteksi lokasi palsu (mock location). Matikan aplikasi lokasi palsu.', 422, 'lokasi_palsu');
        }

        if ($akurasi !== null && $akurasi > config('absensi.maks_akurasi_meter')) {
            throw new AbsensiException(
                'Akurasi GPS terlalu rendah ('.round($akurasi).' m). Pindah ke area terbuka lalu coba lagi.',
                422,
                'akurasi_rendah',
            );
        }

        $kantor = config('absensi.kantor');
        $jarak = Geo::jarakMeter($lat, $lng, $kantor['latitude'], $kantor['longitude']);

        if ($jarak > $kantor['radius_meter']) {
            throw new AbsensiException(
                "Anda berada {$jarak} m dari {$kantor['nama']}. Batas absen {$kantor['radius_meter']} m.",
                422,
                'di_luar_radius',
            );
        }

        return $jarak;
    }
}
