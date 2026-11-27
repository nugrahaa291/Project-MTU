<?php

namespace App\Services;

use App\Enums\JenisKegiatan;
use App\Enums\KategoriIzin;
use App\Enums\StatusKegiatan;
use App\Exceptions\AbsensiException;
use App\Models\Karyawan;
use App\Models\KegiatanAbsensi;
use App\Models\User;
use App\Support\Geo;
use App\Support\WatermarkFoto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Aturan alur absensi baru. Semua aturan ada di sini (bukan di aplikasi HP), karena aplikasi
 * hanya tampilan: yang menentukan sah atau tidaknya sebuah catatan adalah server.
 *
 * Prinsip yang berlaku untuk semua jenis:
 *  - Identitas selalu dari akun yang sedang login (bukan dari data kiriman).
 *  - Waktu resmi selalu dari jam server.
 *  - Satu penekanan Kirim = satu catatan, dijaga oleh "kunci" (idempotensi) dan indeks unik database.
 */
class KegiatanAbsensiService
{
    public function __construct(
        private AbsensiService $waktu,
        private ReverseGeocoder $geocoder,
        private WatermarkFoto $watermark,
    ) {
    }

    /**
     * @return array{0: KegiatanAbsensi, 1: bool}  [catatan, barudibuat]
     */
    public function standby(Karyawan $karyawan, string $kunci): array
    {
        $this->pastikanAktif($karyawan);

        if ($ada = $this->sudahAda($karyawan, $kunci, JenisKegiatan::Standby)) {
            return [$ada, false];
        }

        return $this->simpan($karyawan, $kunci, [
            'jenis' => JenisKegiatan::Standby,
            'status' => StatusKegiatan::Tercatat,
        ]);
    }

    /**
     * Order: tujuan + lokasi aktual HP + selfie wajib lengkap. Posisi di luar radius kantor TETAP boleh.
     *
     * @return array{0: KegiatanAbsensi, 1: bool}
     */
    public function order(Karyawan $karyawan, string $kunci, string $tujuan, float $lat, float $lng, ?float $akurasi, UploadedFile $foto): array
    {
        $this->pastikanAktif($karyawan);

        if ($ada = $this->sudahAda($karyawan, $kunci, JenisKegiatan::Order)) {
            return [$ada, false];
        }

        $tujuan = trim((string) preg_replace('/\s+/u', ' ', $tujuan));
        if (mb_strlen($tujuan) < 3) {
            throw new AbsensiException('Lokasi tujuan pengantaran wajib diisi.', 422, 'tujuan_kosong');
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new AbsensiException('Koordinat lokasi tidak valid.', 422, 'lokasi_tidak_valid');
        }
        if ($akurasi !== null && $akurasi > config('absensi.order.maks_akurasi_meter', 1000)) {
            throw new AbsensiException(
                'Akurasi lokasi terlalu rendah (±'.round($akurasi).' m). Pindah ke area terbuka lalu ulangi pengambilan lokasi.',
                422,
                'akurasi_rendah',
            );
        }

        $sekarang = $this->waktu->sekarang();
        $nama = $this->geocoder->namaLokasi($lat, $lng);
        $teksLokasi = $nama ?? $this->formatKoordinat($lat, $lng, $akurasi);

        $isiFoto = $this->buatWatermark($foto, $sekarang, $teksLokasi);

        $path = 'kegiatan/order/'.$karyawan->id.'/'.$sekarang->format('Y-m-d').'/'
            .$sekarang->format('His').'-'.Str::lower(Str::random(8)).'.jpg';
        Storage::disk('local')->put($path, $isiFoto);

        $kantor = config('absensi.kantor', []);

        try {
            return $this->simpan($karyawan, $kunci, [
                'jenis' => JenisKegiatan::Order,
                'status' => StatusKegiatan::Tercatat,
                'tujuan' => $tujuan,
                'lat_aktual' => $lat,
                'lng_aktual' => $lng,
                'akurasi_meter' => $akurasi !== null ? (int) round($akurasi) : null,
                'nama_lokasi' => $nama,
                'jarak_kantor_meter' => isset($kantor['latitude'], $kantor['longitude'])
                    ? Geo::jarakMeter($lat, $lng, $kantor['latitude'], $kantor['longitude'])
                    : null,
                'foto' => $path,
            ], [$path]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    /**
     * Izin: libur (langsung), sakit (wajib SKD), lain-lain (wajib alasan, menunggu keputusan admin).
     *
     * @return array{0: KegiatanAbsensi, 1: bool}
     */
    public function izin(Karyawan $karyawan, string $kunci, KategoriIzin $kategori, ?string $alasan, ?UploadedFile $lampiran): array
    {
        $this->pastikanAktif($karyawan);

        if ($ada = $this->sudahAda($karyawan, $kunci, JenisKegiatan::Izin)) {
            return [$ada, false];
        }

        $alasan = $alasan === null ? null : trim((string) preg_replace('/[ \t]+/u', ' ', $alasan));
        $data = ['jenis' => JenisKegiatan::Izin, 'kategori_izin' => $kategori];
        $berkasBaru = [];

        if ($kategori === KategoriIzin::Libur) {
            $data['status'] = StatusKegiatan::Tercatat;
        } elseif ($kategori === KategoriIzin::Sakit) {
            if ($lampiran === null) {
                throw new AbsensiException('Surat Keterangan Dokter (SKD) wajib diunggah untuk izin sakit.', 422, 'skd_wajib');
            }
            $ekstensi = $this->periksaLampiran($lampiran);
            $sekarang = $this->waktu->sekarang();
            $path = 'izin/'.$karyawan->id.'/'.$sekarang->format('Y-m-d').'/skd-'.Str::lower(Str::random(12)).'.'.$ekstensi;
            Storage::disk('local')->putFileAs(dirname($path), $lampiran, basename($path));
            $berkasBaru[] = $path;
            $data['status'] = StatusKegiatan::Tercatat;
            $data['lampiran'] = $path;
            $data['alasan'] = $alasan ?: null;
        } else {
            if ($alasan === null || mb_strlen($alasan) < 5) {
                throw new AbsensiException('Alasan izin wajib diisi (minimal 5 karakter).', 422, 'alasan_wajib');
            }
            $data['status'] = StatusKegiatan::Menunggu;
            $data['alasan'] = $alasan;
        }

        try {
            return $this->simpan($karyawan, $kunci, $data, $berkasBaru);
        } catch (Throwable $e) {
            foreach ($berkasBaru as $berkas) {
                Storage::disk('local')->delete($berkas);
            }
            throw $e;
        }
    }

    /** Admin menyetujui izin lain-lain. WAJIB memilih karyawan pengganti. */
    public function setujui(KegiatanAbsensi $izin, ?Karyawan $pengganti, User $admin): KegiatanAbsensi
    {
        $this->pastikanAdmin($admin);

        if ($pengganti === null) {
            throw new AbsensiException('Izin hanya dapat disetujui setelah memilih karyawan pengganti.', 422, 'pengganti_wajib');
        }

        return DB::transaction(function () use ($izin, $pengganti, $admin) {
            // Kunci baris: dua admin yang memutuskan bersamaan dilayani bergantian
            $izin = KegiatanAbsensi::whereKey($izin->id)->lockForUpdate()->firstOrFail();
            $this->pastikanMenunggu($izin);

            if (! $pengganti->aktif) {
                throw new AbsensiException('Karyawan pengganti tidak aktif.', 422, 'pengganti_tidak_valid');
            }
            if ($pengganti->id === $izin->karyawan_id) {
                throw new AbsensiException('Karyawan pengganti tidak boleh orang yang sama dengan pemohon izin.', 422, 'pengganti_tidak_valid');
            }
            $sedangIzin = KegiatanAbsensi::where('karyawan_id', $pengganti->id)
                ->where('jenis', JenisKegiatan::Izin->value)
                ->whereIn('status', [StatusKegiatan::Tercatat->value, StatusKegiatan::Disetujui->value])
                ->whereDate('tanggal', $izin->tanggal->toDateString())
                ->exists();
            if ($sedangIzin) {
                throw new AbsensiException('Karyawan pengganti sendiri sedang izin pada tanggal tersebut.', 422, 'pengganti_tidak_valid');
            }

            $izin->update([
                'status' => StatusKegiatan::Disetujui,
                'pengganti_id' => $pengganti->id,
                'diputuskan_oleh' => $admin->id,
                'diputuskan_pada' => now(),
                'alasan_penolakan' => null,
            ]);

            return $izin;
        });
    }

    /** Admin menolak izin lain-lain (mis. tidak ada pengganti). Alasan wajib. */
    public function tolak(KegiatanAbsensi $izin, string $alasan, User $admin): KegiatanAbsensi
    {
        $this->pastikanAdmin($admin);

        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 3) {
            throw new AbsensiException('Alasan penolakan wajib diisi.', 422, 'alasan_penolakan_wajib');
        }

        return DB::transaction(function () use ($izin, $alasan, $admin) {
            $izin = KegiatanAbsensi::whereKey($izin->id)->lockForUpdate()->firstOrFail();
            $this->pastikanMenunggu($izin);

            $izin->update([
                'status' => StatusKegiatan::Ditolak,
                'pengganti_id' => null,
                'diputuskan_oleh' => $admin->id,
                'diputuskan_pada' => now(),
                'alasan_penolakan' => $alasan,
            ]);

            return $izin;
        });
    }

    /* ----------------------------------------------------------------- internal */

    private function pastikanAktif(Karyawan $karyawan): void
    {
        $hariIni = $this->waktu->sekarang()->toDateString();

        if (! $karyawan->aktif || $karyawan->tanggal_masuk->toDateString() > $hariIni) {
            throw new AbsensiException('Akun karyawan belum/tidak aktif untuk absensi.', 403, 'karyawan_tidak_aktif');
        }
    }

    private function pastikanAdmin(User $admin): void
    {
        if (! $admin->isHrd() || ! $admin->is_active) {
            throw new AbsensiException('Hanya admin (HRD) yang dapat memutuskan pengajuan izin.', 403, 'bukan_admin');
        }
    }

    private function pastikanMenunggu(KegiatanAbsensi $izin): void
    {
        $bisa = $izin->jenis === JenisKegiatan::Izin
            && $izin->kategori_izin === KategoriIzin::Lainnya
            && $izin->status === StatusKegiatan::Menunggu;

        if (! $bisa) {
            throw new AbsensiException(
                'Pengajuan ini tidak dalam status menunggu keputusan (mungkin sudah diputuskan admin lain).',
                409,
                'bukan_menunggu',
            );
        }
    }

    /** Kunci yang sama untuk jenis yang sama = kirim ulang (aman). Untuk jenis lain = bentrok. */
    private function sudahAda(Karyawan $karyawan, string $kunci, JenisKegiatan $jenis): ?KegiatanAbsensi
    {
        $ada = KegiatanAbsensi::where('karyawan_id', $karyawan->id)->where('kunci', $kunci)->first();

        if ($ada && $ada->jenis !== $jenis) {
            throw new AbsensiException('Kunci permintaan sudah dipakai untuk jenis absen yang lain.', 409, 'kunci_bentrok');
        }

        return $ada;
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  list<string>  $berkasBaru  berkas yang baru disimpan (dihapus bila ternyata sudah ada catatan yang sama)
     * @return array{0: KegiatanAbsensi, 1: bool}
     */
    private function simpan(Karyawan $karyawan, string $kunci, array $data, array $berkasBaru = []): array
    {
        $sekarang = $this->waktu->sekarang();

        try {
            $catatan = KegiatanAbsensi::create([
                ...$data,
                'karyawan_id' => $karyawan->id,
                'kunci' => $kunci,
                'tanggal' => $sekarang->toDateString(),
                'dicatat_pada' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan dengan kunci sama masuk bersamaan: yang kedua tidak membuat catatan baru
            foreach ($berkasBaru as $berkas) {
                Storage::disk('local')->delete($berkas);
            }
            $ada = KegiatanAbsensi::where('karyawan_id', $karyawan->id)->where('kunci', $kunci)->firstOrFail();

            return [$ada, false];
        }

        return [$catatan, true];
    }

    private function buatWatermark(UploadedFile $foto, \Carbon\CarbonImmutable $sekarang, string $teksLokasi): string
    {
        try {
            return $this->watermark->buat(
                $foto->getRealPath(),
                $sekarang->format('H.i.s').' '.$sekarang->format('T'),
                $sekarang->locale('id')->translatedFormat('l, d F Y'),
                $teksLokasi,
            );
        } catch (InvalidArgumentException $e) {
            throw new AbsensiException('Foto selfie tidak dapat diproses: '.$e->getMessage(), 422, 'foto_tidak_valid');
        } catch (RuntimeException $e) {
            report($e);
            throw new AbsensiException(
                'Server belum dapat memproses gambar (ekstensi GD pada PHP belum aktif). Hubungi admin sistem.',
                500,
                'server_tanpa_gd',
            );
        }
    }

    private function formatKoordinat(float $lat, float $lng, ?float $akurasi): string
    {
        $teks = number_format($lat, 6, '.', '').', '.number_format($lng, 6, '.', '');

        return $akurasi !== null ? $teks.' (±'.round($akurasi).' m)' : $teks;
    }

    /**
     * Memeriksa lampiran SKD berdasarkan ISI berkas (bukan nama/ekstensi dari pengguna).
     *
     * @return string ekstensi aman untuk menyimpan berkas
     */
    private function periksaLampiran(UploadedFile $berkas): string
    {
        $maksKb = (int) config('absensi.izin.maks_lampiran_kb', 5120);
        $path = $berkas->getRealPath();

        if (! $berkas->isValid() || $path === false || filesize($path) < 1) {
            throw new AbsensiException('Berkas SKD kosong atau rusak.', 422, 'lampiran_tidak_valid');
        }
        if ($berkas->getSize() > $maksKb * 1024) {
            throw new AbsensiException('Ukuran SKD maksimal '.round($maksKb / 1024, 1).' MB.', 422, 'lampiran_tidak_valid');
        }

        $mime = $berkas->getMimeType();
        $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? null;
        if ($ekstensi === null) {
            throw new AbsensiException('SKD harus berupa foto (JPG/PNG) atau PDF.', 422, 'lampiran_tidak_valid');
        }

        $kepala = (string) file_get_contents($path, false, null, 0, 5);
        if ($ekstensi === 'pdf' && $kepala !== '%PDF-') {
            throw new AbsensiException('Berkas PDF SKD tidak valid.', 422, 'lampiran_tidak_valid');
        }
        if ($ekstensi !== 'pdf' && @getimagesize($path) === false) {
            throw new AbsensiException('Foto SKD tidak valid atau rusak.', 422, 'lampiran_tidak_valid');
        }

        return $ekstensi;
    }
}
