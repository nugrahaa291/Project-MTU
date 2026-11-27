<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\User;
use App\Services\KaryawanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AkunTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private function loginApi(string $nip, string $pin)
    {
        return $this->postJson('/api/login', ['nip' => $nip, 'pin' => $pin, 'device_name' => 'hp-uji']);
    }

    public function test_hrd_login_web_diarahkan_ke_dashboard_hrd(): void
    {
        $hrd = $this->hrd();

        $this->post('/login', ['email' => $hrd->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($hrd);
        $this->get('/dashboard')->assertRedirect(route('hrd.dashboard'));
    }

    public function test_karyawan_tidak_bisa_login_di_panel_web_dan_diarahkan_ke_halaman_absen(): void
    {
        $karyawan = $this->karyawan();

        $this->post('/login', ['email' => $karyawan->user->email, 'password' => self::PIN_KARYAWAN])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Bila sudah punya sesi web, pintu masuk "dashboard" mengarahkan karyawan langsung ke /absen
        $this->actingAs($karyawan->user)->get('/dashboard')->assertRedirect(route('pwa'));
    }

    public function test_login_karyawan_dengan_nip_dan_pin(): void
    {
        $karyawan = $this->karyawan();

        $this->loginApi($karyawan->nip, self::PIN_KARYAWAN)->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'must_change_pin', 'karyawan' => ['nama', 'nip', 'jabatan']])
            ->assertJsonPath('karyawan.nip', $karyawan->nip)
            ->assertJsonPath('must_change_pin', false);

        // Email dan password lama tidak lagi dipakai untuk login karyawan
        $this->postJson('/api/login', ['email' => $karyawan->user->email, 'password' => self::PIN_KARYAWAN, 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['nip', 'pin']);
    }

    public function test_pin_salah_nip_tidak_ada_dan_akun_nonaktif_ditolak_dengan_pesan_sama(): void
    {
        $karyawan = $this->karyawan();
        $nonaktif = $this->karyawan();
        app(KaryawanService::class)->setAktif($nonaktif, false);

        $a = $this->loginApi($karyawan->nip, '111222')->assertUnauthorized()->json('message');
        $b = $this->loginApi('NIP-TIDAK-ADA', '482913')->assertUnauthorized()->json('message');
        $c = $this->loginApi($nonaktif->nip, self::PIN_KARYAWAN)->assertUnauthorized()->json('message');

        $this->assertSame($a, $b);
        $this->assertSame($a, $c); // tidak membocorkan NIP mana yang terdaftar / aktif
    }

    public function test_percobaan_pin_dibatasi(): void
    {
        $karyawan = $this->karyawan();

        for ($i = 0; $i < 5; $i++) {
            $this->loginApi($karyawan->nip, '000'.$i.'99')->assertUnauthorized();
        }

        // Percobaan ke-6 diblokir, walaupun PIN-nya benar
        $this->loginApi($karyawan->nip, self::PIN_KARYAWAN)->assertStatus(429)
            ->assertJsonPath('kode', 'terlalu_banyak_percobaan');
    }

    public function test_pin_sementara_wajib_didaftarkan_dengan_aturan_pin(): void
    {
        $karyawan = $this->karyawan(wajibGantiPassword: true);
        $token = $this->loginApi($karyawan->nip, self::PIN_KARYAWAN)->assertOk()->assertJsonPath('must_change_pin', true)->json('token');
        $header = ['Authorization' => "Bearer $token"];
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/aktivitas/ringkasan', $header)->assertForbidden()->assertJsonPath('kode', 'wajib_ganti_pin');

        // PIN yang ditolak: bukan 6 digit, angka berulang, berurutan, sama dengan PIN lama
        foreach (['12345', '1234567', 'abcdef', '111111', '123456', '654321', '121212', self::PIN_KARYAWAN] as $buruk) {
            $this->app['auth']->forgetGuards();
            $this->putJson('/api/pin', ['pin_lama' => self::PIN_KARYAWAN, 'pin' => $buruk, 'pin_confirmation' => $buruk], $header)
                ->assertStatus(422)->assertJsonValidationErrors('pin');
        }
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/pin', ['pin_lama' => '999000', 'pin' => '739418', 'pin_confirmation' => '739418'], $header)
            ->assertStatus(422)->assertJsonValidationErrors('pin_lama');
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/pin', ['pin_lama' => self::PIN_KARYAWAN, 'pin' => '739418', 'pin_confirmation' => '739419'], $header)
            ->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/pin', ['pin_lama' => self::PIN_KARYAWAN, 'pin' => '739418', 'pin_confirmation' => '739418'], $header)->assertOk();
        $this->assertFalse($karyawan->user->fresh()->must_change_password);

        // Login berikutnya memakai PIN baru; PIN lama tidak berlaku
        $this->loginApi($karyawan->nip, self::PIN_KARYAWAN)->assertUnauthorized();
        $this->loginApi($karyawan->nip, '739418')->assertOk()->assertJsonPath('must_change_pin', false);
    }

    public function test_karyawan_nonaktif_terputus_dari_api_seketika(): void
    {
        $karyawan = $this->karyawan();
        $token = $this->loginApi($karyawan->nip, self::PIN_KARYAWAN)->json('token');
        $header = ['Authorization' => "Bearer $token"];
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertOk();

        app(KaryawanService::class)->setAktif($karyawan, false);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertUnauthorized();
    }

    public function test_reset_pin_oleh_admin_membuat_pin_baru_dan_mencabut_token(): void
    {
        $admin = $this->admin();
        $hrd = $this->hrd();
        $karyawan = $this->karyawan();
        $karyawan->user->createToken('hp-lama');

        // HRD ditolak saat mereset password
        $this->actingAs($hrd)->post(route('hrd.karyawan.reset-password', $karyawan))->assertForbidden();

        // Admin berhasil mereset password
        $this->actingAs($admin)->post(route('admin.karyawan.reset-password', $karyawan))
            ->assertRedirect(route('admin.karyawan.show', $karyawan))->assertSessionHas('pin_sementara');

        $user = $karyawan->user->fresh();
        $pin = session('pin_sementara');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check($pin, $user->password));
        $this->assertFalse(Hash::check(self::PIN_KARYAWAN, $user->password));
    }

    public function test_admin_bisa_menambah_karyawan_manual_dan_hrd_dilarang(): void
    {
        $admin = $this->admin();
        $hrd = $this->hrd();
        $jabatan = \App\Models\Jabatan::create(['nama' => 'Driver', 'aktif' => true]);
        $dasar = ['jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2026-10-01', 'no_hp' => '+6281234500001'];

        // HRD dilarang menambah karyawan (View Only)
        $this->actingAs($hrd)->post(route('hrd.karyawan.store'), [
            'nama' => 'Agus Salim', 'email' => 'agus@example.com', 'nik' => '3215030303900001',
            'no_hp' => '+6281234500001', 'jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2026-10-01',
        ])->assertForbidden();

        // Admin berhasil menambah karyawan
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            ...$dasar, 'nama' => 'Agus Salim', 'email' => 'agus@example.com', 'nik' => '3215030303900001', 'nip' => 'MTU-0123',
        ])->assertSessionHas('pin_sementara');
        $this->assertDatabaseHas('karyawan', ['nik' => '3215030303900001', 'nip' => 'MTU-0123', 'no_hp' => '081234500001']);
        $this->assertTrue(User::where('email', 'agus@example.com')->firstOrFail()->must_change_password);

        // NIP kosong => dibuat otomatis
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            ...$dasar, 'nama' => 'Tono', 'email' => 'tono@example.com', 'nik' => '3215030303900002', 'nip' => '',
        ])->assertSessionHas('pin_sementara');
        $this->assertNotEmpty(Karyawan::where('nik', '3215030303900002')->value('nip'));

        // NIK ganda dan NIP ganda ditolak
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            ...$dasar, 'nama' => 'Dua', 'email' => 'dua@example.com', 'nik' => '3215030303900001',
        ])->assertSessionHasErrors('nik');
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            ...$dasar, 'nama' => 'Tiga', 'email' => 'tiga@example.com', 'nik' => '3215030303900003', 'nip' => 'MTU-0123',
        ])->assertSessionHasErrors('nip');
    }
}
