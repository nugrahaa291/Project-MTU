<?php

namespace Tests\Feature;

use App\Models\Jabatan;
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

    private function loginApi(string $nip, string $password)
    {
        return $this->postJson('/api/login', ['nip' => $nip, 'password' => $password, 'device_name' => 'hp-uji']);
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

        $this->post('/login', ['email' => $karyawan->user->email, 'password' => self::PASSWORD_KARYAWAN])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Bila sudah punya sesi web, pintu masuk "dashboard" mengarahkan karyawan langsung ke /absen
        $this->actingAs($karyawan->user)->get('/dashboard')->assertRedirect(route('pwa'));
    }

    public function test_login_karyawan_dengan_nip_dan_password(): void
    {
        $karyawan = $this->karyawan();

        $this->loginApi($karyawan->nip, self::PASSWORD_KARYAWAN)->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'must_change_password', 'karyawan' => ['nama', 'nip', 'jabatan']])
            ->assertJsonPath('karyawan.nip', $karyawan->nip)
            ->assertJsonPath('must_change_password', false);

        // Email dan password lama tidak lagi dipakai untuk login karyawan
        $this->postJson('/api/login', ['email' => $karyawan->user->email, 'password' => self::PASSWORD_KARYAWAN, 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('nip');
    }

    public function test_password_salah_nip_tidak_ada_dan_akun_nonaktif_ditolak_dengan_pesan_sama(): void
    {
        $karyawan = $this->karyawan();
        $nonaktif = $this->karyawan();
        app(KaryawanService::class)->setAktif($nonaktif, false);

        $a = $this->loginApi($karyawan->nip, '111222')->assertUnauthorized()->json('message');
        $b = $this->loginApi('NIP-TIDAK-ADA', '482913')->assertUnauthorized()->json('message');
        $c = $this->loginApi($nonaktif->nip, self::PASSWORD_KARYAWAN)->assertUnauthorized()->json('message');

        $this->assertSame($a, $b);
        $this->assertSame($a, $c); // tidak membocorkan NIP mana yang terdaftar / aktif
    }

    public function test_percobaan_password_dibatasi(): void
    {
        $karyawan = $this->karyawan();

        for ($i = 0; $i < 5; $i++) {
            $this->loginApi($karyawan->nip, '000'.$i.'99')->assertUnauthorized();
        }

        // Percobaan ke-6 diblokir, walaupun Password-nya benar
        $this->loginApi($karyawan->nip, self::PASSWORD_KARYAWAN)->assertStatus(429)
            ->assertJsonPath('kode', 'terlalu_banyak_percobaan');
    }

    public function test_password_sementara_wajib_didaftarkan_dengan_aturan_password(): void
    {
        $karyawan = $this->karyawan(wajibGantiPassword: true);
        $token = $this->loginApi($karyawan->nip, self::PASSWORD_KARYAWAN)->assertOk()->assertJsonPath('must_change_password', true)->json('token');
        $header = ['Authorization' => "Bearer $token"];
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/aktivitas/ringkasan', $header)->assertForbidden()->assertJsonPath('kode', 'wajib_ganti_password');

        // Tolak password pendek, tanpa huruf atau angka, dan pengulangan password lama
        foreach (['12345', '1234567', 'abcdef', 'aaaaaaaa', '123456789', self::PASSWORD_KARYAWAN] as $buruk) {
            $this->app['auth']->forgetGuards();
            $this->putJson('/api/password', ['current_password' => self::PASSWORD_KARYAWAN, 'password' => $buruk, 'password_confirmation' => $buruk], $header)
                ->assertStatus(422)->assertJsonValidationErrors('password');
        }
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/password', ['current_password' => '999000', 'password' => 'Rahasia739418', 'password_confirmation' => 'Rahasia739418'], $header)
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/password', ['current_password' => self::PASSWORD_KARYAWAN, 'password' => 'Rahasia739418', 'password_confirmation' => 'Rahasia739419'], $header)
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/password', ['current_password' => self::PASSWORD_KARYAWAN, 'password' => 'Rahasia739418', 'password_confirmation' => 'Rahasia739418'], $header)->assertOk();
        $this->assertFalse($karyawan->user->fresh()->must_change_password);

        // Login berikutnya memakai Password baru; Password lama tidak berlaku
        $this->loginApi($karyawan->nip, self::PASSWORD_KARYAWAN)->assertUnauthorized();
        $this->loginApi($karyawan->nip, 'Rahasia739418')->assertOk()->assertJsonPath('must_change_password', false);
    }

    public function test_karyawan_nonaktif_terputus_dari_api_seketika(): void
    {
        $karyawan = $this->karyawan();
        $token = $this->loginApi($karyawan->nip, self::PASSWORD_KARYAWAN)->json('token');
        $header = ['Authorization' => "Bearer $token"];
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertOk();

        app(KaryawanService::class)->setAktif($karyawan, false);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertUnauthorized();
    }

    public function test_reset_password_oleh_admin_membuat_password_baru_dan_mencabut_token(): void
    {
        $admin = $this->admin();
        $hrd = $this->hrd();
        $karyawan = $this->karyawan();
        $karyawan->user->createToken('hp-lama');

        // HRD ditolak saat mereset password
        $this->actingAs($hrd)->post(route('hrd.karyawan.reset-password', $karyawan))->assertForbidden();

        // Admin berhasil mereset password
        $this->actingAs($admin)->post(route('admin.karyawan.reset-password', $karyawan))
            ->assertRedirect(route('admin.karyawan.show', $karyawan))->assertSessionHas('password_sementara');

        $user = $karyawan->user->fresh();
        $password = session('password_sementara');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $password);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse(Hash::check(self::PASSWORD_KARYAWAN, $user->password));
    }

    public function test_admin_bisa_menambah_karyawan_manual_dan_hrd_dilarang(): void
    {
        $admin = $this->admin();
        $hrd = $this->hrd();
        $jabatan = Jabatan::create(['nama' => 'Driver', 'aktif' => true]);
        $dasar = ['jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2026-10-01', 'no_hp' => '+6281234500001'];

        // HRD dilarang menambah karyawan (View Only)
        $this->actingAs($hrd)->post(route('hrd.karyawan.store'), [
            'nama' => 'Agus Salim', 'email' => 'agus@example.com', 'nik' => '3215030303900001',
            'no_hp' => '+6281234500001', 'jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2026-10-01',
        ])->assertForbidden();

        // Admin berhasil menambah karyawan
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            ...$dasar, 'nama' => 'Agus Salim', 'email' => 'agus@example.com', 'nik' => '3215030303900001', 'nip' => 'MTU-0123',
        ])->assertSessionHas('password_sementara');
        $this->assertDatabaseHas('karyawan', ['nik' => '3215030303900001', 'nip' => 'MTU-0123', 'no_hp' => '081234500001']);
        $this->assertTrue(User::where('email', 'agus@example.com')->firstOrFail()->must_change_password);

        // NIP kosong => dibuat otomatis
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            ...$dasar, 'nama' => 'Tono', 'email' => 'tono@example.com', 'nik' => '3215030303900002', 'nip' => '',
        ])->assertSessionHas('password_sementara');
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
