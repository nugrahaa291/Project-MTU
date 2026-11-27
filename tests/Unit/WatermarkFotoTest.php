<?php

namespace Tests\Unit;

use App\Support\WatermarkFoto;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Uji watermark SUNGGUHAN dengan GD. Dilewati bila ekstensi GD belum aktif di PHP pengujian.
 * (Cek ekstensi: php -m | findstr gd)
 */
class WatermarkFotoTest extends TestCase
{
    private string $folderTemp = '';

    protected function setUp(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak aktif.');
        }
        $this->folderTemp = sys_get_temp_dir().'/wm-uji-'.bin2hex(random_bytes(4));
        mkdir($this->folderTemp);
    }

    protected function tearDown(): void
    {
        if ($this->folderTemp === '') {
            return;
        }
        foreach (glob($this->folderTemp.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->folderTemp);
    }

    private function buatWm(): WatermarkFoto
    {
        return new WatermarkFoto(dirname(__DIR__, 2).'/resources/fonts');
    }

    private function fotoUji(int $w, int $h, string $format = 'jpeg'): string
    {
        $g = imagecreatetruecolor($w, $h);
        imagefilledrectangle($g, 0, 0, $w, $h, imagecolorallocate($g, 200, 220, 240)); // latar terang
        $path = $this->folderTemp.'/asli.'.($format === 'png' ? 'png' : 'jpg');
        $format === 'png' ? imagepng($g, $path) : imagejpeg($g, $path, 90);

        return $path;
    }

    private function dekode(string $jpeg): \GdImage
    {
        $g = imagecreatefromstring($jpeg);
        $this->assertInstanceOf(\GdImage::class, $g, 'Hasil harus gambar yang valid');

        return $g;
    }

    public function test_hasilnya_jpeg_dan_pita_watermark_ada_di_bagian_bawah(): void
    {
        $hasil = $this->buatWm()->buat($this->fotoUji(960, 1280), '10.32.05 WIB', 'Sabtu, 14 November 2026', 'Jalan Tuparev, Nagasari, Karawang Barat');

        $this->assertSame("\xFF\xD8", substr($hasil, 0, 2), 'Hasil harus berformat JPEG');
        $g = $this->dekode($hasil);
        $this->assertSame(960, imagesx($g));
        $this->assertSame(1280, imagesy($g));

        $rgb = fn ($x, $y) => ['r' => (imagecolorat($g, $x, $y) >> 16) & 255, 'g' => (imagecolorat($g, $x, $y) >> 8) & 255, 'b' => imagecolorat($g, $x, $y) & 255];
        $atas = $rgb(480, 100);
        $bawah = $rgb(480, 1270);

        $this->assertGreaterThan(190, $atas['r'], 'Bagian atas foto tidak boleh berubah (tetap terang)');
        $this->assertLessThan(120, $bawah['r'], 'Pita watermark di bawah harus gelap');
    }

    public function test_ada_teks_putih_di_pita_dan_titik_logo_berwarna_koral(): void
    {
        $g = $this->dekode($this->buatWm()->buat($this->fotoUji(960, 1280), '10.32.05 WIB', 'Sabtu, 14 November 2026', 'Jalan Tuparev'));
        $w = imagesx($g);
        $h = imagesy($g);

        $putih = 0;
        $koral = 0;
        for ($y = (int) ($h * 0.80); $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $c = imagecolorat($g, $x, $y);
                [$r, $gg, $b] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
                if ($r > 235 && $gg > 235 && $b > 235) {
                    $putih++;
                }
                if ($r > 225 && $gg > 80 && $gg < 140 && $b > 40 && $b < 110) { // #f8694a (koral)
                    $koral++;
                }
            }
        }

        $this->assertGreaterThan(150, $putih, 'Teks watermark (putih) harus tergambar');
        $this->assertGreaterThan(40, $koral, 'Titik koral pada logo MTU dan garis aksen harus tergambar');
    }

    public function test_watermark_membuat_hasil_berbeda_untuk_teks_berbeda(): void
    {
        $foto = $this->fotoUji(800, 600);
        $a = $this->buatWm()->buat($foto, '07.05.41 WIB', 'Sabtu, 14 November 2026', 'Lokasi A');
        $b = $this->buatWm()->buat($foto, '07.05.41 WIB', 'Sabtu, 14 November 2026', '-6.301993, 107.305442 (±12 m)');

        $this->assertNotSame($a, $b);
    }

    public function test_foto_besar_diperkecil_dan_png_diterima(): void
    {
        $besar = $this->dekode($this->buatWm()->buat($this->fotoUji(3000, 2000), '08.00.00 WIB', 'Senin, 16 November 2026', 'X'));
        $this->assertSame(1280, imagesx($besar));
        $this->assertSame(853, imagesy($besar));

        $png = $this->dekode($this->buatWm()->buat($this->fotoUji(640, 480, 'png'), '08.00.00 WIB', 'Senin, 16 November 2026', 'X'));
        $this->assertSame(640, imagesx($png));
    }

    public function test_lokasi_sangat_panjang_dipotong_dan_tidak_error(): void
    {
        $panjang = str_repeat('Jalan Raya Karawang-Cikampek Kilometer 45 Desa Sukamakmur Kecamatan Telukjambe ', 6);

        $hasil = $this->buatWm()->buat($this->fotoUji(960, 1280), '10.32.05 WIB', 'Sabtu, 14 November 2026', $panjang);

        $this->dekode($hasil);
        $this->assertSame(1280, imagesy($this->dekode($hasil)));
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $path = $this->folderTemp.'/palsu.jpg';
        file_put_contents($path, 'ini bukan gambar sama sekali');

        $this->expectException(InvalidArgumentException::class);
        $this->buatWm()->buat($path, '10.32.05 WIB', 'Sabtu, 14 November 2026', 'X');
    }

    public function test_gambar_rusak_ditolak(): void
    {
        $path = $this->folderTemp.'/rusak.jpg';
        file_put_contents($path, "\xFF\xD8\xFF\xE0".random_bytes(40)); // kepala JPEG tanpa isi

        $this->expectException(InvalidArgumentException::class);
        $this->buatWm()->buat($path, '10.32.05 WIB', 'Sabtu, 14 November 2026', 'X');
    }
}
