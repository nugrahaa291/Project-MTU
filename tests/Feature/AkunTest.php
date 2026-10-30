<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\KaryawanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AkunTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_login_berhasil_diarahkan_sesuai_peran(): void
    {
        $hrd = $this->hrd();
        $karyawan = $this->karyawan();

        $this->post('/login', ['email' => $hrd->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($hrd);
        $this->get('/dashboard')->assertRedirect(route('hrd.dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => $karyawan->user->email, 'password' => 'RahasiaBaru1']);
        $this->get('/dashboard')->assertRedirect(route('karyawan.beranda'));
        $this->get(route('karyawan.beranda'))->assertOk()->assertSee($karyawan->nama);
    }

    public function test_password_salah_dan_akun_nonaktif_tidak_bisa_login(): void
    {
        $karyawan = $this->karyawan();

        $this->post('/login', ['email' => $karyawan->user->email, 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();

        app(KaryawanService::class)->setAktif($karyawan, false);

        $this->post('/login', ['email' => $karyawan->user->email, 'password' => 'RahasiaBaru1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sesi_yang_sudah_berjalan_terputus_saat_akun_dinonaktifkan(): void
    {
        $karyawan = $this->karyawan();
        $this->actingAs($karyawan->user)->get(route('karyawan.beranda'))->assertOk();

        app(KaryawanService::class)->setAktif($karyawan, false);

        $this->actingAs($karyawan->user->fresh())->get(route('karyawan.beranda'))->assertRedirect('/login');
    }

    public function test_password_sementara_wajib_diganti_sebelum_memakai_sistem(): void
    {
        $karyawan = $this->karyawan(wajibGantiPassword: true);
        $this->actingAs($karyawan->user);

        $this->get(route('karyawan.beranda'))->assertRedirect(route('password.edit'));
        $this->get(route('password.edit'))->assertOk();

        // Password baru terlalu lemah / sama dengan lama ditolak
        $this->put(route('password.update'), ['password_lama' => 'RahasiaBaru1', 'password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertSessionHasErrors('password');
        $this->put(route('password.update'), ['password_lama' => 'salah', 'password' => 'PasswordBaru9', 'password_confirmation' => 'PasswordBaru9'])
            ->assertSessionHasErrors('password_lama');

        $this->put(route('password.update'), ['password_lama' => 'RahasiaBaru1', 'password' => 'PasswordBaru9', 'password_confirmation' => 'PasswordBaru9'])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($karyawan->user->fresh()->must_change_password);
        $this->get(route('karyawan.beranda'))->assertOk();
    }

    public function test_reset_password_membuat_password_baru_dan_mencabut_token(): void
    {
        $hrd = $this->hrd();
        $karyawan = $this->karyawan();
        $karyawan->user->createToken('hp-lama');

        $this->actingAs($hrd)->post(route('hrd.karyawan.reset-password', $karyawan))
            ->assertRedirect(route('hrd.karyawan.show', $karyawan))->assertSessionHas('password_sementara');

        $user = $karyawan->user->fresh();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(\Hash::check(session('password_sementara'), $user->password));
        $this->assertFalse(\Hash::check('RahasiaBaru1', $user->password));
    }

    public function test_hrd_bisa_menambah_karyawan_manual(): void
    {
        $hrd = $this->hrd();
        $jabatan = \App\Models\Jabatan::create(['nama' => 'Driver', 'aktif' => true]);

        $this->actingAs($hrd)->post(route('hrd.karyawan.store'), [
            'nama' => 'Agus Salim', 'email' => 'agus@example.com', 'nik' => '3215030303900001',
            'no_hp' => '+6281234500001', 'jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2026-10-01',
        ])->assertSessionHas('password_sementara');

        $this->assertDatabaseHas('karyawan', ['nik' => '3215030303900001', 'no_hp' => '081234500001']);
        $this->assertTrue(User::where('email', 'agus@example.com')->firstOrFail()->must_change_password);

        // NIK ganda ditolak
        $this->actingAs($hrd)->post(route('hrd.karyawan.store'), [
            'nama' => 'Agus Dua', 'email' => 'agus2@example.com', 'nik' => '3215030303900001',
            'no_hp' => '081234500002', 'jabatan_id' => $jabatan->id, 'tanggal_masuk' => '2026-10-01',
        ])->assertSessionHasErrors('nik');
    }
}
