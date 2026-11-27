<?php

namespace Tests\Feature;

use App\Enums\JenisKegiatan;
use App\Enums\StatusKegiatan;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\KegiatanAbsensi;
use App\Support\WatermarkFoto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\Support\WatermarkPalsu;
use Tests\TestCase;

/** Alur absensi baru: Standby / Order / Izin lewat API. */
class KegiatanAbsensiTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private WatermarkPalsu $watermark;

    /** Balasan geocoder yang dapat diganti di tiap tes (Http::fake yang kedua tidak menimpa yang pertama). */
    private \Closure $balasGeocoder;

    // Sabtu, 14 November 2026, 10:32:05 WIB
    private const WAKTU = '2026-11-14 10:32:05';

    // Titik uji ~ Karawang; titik kantor di config ada di -6.3215, 107.3376 (>2 km dari titik ini)
    private const LOKASI = ['latitude' => -6.3019925, 'longitude' => 107.3054416, 'akurasi' => 12];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();

        $this->watermark = new WatermarkPalsu();
        $this->app->instance(WatermarkFoto::class, $this->watermark);

        $this->balasGeocoder = fn () => Http::response([
            'address' => ['road' => 'Jalan Tuparev', 'suburb' => 'Nagasari', 'city_district' => 'Karawang Barat', 'state' => 'Jawa Barat'],
        ]);
        Http::fake(['*nominatim*' => fn ($request) => ($this->balasGeocoder)($request)]);

        $this->travelTo(CarbonImmutable::parse(self::WAKTU, 'Asia/Jakarta'));
    }

    private function masukApi(?Karyawan $k = null): array
    {
        $k ??= $this->karyawan();
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/login', ['nip' => $k->nip, 'pin' => self::PIN_KARYAWAN, 'device_name' => 'hp-'.$k->id])
            ->assertOk()->json('token');

        return ['Authorization' => "Bearer $token"];
    }

    private function kunci(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function kirim(string $jenis, array $header, array $data)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/aktivitas/$jenis", $data, $header);
    }

    private function dataOrder(array $o = []): array
    {
        return ['kunci' => $this->kunci(), 'tujuan' => 'Gudang PT Maju Jaya, Cikarang', ...self::LOKASI, 'foto' => $this->png('selfie.png'), ...$o];
    }

    /* ------------------------------------------------------------- akses */

    public function test_tanpa_token_ditolak_dan_hrd_tidak_bisa_memakai_api_karyawan(): void
    {
        $this->postJson('/api/aktivitas/standby', ['kunci' => $this->kunci()])->assertUnauthorized();

        \Laravel\Sanctum\Sanctum::actingAs($this->hrd());
        $this->postJson('/api/aktivitas/standby', ['kunci' => $this->kunci()])->assertForbidden();
        $this->assertSame(0, KegiatanAbsensi::count());
    }

    /* ----------------------------------------------------------- standby */

    public function test_standby_langsung_tercatat_tanpa_lokasi_selfie_atau_keterangan(): void
    {
        $k = $this->karyawan();
        $header = $this->masukApi($k);
        $lain = $this->karyawan();

        // "karyawan_id" dan "waktu" dari kiriman TIDAK dipakai: identitas dari akun login, waktu dari server
        $this->kirim('standby', $header, ['kunci' => $this->kunci(), 'karyawan_id' => $lain->id, 'waktu' => '2020-01-01 00:00:00'])
            ->assertCreated()->assertJsonPath('kegiatan.jenis', 'standby')->assertJsonPath('duplikat', false);

        $catatan = KegiatanAbsensi::firstOrFail();
        $this->assertSame($k->id, $catatan->karyawan_id);
        $this->assertSame(JenisKegiatan::Standby, $catatan->jenis);
        $this->assertSame(StatusKegiatan::Tercatat, $catatan->status);
        $this->assertSame('2026-11-14', $catatan->tanggal->toDateString());
        $this->assertSame('10:32:05', $catatan->waktuWib()->format('H:i:s')); // jam SERVER dalam WIB
        $this->assertNull($catatan->foto);
        $this->assertNull($catatan->lat_aktual);
        $this->assertNull($catatan->tujuan);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_standby_kirim_ulang_dengan_kunci_sama_tidak_membuat_catatan_ganda(): void
    {
        $header = $this->masukApi();
        $kunci = $this->kunci();

        $this->kirim('standby', $header, ['kunci' => $kunci])->assertCreated()->assertJsonPath('duplikat', false);
        $this->kirim('standby', $header, ['kunci' => $kunci])->assertOk()->assertJsonPath('duplikat', true);
        $this->kirim('standby', $header, ['kunci' => $kunci])->assertOk()->assertJsonPath('duplikat', true);

        $this->assertSame(1, KegiatanAbsensi::count());

        // Penekanan tombol yang BERBEDA (kunci baru) adalah catatan baru yang sah
        $this->kirim('standby', $header, ['kunci' => $this->kunci()])->assertCreated();
        $this->assertSame(2, KegiatanAbsensi::count());
    }

    public function test_kunci_wajib_dan_tidak_boleh_dipakai_untuk_jenis_lain(): void
    {
        $header = $this->masukApi();
        $kunci = $this->kunci();

        $this->kirim('standby', $header, [])->assertStatus(422)->assertJsonValidationErrors('kunci');
        $this->kirim('standby', $header, ['kunci' => 'pendek'])->assertStatus(422)->assertJsonValidationErrors('kunci');

        $this->kirim('standby', $header, ['kunci' => $kunci])->assertCreated();
        $this->kirim('izin', $header, ['kunci' => $kunci, 'kategori' => 'libur'])->assertStatus(409)->assertJsonPath('kode', 'kunci_bentrok');
        $this->assertSame(1, KegiatanAbsensi::count());
    }

    public function test_kunci_tidak_bisa_membaca_catatan_karyawan_lain(): void
    {
        $kunci = $this->kunci();
        $this->kirim('standby', $this->masukApi(), ['kunci' => $kunci])->assertCreated();

        // Karyawan lain memakai kunci yang sama: tetap catatan baru miliknya sendiri
        $this->kirim('standby', $this->masukApi(), ['kunci' => $kunci])->assertCreated();
        $this->assertSame(2, KegiatanAbsensi::count());
    }

    /* ------------------------------------------------------------- order */

    public function test_order_lengkap_tersimpan_dengan_tujuan_dan_lokasi_aktual_terpisah(): void
    {
        $k = $this->karyawan();
        $this->kirim('order', $this->masukApi($k), $this->dataOrder())
            ->assertCreated()->assertJsonPath('kegiatan.jenis', 'order')
            ->assertJsonPath('kegiatan.tujuan', 'Gudang PT Maju Jaya, Cikarang')
            ->assertJsonPath('kegiatan.nama_lokasi', 'Jalan Tuparev, Nagasari, Karawang Barat')
            ->assertJsonPath('kegiatan.jam', '10:32:05');

        $c = KegiatanAbsensi::firstOrFail();
        $this->assertSame($k->id, $c->karyawan_id);
        $this->assertSame('Gudang PT Maju Jaya, Cikarang', $c->tujuan);                 // data 1: tujuan pengantaran
        $this->assertEqualsWithDelta(-6.3019925, $c->lat_aktual, 0.0000001);            // data 2: lokasi aktual HP
        $this->assertEqualsWithDelta(107.3054416, $c->lng_aktual, 0.0000001);
        $this->assertSame(12, $c->akurasi_meter);
        $this->assertSame('Jalan Tuparev, Nagasari, Karawang Barat', $c->nama_lokasi);
        $this->assertSame('10:32:05', $c->waktuWib()->format('H:i:s'));                 // waktu resmi = server
        $this->assertNotNull($c->foto);
        $this->assertStringStartsWith('kegiatan/order/'.$k->id.'/2026-11-14/', $c->foto);
        Storage::disk('local')->assertExists($c->foto);                                 // disimpan di disk privat
    }

    public function test_watermark_memuat_lokasi_aktual_tanggal_dan_jam_wib(): void
    {
        $this->kirim('order', $this->masukApi(), $this->dataOrder())->assertCreated();

        $this->assertCount(1, $this->watermark->panggilan);
        $this->assertSame([
            'waktu' => '10.32.05 WIB',
            'tanggal' => 'Sabtu, 14 November 2026',
            'lokasi' => 'Jalan Tuparev, Nagasari, Karawang Barat',
        ], $this->watermark->panggilan[0]);
    }

    public function test_watermark_memakai_koordinat_bila_nama_lokasi_tidak_tersedia(): void
    {
        $this->balasGeocoder = fn () => Http::response('error', 500);

        $this->kirim('order', $this->masukApi(), $this->dataOrder())->assertCreated()->assertJsonPath('kegiatan.nama_lokasi', null);

        $this->assertSame('-6.301993, 107.305442 (±12 m)', $this->watermark->panggilan[0]['lokasi']);
        $this->assertNull(KegiatanAbsensi::firstOrFail()->nama_lokasi);
    }

    public function test_geocoder_mati_atau_timeout_tidak_menggagalkan_order(): void
    {
        $this->balasGeocoder = fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout');

        $this->kirim('order', $this->masukApi(), $this->dataOrder())->assertCreated();
        $this->assertSame('-6.301993, 107.305442 (±12 m)', $this->watermark->panggilan[0]['lokasi']);
    }

    public function test_order_dari_luar_radius_kantor_tetap_bisa_dikirim(): void
    {
        // Titik uji lebih dari 2 km dari kantor (radius kantor 100 m) dan tetap diterima
        $this->kirim('order', $this->masukApi(), $this->dataOrder())->assertCreated();

        $c = KegiatanAbsensi::firstOrFail();
        $this->assertGreaterThan(config('absensi.kantor.radius_meter'), $c->jarak_kantor_meter);
        $this->assertGreaterThan(1000, $c->jarak_kantor_meter); // jarak tercatat sebagai informasi
    }

    public function test_order_tidak_dicatat_bila_tujuan_lokasi_atau_selfie_tidak_lengkap(): void
    {
        $header = $this->masukApi();

        foreach ([
            'tanpa tujuan' => ['tujuan' => null, 'galat' => 'tujuan'],
            'tujuan terlalu pendek' => ['tujuan' => 'ab', 'galat' => 'tujuan'],
            'tanpa latitude' => ['latitude' => null, 'galat' => 'latitude'],
            'tanpa longitude' => ['longitude' => null, 'galat' => 'longitude'],
            'tanpa selfie' => ['foto' => null, 'galat' => 'foto'],
            'latitude di luar jangkauan' => ['latitude' => 123, 'galat' => 'latitude'],
            'selfie berupa PDF' => ['foto' => $this->pdf('selfie.pdf'), 'galat' => 'foto'],
        ] as $nama => $ubah) {
            $galat = $ubah['galat'];
            unset($ubah['galat']);
            $data = array_filter([...$this->dataOrder(), ...$ubah], fn ($v) => $v !== null);

            $this->kirim('order', $header, $data)->assertStatus(422)->assertJsonValidationErrors($galat);
        }

        $this->assertSame(0, KegiatanAbsensi::count(), 'Tidak boleh ada catatan Order yang tidak lengkap');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], $this->watermark->panggilan);
    }

    public function test_order_dengan_akurasi_sangat_buruk_ditolak(): void
    {
        $this->kirim('order', $this->masukApi(), $this->dataOrder(['akurasi' => 5000]))
            ->assertStatus(422)->assertJsonPath('kode', 'akurasi_rendah');

        $this->assertSame(0, KegiatanAbsensi::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_order_kirim_ulang_dengan_kunci_sama_tidak_menggandakan_catatan_maupun_berkas(): void
    {
        $header = $this->masukApi();
        $kunci = $this->kunci();

        $this->kirim('order', $header, $this->dataOrder(['kunci' => $kunci]))->assertCreated();
        $this->kirim('order', $header, $this->dataOrder(['kunci' => $kunci]))->assertOk()->assertJsonPath('duplikat', true);

        $this->assertSame(1, KegiatanAbsensi::count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertCount(1, $this->watermark->panggilan); // kiriman ulang tidak memproses foto lagi
    }

    public function test_dua_permintaan_serentak_dengan_kunci_sama_hanya_membuat_satu_catatan(): void
    {
        $k = $this->karyawan();
        $header = $this->masukApi($k);
        $kunci = $this->kunci();

        // Saat permintaan pertama masih memproses (menunggu geocoder), permintaan lain dengan kunci sama sudah tersimpan
        $this->balasGeocoder = function () use ($k, $kunci) {
            KegiatanAbsensi::create([
                'karyawan_id' => $k->id, 'kunci' => $kunci, 'jenis' => 'order', 'status' => 'tercatat',
                'tanggal' => '2026-11-14', 'dicatat_pada' => now(), 'tujuan' => 'Pemenang balapan',
            ]);

            return Http::response(['address' => ['road' => 'Jalan X']]);
        };

        $this->kirim('order', $header, $this->dataOrder(['kunci' => $kunci]))->assertOk()->assertJsonPath('duplikat', true);

        $this->assertSame(1, KegiatanAbsensi::count());
        $this->assertSame('Pemenang balapan', KegiatanAbsensi::firstOrFail()->tujuan);
        $this->assertSame([], Storage::disk('local')->allFiles(), 'Berkas dari permintaan yang kalah harus dibersihkan');
    }

    public function test_kegagalan_memproses_gambar_tidak_meninggalkan_catatan(): void
    {
        $header = $this->masukApi();

        $this->watermark->gagal = 'gd';
        $this->kirim('order', $header, $this->dataOrder())->assertStatus(500)->assertJsonPath('kode', 'server_tanpa_gd');

        $this->watermark->gagal = 'foto';
        $this->kirim('order', $header, $this->dataOrder())->assertStatus(422)->assertJsonPath('kode', 'foto_tidak_valid');

        $this->assertSame(0, KegiatanAbsensi::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_karyawan_yang_tidak_aktif_tidak_bisa_mengirim(): void
    {
        $k = $this->karyawan();
        $header = $this->masukApi($k);
        $k->update(['aktif' => false]); // token masih hidup, tetapi data karyawan sudah nonaktif

        $this->kirim('standby', $header, ['kunci' => $this->kunci()])->assertForbidden()->assertJsonPath('kode', 'karyawan_tidak_aktif');
        $this->assertSame(0, KegiatanAbsensi::count());
    }

    /* -------------------------------------------------------------- izin */

    public function test_izin_libur_langsung_tercatat(): void
    {
        $this->kirim('izin', $this->masukApi(), ['kunci' => $this->kunci(), 'kategori' => 'libur'])
            ->assertCreated()->assertJsonPath('kegiatan.status', 'tercatat')->assertJsonPath('kegiatan.kategori_izin', 'libur');

        $c = KegiatanAbsensi::firstOrFail();
        $this->assertSame(StatusKegiatan::Tercatat, $c->status);
        $this->assertNull($c->lampiran);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_izin_sakit_ditolak_tanpa_skd(): void
    {
        $header = $this->masukApi();

        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'sakit'])
            ->assertStatus(422)->assertJsonPath('kode', 'skd_wajib');
        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'sakit', 'alasan' => 'demam'])
            ->assertStatus(422)->assertJsonPath('kode', 'skd_wajib');

        $this->assertSame(0, KegiatanAbsensi::count());
    }

    public function test_izin_sakit_dengan_skd_valid_tersimpan_privat(): void
    {
        $k = $this->karyawan();
        $this->kirim('izin', $this->masukApi($k), ['kunci' => $this->kunci(), 'kategori' => 'sakit', 'lampiran' => $this->pdf('skd.pdf')])
            ->assertCreated()->assertJsonPath('kegiatan.status', 'tercatat')->assertJsonPath('kegiatan.ada_lampiran', true);

        $c = KegiatanAbsensi::firstOrFail();
        $this->assertStringStartsWith('izin/'.$k->id.'/2026-11-14/skd-', $c->lampiran);
        $this->assertStringEndsWith('.pdf', $c->lampiran);          // ekstensi dari ISI berkas, bukan nama kiriman
        Storage::disk('local')->assertExists($c->lampiran);
        $this->assertStringNotContainsString('skd.pdf', $c->lampiran); // nama dari pengguna tidak dipakai

        // SKD berupa foto juga boleh
        $this->kirim('izin', $this->masukApi($k), ['kunci' => $this->kunci(), 'kategori' => 'sakit', 'lampiran' => $this->png('skd.png')])
            ->assertCreated();
    }

    public function test_lampiran_skd_yang_tidak_sah_ditolak(): void
    {
        $header = $this->masukApi();
        $palsuPdf = UploadedFile::fake()->createWithContent('skd.pdf', 'ini bukan pdf, hanya teks biasa yang cukup panjang untuk lolos ukuran minimal berkas');
        $skrip = UploadedFile::fake()->createWithContent('skd.php', "<?php echo 'x'; // ".str_repeat('a', 200));
        $kosong = UploadedFile::fake()->createWithContent('skd.pdf', '');

        foreach (['PDF palsu (isi teks)' => $palsuPdf, 'skrip PHP' => $skrip, 'berkas kosong' => $kosong] as $nama => $berkas) {
            $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'sakit', 'lampiran' => $berkas])
                ->assertStatus(422);
        }

        $this->assertSame(0, KegiatanAbsensi::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_izin_lain_lain_wajib_alasan_dan_menunggu_keputusan(): void
    {
        $header = $this->masukApi();

        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'lainnya'])
            ->assertStatus(422)->assertJsonPath('kode', 'alasan_wajib');
        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'lainnya', 'alasan' => 'ya'])
            ->assertStatus(422)->assertJsonPath('kode', 'alasan_wajib');
        $this->assertSame(0, KegiatanAbsensi::count());

        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'lainnya', 'alasan' => 'Rumah keluarga terkena banjir'])
            ->assertCreated()->assertJsonPath('kegiatan.status', 'menunggu')
            ->assertJsonPath('kegiatan.status_label', 'Menunggu Keputusan');

        $c = KegiatanAbsensi::firstOrFail();
        $this->assertSame(StatusKegiatan::Menunggu, $c->status);
        $this->assertNull($c->pengganti_id);
        $this->assertSame('Rumah keluarga terkena banjir', $c->alasan);
    }

    public function test_kategori_izin_tidak_dikenal_ditolak(): void
    {
        $this->kirim('izin', $this->masukApi(), ['kunci' => $this->kunci(), 'kategori' => 'cuti'])
            ->assertStatus(422)->assertJsonValidationErrors('kategori');
    }

    public function test_karyawan_melihat_hasil_keputusan_di_ringkasan(): void
    {
        $k = $this->karyawan();
        $pengganti = $this->karyawan();
        $header = $this->masukApi($k);
        $hrd = $this->hrd();

        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'lainnya', 'alasan' => 'Keperluan keluarga mendadak'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertOk()
            ->assertJsonPath('izin_lainnya.0.status', 'menunggu');

        $izin = KegiatanAbsensi::firstOrFail();
        $this->actingAs($hrd)->post(route('hrd.izin.setujui', $izin), ['pengganti_id' => $pengganti->id])->assertSessionHas('success');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertOk()
            ->assertJsonPath('izin_lainnya.0.status', 'disetujui')
            ->assertJsonPath('izin_lainnya.0.pengganti', $pengganti->nama);

        // Pengajuan kedua ditolak
        $this->app['auth']->forgetGuards();
        $this->kirim('izin', $header, ['kunci' => $this->kunci(), 'kategori' => 'lainnya', 'alasan' => 'Urusan pribadi lain'])->assertCreated();
        $kedua = KegiatanAbsensi::latest('id')->firstOrFail();
        $this->actingAs($hrd)->post(route('hrd.izin.tolak', $kedua), ['alasan_penolakan' => 'Belum ada pengganti'])->assertSessionHas('success');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertOk()
            ->assertJsonPath('izin_lainnya.0.status', 'ditolak')
            ->assertJsonPath('izin_lainnya.0.alasan_penolakan', 'Belum ada pengganti');
    }

    /* ----------------------------------------------------- ringkasan/riwayat */

    public function test_ringkasan_dan_riwayat_hanya_milik_karyawan_yang_login(): void
    {
        $saya = $this->karyawan();
        $lain = $this->karyawan();
        $header = $this->masukApi($saya);

        $this->kirim('standby', $header, ['kunci' => $this->kunci()])->assertCreated();
        $this->kirim('standby', $this->masukApi($lain), ['kunci' => $this->kunci()])->assertCreated();
        Absensi::create(['karyawan_id' => $saya->id, 'tanggal' => '2026-11-03', 'status' => 'hadir', 'jam_masuk' => '07:50:00']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertOk()
            ->assertJsonCount(1, 'hari_ini')->assertJsonPath('tanggal', '2026-11-14');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas?bulan=2026-11', $header)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('ringkasan.standby', 1)
            ->assertJsonCount(1, 'absensi_lama'); // riwayat lama tetap terbaca

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas?bulan=bukan-bulan', $header)->assertStatus(422);
    }
}
