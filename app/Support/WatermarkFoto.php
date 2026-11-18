<?php

namespace App\Support;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Membubuhkan watermark pada selfie: waktu & tanggal (WIB), lokasi aktual, dan logo MTU.
 *
 * Dikerjakan di SERVER (bukan di HP) supaya waktu dan lokasi pada gambar berasal dari data yang
 * divalidasi server. Hasilnya JPEG; foto asli dari HP tidak pernah disimpan.
 *
 * Logo MTU di website adalah teks "MTU" tebal dengan titik berwarna koral (font Hind),
 * jadi logo yang sama digambar di sini lewat font Hind yang disertakan di resources/fonts.
 *
 * Kelas ini sengaja tidak bergantung pada Laravel sehingga mudah diuji sendiri.
 */
class WatermarkFoto
{
    private const MAKS_PIKSEL = 25_000_000; // melindungi memori server dari gambar raksasa

    public function __construct(private string $folderFont, private int $sisiMaks = 1280)
    {
    }

    /**
     * @param  string  $pathAsli    lokasi berkas foto unggahan (JPEG/PNG)
     * @param  string  $teksWaktu   contoh: "10.32.05 WIB"
     * @param  string  $teksTanggal contoh: "Sabtu, 14 November 2026"
     * @param  string  $teksLokasi  nama lokasi, atau koordinat bila nama tidak tersedia
     * @return string isi berkas JPEG
     */
    public function buat(string $pathAsli, string $teksWaktu, string $teksTanggal, string $teksLokasi): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi GD tidak aktif di PHP.');
        }

        $info = @getimagesize($pathAsli);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new InvalidArgumentException('Berkas bukan gambar JPEG/PNG yang valid.');
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAKS_PIKSEL) {
            throw new InvalidArgumentException('Ukuran (resolusi) foto tidak dapat diproses.');
        }

        $gambar = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($pathAsli) : @imagecreatefromjpeg($pathAsli);
        if (! $gambar instanceof GdImage) {
            throw new InvalidArgumentException('Foto rusak dan tidak dapat dibaca.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $gambar = $this->putarSesuaiExif($gambar, $pathAsli);
        }
        $gambar = $this->skala($gambar);

        $this->gambarWatermark($gambar, $teksWaktu, $teksTanggal, $teksLokasi);

        ob_start();
        imagejpeg($gambar, null, 85);
        $isi = (string) ob_get_clean();
        unset($gambar);

        return $isi;
    }

    private function putarSesuaiExif(GdImage $gambar, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }
        $exif = @exif_read_data($path);
        $sudut = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($sudut === 0) {
            return $gambar;
        }
        $putar = imagerotate($gambar, $sudut, 0);

        return $putar instanceof GdImage ? $putar : $gambar;
    }

    private function skala(GdImage $gambar): GdImage
    {
        $w = imagesx($gambar);
        $h = imagesy($gambar);
        $rasio = min(1, $this->sisiMaks / max($w, $h));
        if ($rasio >= 1) {
            return $gambar;
        }
        $baru = imagescale($gambar, (int) round($w * $rasio), (int) round($h * $rasio));

        return $baru instanceof GdImage ? $baru : $gambar;
    }

    private function gambarWatermark(GdImage $g, string $waktu, string $tanggal, string $lokasi): void
    {
        $w = imagesx($g);
        $h = imagesy($g);
        imagealphablending($g, true);

        $fontTebal = $this->folderFont.'/Hind-Bold.ttf';
        $fontSedang = $this->folderFont.'/Hind-SemiBold.ttf';
        $fontBiasa = $this->folderFont.'/Hind-Regular.ttf';
        $adaFont = function_exists('imagettftext') && is_file($fontTebal) && is_file($fontSedang) && is_file($fontBiasa);

        $putih = imagecolorallocate($g, 255, 255, 255);
        $koral = imagecolorallocate($g, 248, 105, 74);   // #f8694a, sama dengan titik pada logo website
        $abu = imagecolorallocate($g, 226, 229, 235);
        $pita = imagecolorallocatealpha($g, 20, 22, 28, 40);

        $pad = (int) round($w * 0.035);
        $pxWaktu = max(22, (int) round($w * 0.052));
        $pxTeks = max(15, (int) round($w * 0.030));
        $pxLogo = max(24, (int) round($w * 0.058));
        $lebarTeks = $w - 2 * $pad;

        // Baris lokasi dipotong rapi menjadi maksimal 2 baris
        $barisLokasi = $adaFont
            ? $this->bungkus($lokasi, $fontBiasa, $pxTeks, $lebarTeks, 2)
            : [mb_substr($lokasi, 0, 60)];

        $tinggiBarisWaktu = (int) round($pxWaktu * 1.25);
        $tinggiBaris = (int) round($pxTeks * 1.4);
        $tinggiPita = $pad + $tinggiBarisWaktu + $tinggiBaris * (1 + count($barisLokasi)) + (int) round($pad * 0.6);
        $atasPita = $h - $tinggiPita;

        imagefilledrectangle($g, 0, $atasPita, $w, $h, $pita);
        imagefilledrectangle($g, 0, $atasPita, $w, $atasPita + max(2, (int) round($w * 0.004)), $koral);

        $y = $atasPita + (int) round($pad * 0.75) + $pxWaktu;

        if (! $adaFont) {
            // Cadangan tanpa FreeType: teks polos dengan font bawaan GD
            $yy = $atasPita + 10;
            foreach (array_merge(['MTU.', $waktu, $tanggal], $barisLokasi) as $baris) {
                imagestring($g, 5, $pad, $yy, $baris, $putih);
                $yy += 18;
            }

            return;
        }

        // Logo MTU di kanan atas pita: "MTU" putih + titik koral
        $lebarMtu = $this->lebarTeks('MTU', $fontTebal, $pxLogo);
        $lebarTitik = $this->lebarTeks('.', $fontTebal, $pxLogo);
        $xLogo = $w - $pad - $lebarMtu - $lebarTitik;
        $this->tulis($g, 'MTU', $fontTebal, $pxLogo, $xLogo, $atasPita + (int) round($pad * 0.75) + $pxLogo, $putih);
        $this->tulis($g, '.', $fontTebal, $pxLogo, $xLogo + $lebarMtu, $atasPita + (int) round($pad * 0.75) + $pxLogo, $koral);

        // Waktu besar, lalu tanggal, lalu lokasi
        $this->tulis($g, $waktu, $fontTebal, $pxWaktu, $pad, $y, $putih);
        $y += $tinggiBarisWaktu - (int) round($pxWaktu * 0.25) + (int) round($pxTeks * 0.2);
        $this->tulis($g, $tanggal, $fontSedang, $pxTeks, $pad, $y, $abu);
        foreach ($barisLokasi as $baris) {
            $y += $tinggiBaris;
            $this->tulis($g, $baris, $fontBiasa, $pxTeks, $pad, $y, $putih);
        }
    }

    /** GD memakai satuan "point" (96 dpi) untuk ukuran font; konversi dari piksel. */
    private function titik(int $px): float
    {
        return $px * 0.75;
    }

    private function tulis(GdImage $g, string $teks, string $font, int $px, int $x, int $y, int $warna): void
    {
        imagettftext($g, $this->titik($px), 0, $x, $y, $warna, $font, $teks);
    }

    private function lebarTeks(string $teks, string $font, int $px): int
    {
        $kotak = imagettfbbox($this->titik($px), 0, $font, $teks);

        return $kotak === false ? 0 : (int) abs($kotak[2] - $kotak[0]);
    }

    /** @return list<string> */
    private function bungkus(string $teks, string $font, int $px, int $lebarMaks, int $barisMaks): array
    {
        $teks = trim(preg_replace('/\s+/u', ' ', $teks) ?? '');
        if ($teks === '') {
            return [''];
        }

        $baris = [];
        $sekarang = '';
        foreach (explode(' ', $teks) as $kata) {
            // Kata yang lebih panjang dari satu baris dipecah per huruf
            while ($this->lebarTeks($kata, $font, $px) > $lebarMaks && mb_strlen($kata) > 1) {
                $potong = mb_strlen($kata);
                while ($potong > 1 && $this->lebarTeks(mb_substr($kata, 0, $potong), $font, $px) > $lebarMaks) {
                    $potong--;
                }
                if ($sekarang !== '') {
                    $baris[] = $sekarang;
                    $sekarang = '';
                }
                $baris[] = mb_substr($kata, 0, $potong);
                $kata = mb_substr($kata, $potong);
            }

            $kandidat = $sekarang === '' ? $kata : $sekarang.' '.$kata;
            if ($this->lebarTeks($kandidat, $font, $px) <= $lebarMaks) {
                $sekarang = $kandidat;
            } else {
                $baris[] = $sekarang;
                $sekarang = $kata;
            }
        }
        if ($sekarang !== '') {
            $baris[] = $sekarang;
        }

        if (count($baris) > $barisMaks) {
            $baris = array_slice($baris, 0, $barisMaks);
            $akhir = rtrim($baris[$barisMaks - 1], " ,.");
            while ($akhir !== '' && $this->lebarTeks($akhir.'…', $font, $px) > $lebarMaks) {
                $akhir = mb_substr($akhir, 0, -1);
            }
            $baris[$barisMaks - 1] = $akhir.'…';
        }

        return $baris;
    }
}
