<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mengubah koordinat menjadi nama lokasi (jalan, kelurahan, kota) untuk watermark.
 * Selalu aman: bila layanan mati, lambat, atau menolak, hasilnya null dan watermark memakai koordinat.
 */
class ReverseGeocoder
{
    public function namaLokasi(float $lat, float $lng): ?string
    {
        $cfg = config('absensi.geocoder', []);
        if (! ($cfg['aktif'] ?? true)) {
            return null;
        }

        // Dibulatkan ke ~11 m: kunjungan berulang ke titik yang sama tidak memanggil layanan berkali-kali
        $kunci = 'geocode:'.round($lat, 4).','.round($lng, 4);
        $tersimpan = Cache::get($kunci);
        if (is_array($tersimpan)) {
            return $tersimpan['nama'];
        }

        $nama = $this->tanya($lat, $lng, $cfg);
        // Hasil sukses disimpan lama; kegagalan hanya sebentar agar layanan tidak dibanjiri
        Cache::put($kunci, ['nama' => $nama], $nama ? now()->addDay() : now()->addMinute());

        return $nama;
    }

    private function tanya(float $lat, float $lng, array $cfg): ?string
    {
        try {
            $res = Http::timeout((int) ($cfg['timeout'] ?? 3))
                ->withHeaders([
                    'User-Agent' => $cfg['user_agent'] ?? 'PortalPT-Absensi/1.0',
                    'Accept-Language' => 'id',
                ])
                ->get($cfg['url'] ?? 'https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $res->ok()) {
            return null;
        }

        $data = $res->json();

        return is_array($data) ? $this->susun($data) : null;
    }

    /** Menyusun nama pendek: jalan, kelurahan/desa, kecamatan/kota (maksimal 3 bagian). */
    private function susun(array $data): ?string
    {
        $alamat = $data['address'] ?? [];
        $bagian = [];

        foreach ([['road', 'pedestrian', 'highway'], ['neighbourhood', 'suburb', 'village', 'hamlet'], ['city_district', 'city', 'town', 'municipality', 'county', 'state_district']] as $pilihan) {
            foreach ($pilihan as $kunci) {
                if (! empty($alamat[$kunci]) && is_string($alamat[$kunci]) && ! in_array($alamat[$kunci], $bagian, true)) {
                    $bagian[] = $alamat[$kunci];
                    break;
                }
            }
        }

        if ($bagian === [] && ! empty($data['name']) && is_string($data['name'])) {
            $bagian[] = $data['name'];
        }
        if ($bagian === [] && ! empty($data['display_name']) && is_string($data['display_name'])) {
            $bagian = array_slice(array_map('trim', explode(',', $data['display_name'])), 0, 3);
        }

        $nama = trim(implode(', ', $bagian));

        return $nama === '' ? null : mb_substr($nama, 0, 255);
    }
}
