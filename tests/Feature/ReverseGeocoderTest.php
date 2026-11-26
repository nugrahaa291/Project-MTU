<?php

namespace Tests\Feature;

use App\Services\ReverseGeocoder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReverseGeocoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['absensi.geocoder.aktif' => true, 'absensi.geocoder.user_agent' => 'PortalPT-Uji/1.0 (uji@example.com)']);
    }

    public function test_menyusun_nama_pendek_dan_mengirim_identitas_aplikasi(): void
    {
        Http::fake(['*' => Http::response(['address' => [
            'road' => 'Jalan Tuparev', 'neighbourhood' => 'RT 02', 'suburb' => 'Nagasari', 'city_district' => 'Karawang Barat', 'state' => 'Jawa Barat',
        ]])]);

        $this->assertSame('Jalan Tuparev, RT 02, Karawang Barat', app(ReverseGeocoder::class)->namaLokasi(-6.30199, 107.30544));

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'lat=-6.30199') && str_contains($r->url(), 'lon=107.30544')
                && $r->hasHeader('User-Agent', 'PortalPT-Uji/1.0 (uji@example.com)');
        });
    }

    public function test_hasil_disimpan_sehingga_titik_yang_sama_tidak_memanggil_layanan_lagi(): void
    {
        Http::fake(['*' => Http::response(['address' => ['road' => 'Jalan A']])]);

        $g = app(ReverseGeocoder::class);
        $this->assertSame('Jalan A', $g->namaLokasi(-6.30199, 107.30544));
        $this->assertSame('Jalan A', $g->namaLokasi(-6.301991, 107.305441)); // beda ~0,1 m = titik yang sama

        Http::assertSentCount(1);
    }

    public function test_kegagalan_menghasilkan_null_bukan_error(): void
    {
        foreach ([Http::response('x', 500), Http::response('x', 429), Http::response(['error' => 'Unable to geocode'], 200), Http::response('bukan json', 200)] as $balas) {
            Cache::flush();
            Http::fake(['*' => $balas]);
            $this->assertNull(app(ReverseGeocoder::class)->namaLokasi(-6.1, 106.8));
        }

        Cache::flush();
        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->assertNull(app(ReverseGeocoder::class)->namaLokasi(-6.1, 106.8));
    }

    public function test_dapat_dimatikan_lewat_pengaturan_tanpa_memanggil_layanan(): void
    {
        config(['absensi.geocoder.aktif' => false]);
        Http::fake();

        $this->assertNull(app(ReverseGeocoder::class)->namaLokasi(-6.1, 106.8));
        Http::assertNothingSent();
    }
}
