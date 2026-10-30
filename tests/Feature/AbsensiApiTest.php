<?php

namespace Tests\Feature;

use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\KalenderKerja;
use App\Models\Karyawan;
use App\Services\AbsensiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AbsensiApiTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    // Rabu, 28 Oktober 2026 (hari kerja), WIB
    private const RABU = '2026-10-28';

    // Koordinat kantor (config/absensi.php) dan titik ~1,1 km darinya
    private const DEKAT = ['latitude' => -6.3215, 'longitude' => 107.3376];
    private const JAUH = ['latitude' => -6.3315, 'longitude' => 107.3376];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->jam('08:00');
    }

    private function jam(string $jam, string $tanggal = self::RABU): void
    {
        $this->travelTo(CarbonImmutable::parse("$tanggal $jam:00", 'Asia/Jakarta'));
    }

    /** Login lewat API dan kembalikan header Authorization. */
    private function masukApi(Karyawan $k, string $perangkat = 'hp-test'): array
    {
        $this->app['auth']->forgetGuards(); // satu pengujian = satu aplikasi; lupakan pengguna dari permintaan sebelumnya
        $token = $this->postJson('/api/login', [
            'email' => $k->user->email, 'password' => 'RahasiaBaru1', 'device_name' => $perangkat,
        ])->assertOk()->json('token');

        return ['Authorization' => "Bearer $token"];
    }

    private function absen(string $jenis, array $header, array $lokasi = self::DEKAT, array $tambahan = [])
    {
        $this->app['auth']->forgetGuards();
        return $this->postJson("/api/absensi/$jenis", [...$lokasi, 'foto' => $this->png('selfie.png'), ...$tambahan], $header);
    }

    public function test_login_api(): void
    {
        $k = $this->karyawan();

        $this->postJson('/api/login', ['email' => $k->user->email, 'password' => 'RahasiaBaru1', 'device_name' => 'Pixel'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'must_change_password', 'karyawan' => ['nama', 'nip', 'jabatan']]);

        $this->postJson('/api/login', ['email' => $k->user->email, 'password' => 'salah', 'device_name' => 'Pixel'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'tidakada@example.com', 'password' => 'x', 'device_name' => 'Pixel'])->assertUnauthorized();

        // HRD tidak bisa memakai aplikasi absensi
        $hrd = $this->hrd();
        $this->postJson('/api/login', ['email' => $hrd->email, 'password' => 'password', 'device_name' => 'Pixel'])->assertForbidden();
    }

    public function test_tanpa_token_ditolak_dan_logout_mencabut_token(): void
    {
        $this->getJson('/api/absensi/hari-ini')->assertUnauthorized();

        $k = $this->karyawan();
        $header = $this->masukApi($k);
        $this->getJson('/api/me', $header)->assertOk();

        $this->postJson('/api/logout', [], $header)->assertOk();
        $this->assertSame(0, $k->user->tokens()->count());
    }

    public function test_absen_masuk_tepat_waktu(): void
    {
        $k = $this->karyawan();
        $this->jam('07:55');

        $this->absen('masuk', $this->masukApi($k))
            ->assertCreated()
            ->assertJsonPath('absensi.status', 'hadir')
            ->assertJsonPath('absensi.jam_masuk', '07:55:00')
            ->assertJsonPath('absensi.jarak_masuk_meter', 0);

        $absensi = Absensi::firstOrFail();
        $this->assertSame(self::RABU, $absensi->tanggal->toDateString());
        Storage::disk('local')->assertExists($absensi->foto_masuk);
    }

    public function test_toleransi_dan_terlambat(): void
    {
        $a = $this->karyawan();
        $b = $this->karyawan();

        // 08:15 = batas toleransi (15 menit) -> masih hadir
        $this->jam('08:15');
        $this->absen('masuk', $this->masukApi($a))->assertCreated()->assertJsonPath('absensi.status', 'hadir');

        // 08:16 -> terlambat, dihitung 16 menit dari jam masuk
        $this->jam('08:16');
        $this->absen('masuk', $this->masukApi($b))
            ->assertCreated()
            ->assertJsonPath('absensi.status', 'terlambat')
            ->assertJsonPath('absensi.menit_terlambat', 16);
    }

    public function test_di_luar_radius_ditolak(): void
    {
        $header = $this->masukApi($this->karyawan());

        $this->absen('masuk', $header, self::JAUH)
            ->assertStatus(422)->assertJsonPath('kode', 'di_luar_radius');

        $this->assertSame(0, Absensi::count());
    }

    public function test_lokasi_palsu_dan_akurasi_buruk_ditolak(): void
    {
        $header = $this->masukApi($this->karyawan());

        $this->absen('masuk', $header, self::DEKAT, ['lokasi_palsu' => true])->assertStatus(422)->assertJsonPath('kode', 'lokasi_palsu');
        $this->absen('masuk', $header, self::DEKAT, ['akurasi' => 500])->assertStatus(422)->assertJsonPath('kode', 'akurasi_rendah');
        $this->absen('masuk', $header, self::DEKAT, ['akurasi' => 12])->assertCreated();
    }

    public function test_tidak_bisa_absen_masuk_dua_kali(): void
    {
        $header = $this->masukApi($this->karyawan());

        $this->absen('masuk', $header)->assertCreated();
        $this->absen('masuk', $header)->assertStatus(409)->assertJsonPath('kode', 'sudah_absen');

        $this->assertSame(1, Absensi::count());
    }

    public function test_absen_pulang(): void
    {
        $header = $this->masukApi($this->karyawan());

        // Pulang sebelum masuk ditolak
        $this->absen('pulang', $header)->assertStatus(409)->assertJsonPath('kode', 'belum_masuk');

        $this->absen('masuk', $header)->assertCreated();
        $this->jam('17:05');
        $this->absen('pulang', $header)->assertOk()->assertJsonPath('absensi.jam_pulang', '17:05:00');
        $this->absen('pulang', $header)->assertStatus(409)->assertJsonPath('kode', 'sudah_pulang');

        // Pulang juga wajib di dalam radius
        $lain = $this->masukApi($this->karyawan(), 'hp-lain');
        $this->jam('08:00');
        $this->absen('masuk', $lain)->assertCreated();
        $this->absen('pulang', $lain, self::JAUH)->assertStatus(422)->assertJsonPath('kode', 'di_luar_radius');
    }

    public function test_akhir_pekan_dan_hari_libur_tidak_bisa_absen(): void
    {
        $header = $this->masukApi($this->karyawan());

        $this->jam('08:00', '2026-10-31'); // Sabtu
        $this->absen('masuk', $header)->assertStatus(422)->assertJsonPath('kode', 'bukan_hari_kerja');

        KalenderKerja::create(['tanggal' => '2026-11-02', 'nama' => 'Cuti bersama']); // Senin
        $this->jam('08:00', '2026-11-02');
        $this->absen('masuk', $header)->assertStatus(422)->assertJsonPath('kode', 'bukan_hari_kerja');
    }

    public function test_hari_ini_menentukan_tombol(): void
    {
        $header = $this->masukApi($this->karyawan());

        $this->getJson('/api/absensi/hari-ini', $header)->assertOk()
            ->assertJsonPath('bisa_masuk', true)->assertJsonPath('bisa_pulang', false)->assertJsonPath('tanggal', self::RABU)
            ->assertJsonPath('kantor.radius_meter', 100);

        $this->absen('masuk', $header);
        $this->getJson('/api/absensi/hari-ini', $header)
            ->assertJsonPath('bisa_masuk', false)->assertJsonPath('bisa_pulang', true);

        $this->absen('pulang', $header);
        $this->getJson('/api/absensi/hari-ini', $header)->assertJsonPath('bisa_pulang', false);
    }

    public function test_validasi_input_absen(): void
    {
        $header = $this->masukApi($this->karyawan());

        $this->postJson('/api/absensi/masuk', ['latitude' => 999, 'longitude' => 'abc'], $header)
            ->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude', 'foto']);

        $this->postJson('/api/absensi/masuk', [...self::DEKAT, 'foto' => $this->pdf('bukan-foto.pdf')], $header)
            ->assertStatus(422)->assertJsonValidationErrors('foto');
    }

    public function test_karyawan_hanya_melihat_riwayat_miliknya(): void
    {
        $saya = $this->karyawan();
        $orangLain = $this->karyawan();
        Absensi::create(['karyawan_id' => $orangLain->id, 'tanggal' => self::RABU, 'status' => StatusAbsensi::Hadir, 'jam_masuk' => '07:50:00']);

        $header = $this->masukApi($saya);
        $this->absen('masuk', $header)->assertCreated();

        $this->getJson('/api/absensi?bulan=2026-10', $header)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('ringkasan.hadir', 1);
        $this->getJson('/api/absensi?bulan=oktober', $header)->assertStatus(422);
    }

    public function test_izin_dari_hrd_menghalangi_absen_masuk(): void
    {
        $k = $this->karyawan();
        $this->actingAs($this->hrd())->post(route('hrd.absensi.store'), [
            'karyawan_id' => $k->id, 'tanggal' => self::RABU, 'status' => 'sakit', 'keterangan' => 'Surat dokter',
        ])->assertSessionHas('success');
        $this->app['auth']->forgetGuards();

        $this->absen('masuk', $this->masukApi($k))->assertStatus(409);
    }

    public function test_password_sementara_memblokir_absensi_sampai_diganti(): void
    {
        $k = $this->karyawan(wajibGantiPassword: true);
        $header = $this->masukApi($k);

        $this->getJson('/api/absensi/hari-ini', $header)->assertForbidden()->assertJsonPath('kode', 'wajib_ganti_password');
        $this->absen('masuk', $header)->assertForbidden();

        $this->putJson('/api/password', [
            'password_lama' => 'RahasiaBaru1', 'password' => 'PasswordBaru9', 'password_confirmation' => 'PasswordBaru9',
        ], $header)->assertOk();

        $this->getJson('/api/absensi/hari-ini', $header)->assertOk();
    }

    public function test_karyawan_nonaktif_langsung_terputus_dari_api(): void
    {
        $k = $this->karyawan();
        $header = $this->masukApi($k);
        $this->getJson('/api/me', $header)->assertOk();

        app(\App\Services\KaryawanService::class)->setAktif($k, false);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me', $header)->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $k->user->email, 'password' => 'RahasiaBaru1', 'device_name' => 'x'])->assertUnauthorized();
    }

    public function test_token_karyawan_tidak_bisa_masuk_area_hrd_dan_hrd_tidak_bisa_pakai_api_absensi(): void
    {
        $k = $this->karyawan();
        $this->masukApi($k);
        // Area HRD berbasis sesi web: tanpa login web tetap diarahkan ke login
        $this->get('/hrd')->assertRedirect('/login');

        \Laravel\Sanctum\Sanctum::actingAs($this->hrd());
        $this->getJson('/api/absensi/hari-ini')->assertForbidden();
    }

    public function test_perintah_tandai_alpa(): void
    {
        $hadir = $this->karyawan();
        $absen = $this->karyawan();
        $baru = $this->karyawan(['tanggal_masuk' => '2026-10-29']);       // belum mulai bekerja
        $nonaktif = $this->karyawan();
        app(\App\Services\KaryawanService::class)->setAktif($nonaktif, false);
        Absensi::create(['karyawan_id' => $hadir->id, 'tanggal' => self::RABU, 'status' => StatusAbsensi::Hadir, 'jam_masuk' => '08:00:00']);

        $this->artisan('absensi:tandai-alpa', ['tanggal' => self::RABU])->assertSuccessful();

        $this->assertSame(StatusAbsensi::Alpa, Absensi::where('karyawan_id', $absen->id)->firstOrFail()->status);
        $this->assertSame(1, Absensi::where('karyawan_id', $hadir->id)->count());   // tidak ditimpa
        $this->assertSame(0, Absensi::where('karyawan_id', $baru->id)->count());
        $this->assertSame(0, Absensi::where('karyawan_id', $nonaktif->id)->count());

        // Dijalankan ulang tidak menggandakan data
        $this->artisan('absensi:tandai-alpa', ['tanggal' => self::RABU])->assertSuccessful();
        $this->assertSame(2, Absensi::count());

        // Akhir pekan tidak ada alpa
        $this->artisan('absensi:tandai-alpa', ['tanggal' => '2026-10-31'])->assertSuccessful();
        $this->assertSame(2, Absensi::count());
    }

    public function test_hrd_bisa_melihat_foto_absensi_tetapi_karyawan_lain_tidak(): void
    {
        $k = $this->karyawan();
        $this->absen('masuk', $this->masukApi($k))->assertCreated();
        $absensi = Absensi::firstOrFail();

        $this->actingAs($this->hrd())->get(route('hrd.absensi.foto', [$absensi, 'masuk']))->assertOk();
        $this->actingAs($this->hrd())->get(route('hrd.absensi.foto', [$absensi, 'pulang']))->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->karyawan()->user)->get(route('hrd.absensi.foto', [$absensi, 'masuk']))->assertForbidden();
    }
}
