<?php

namespace Tests\Feature;

use App\Models\Lamaran;
use App\Models\Pelamar;
use App\Services\KaryawanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AkunPelamarTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_daftar_dari_lowongan_dan_riwayat_setelah_login_ulang(): void
    {
        Storage::fake('local');
        $lowongan = $this->lowongan();
        $url = route('lamaran.create', $lowongan);
        $this->get($url)->assertRedirect(route('pelamar.login'));
        $this->postJson($url, [])->assertUnauthorized();
        $data = ['nama' => 'Budi', 'email' => 'budi@example.test', 'password' => 'Rahasia123'];
        $this->post(route('pelamar.daftar'), [...$data, 'password_confirmation' => $data['password']])->assertRedirect($url);
        $this->post($url, $this->dataLamaran())->assertRedirect();
        $lamaran = Lamaran::sole();
        $this->get(route('pelamar.riwayat'))->assertSee($lamaran->kode);
        $this->post(route('pelamar.keluar'));
        $this->post(route('pelamar.login'), $data)->assertRedirect(route('pelamar.riwayat'));
        $this->get(route('pelamar.riwayat'))->assertSee($lamaran->kode);
        $this->assertGuest('web');

        $this->actingAs(Pelamar::create(['email' => 'lain@example.test', 'nama' => 'Lain']), 'pelamar');
        $this->get(route('lamaran.berhasil', $lamaran))->assertNotFound();
        $this->get(route('pelamar.riwayat'))->assertDontSee($lamaran->kode);
        $this->post(route('status.cek'), ['kode' => $lamaran->kode, 'no_hp' => $lamaran->no_hp])->assertSessionHasErrors('kode');
        $this->post(route('pelamar.tautkan'), ['kode' => $lamaran->kode, 'no_hp' => $lamaran->no_hp])->assertSessionHas('error');
    }

    public function test_pin_lama_diganti_sekali_dan_password_baru_bisa_dipakai_di_kedua_portal(): void
    {
        $karyawan = $this->karyawan();
        $user = $karyawan->user;
        $user->update(['password' => '482913']);
        $kredensial = ['nip' => $karyawan->nip, 'password' => 'KerjaBaru123'];
        $lama = ['nip' => $karyawan->nip, 'password' => '482913'];

        $this->post(route('pelamar.login.karyawan'), $lama)->assertSessionHasErrors('nip');
        $this->assertGuest('pelamar');
        $token = $this->postJson('/api/login', [...$lama, 'device_name' => 'HP'])
            ->assertOk()->assertJsonPath('must_change_password', true)->json('token');
        $header = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/aktivitas/ringkasan', $header)->assertForbidden();
        $this->putJson('/api/password', ['current_password' => '482913', 'password' => $kredensial['password'],
            'password_confirmation' => $kredensial['password']], $header)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', [...$lama, 'device_name' => 'HP'])->assertUnauthorized();
        $this->postJson('/api/login', [...$kredensial, 'device_name' => 'HP'])->assertOk()->assertJsonPath('must_change_password', false);

        $this->get(route('pelamar.login', ['karyawan' => 1]))->assertOk()->assertSee('NIP karyawan');
        $this->post(route('pelamar.login.karyawan'), $kredensial)->assertRedirect(route('pelamar.riwayat'));
        $profil = Pelamar::sole();
        $this->assertSame($user->id, $profil->user_id);
        $this->assertNull($profil->password);
        $this->assertGuest('web');
        $this->get(route('pelamar.riwayat'))->assertOk();
        $this->post(route('pelamar.keluar'));
        $this->post(route('pelamar.login'), ['email' => $profil->email, 'password' => $kredensial['password']])->assertRedirect(route('pelamar.riwayat'));
        $this->assertAuthenticatedAs($profil, 'pelamar');

        $sementara = app(KaryawanService::class)->resetPassword($karyawan);
        $this->app['auth']->forgetGuards();
        $this->get(route('pelamar.riwayat'))->assertRedirect(route('pelamar.login'));
        $this->post(route('pelamar.login.karyawan'), $kredensial)->assertSessionHasErrors('nip');
        $this->post(route('pelamar.login.karyawan'), [...$kredensial, 'password' => $sementara])->assertSessionHasErrors('nip');
        $this->assertGuest('pelamar');
    }

    public function test_email_sama_memerlukan_bukti_kedua_akun_dan_menjaga_riwayat(): void
    {
        $karyawan = $this->karyawan();
        $pelamar = Pelamar::create(['nama' => 'Akun awal', 'email' => $karyawan->user->email, 'password' => 'PelamarAwal123']);
        $lamaran = $this->lamaran($this->lowongan(), ['pelamar_id' => $pelamar->id]);
        $pegawai = ['nip' => $karyawan->nip, 'password' => self::PASSWORD_KARYAWAN];
        $akunAwal = ['email' => $pelamar->email, 'password' => 'PelamarAwal123'];
        $this->post(route('pelamar.login.karyawan'), $pegawai)->assertSessionHasErrors('nip');
        $this->assertGuest('pelamar');
        $this->post(route('pelamar.login'), $akunAwal)->assertRedirect(route('pelamar.riwayat'));
        $this->post(route('pelamar.hubungkan.karyawan'), [...$pegawai, 'password' => 'Salah123'])->assertSessionHasErrors('nip');
        $this->assertNull($pelamar->fresh()->user_id);
        $this->post(route('pelamar.hubungkan.karyawan'), $pegawai)->assertRedirect(route('pelamar.riwayat'));
        $this->get(route('pelamar.riwayat'))->assertSee($lamaran->kode);
        $this->post(route('pelamar.keluar'));
        $this->post(route('pelamar.login'), $akunAwal)->assertSessionHasErrors('email');
        $this->post(route('pelamar.login.karyawan'), $pegawai)->assertRedirect(route('pelamar.riwayat'));
        $this->assertAuthenticatedAs($pelamar, 'pelamar');
        $this->assertSame(1, Pelamar::count());
        app(KaryawanService::class)->setAktif($karyawan, false);
        $this->app['auth']->forgetGuards();
        $this->get(route('pelamar.riwayat'))->assertRedirect(route('pelamar.login'));
        $this->post(route('pelamar.login.karyawan'), $pegawai)->assertSessionHasErrors('nip');
        $this->assertGuest('pelamar');
    }

    public function test_password_diubah_di_absen_langsung_berlaku_untuk_profil_google_yang_ditautkan(): void
    {
        $karyawan = $this->karyawan();
        $pelamar = Pelamar::create(['nama' => 'Akun Google', 'email' => 'pribadi@example.test']);
        $pelamar->forceFill(['google_id' => 'google-sub-asli'])->save();
        $pegawai = ['nip' => $karyawan->nip, 'password' => self::PASSWORD_KARYAWAN];
        $this->actingAs($pelamar, 'pelamar')->post(route('pelamar.hubungkan.karyawan'), $pegawai)->assertRedirect(route('pelamar.riwayat'));
        $this->assertSame('google-sub-asli', $pelamar->fresh()->google_id);
        $token = $this->postJson('/api/login', [...$pegawai, 'device_name' => 'HP'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->putJson('/api/password', ['current_password' => self::PASSWORD_KARYAWAN,
            'password' => 'PasswordKedua123', 'password_confirmation' => 'PasswordKedua123'],
            ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get(route('pelamar.riwayat'))->assertRedirect(route('pelamar.login'));
        $this->post(route('pelamar.login'), ['email' => $pelamar->email, 'password' => self::PASSWORD_KARYAWAN])->assertSessionHasErrors('email');
        $this->post(route('pelamar.login'), ['email' => $pelamar->email, 'password' => 'PasswordKedua123'])->assertRedirect(route('pelamar.riwayat'));
        $this->assertAuthenticatedAs($pelamar, 'pelamar');
        $this->assertGuest('web');
    }

    public function test_lamaran_lama_harus_dibuktikan_sebelum_ditautkan(): void
    {
        $lama = $this->lamaran($this->lowongan());
        $pelamar = Pelamar::create(['nama' => 'Pelamar', 'email' => $lama->email]);
        $this->actingAs($pelamar, 'pelamar')->get(route('pelamar.riwayat'))->assertDontSee($lama->kode);
        $this->post(route('pelamar.tautkan'), ['kode' => $lama->kode, 'no_hp' => '000'])->assertSessionHas('error');
        $this->post(route('pelamar.tautkan'), ['kode' => $lama->kode, 'no_hp' => $lama->no_hp])->assertSessionHas('success');
        $this->get(route('pelamar.riwayat'))->assertSee($lama->kode);
    }

    public function test_google_baru_memerlukan_email_terverifikasi_dan_kembali_ke_lowongan(): void
    {
        config(['services.google' => ['client_id' => 'test', 'client_secret' => 'test', 'redirect' => route('pelamar.google.callback')]]);
        $profil = ['sub' => 'sub-baru', 'email' => 'baru@example.test', 'name' => 'Baru'];
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'uji']),
            'openidconnect.googleapis.com/*' => Http::sequence()->push($profil + ['email_verified' => false])
                ->push($profil + ['email_verified' => true]),
        ]);
        $url = route('lamaran.create', $this->lowongan());
        $this->get($url)->assertRedirect(route('pelamar.login'));
        $this->get(route('pelamar.google'));
        $this->get(route('pelamar.google.callback', ['code' => 'uji', 'state' => session('pelamar.google.state')]))->assertSessionHas('error');
        $this->assertGuest('pelamar');
        $this->get(route('pelamar.google'));
        $this->get(route('pelamar.google.callback', ['code' => 'uji', 'state' => session('pelamar.google.state')]))->assertRedirect($url);
        $this->assertAuthenticatedAs(Pelamar::sole(), 'pelamar');
        $this->assertGuest('web');
    }

    public function test_google_memeriksa_state_dan_kepemilikan_akun_sebelum_menautkan(): void
    {
        config(['services.google' => ['client_id' => 'test', 'client_secret' => 'test', 'redirect' => route('pelamar.google.callback')]]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'uji']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'google-uji', 'email' => 'akun@example.test', 'email_verified' => true]),
        ]);
        $pelamar = Pelamar::create(['nama' => 'Akun', 'email' => 'akun@example.test', 'password' => 'Rahasia123']);
        $callback = fn ($state) => $this->get(route('pelamar.google.callback', ['code' => 'uji', 'state' => $state]));
        $start = function () {
            $this->get(route('pelamar.google'))->assertRedirectContains('accounts.google.com');

            return session('pelamar.google.state');
        };
        $start();
        $callback('salah')->assertSessionHas('error');
        Http::assertNothingSent();
        $callback($start())->assertSessionHas('error');
        $this->assertGuest('pelamar');
        $this->actingAs($pelamar, 'pelamar');
        $callback($start())->assertSessionHas('success');
        $this->assertSame('google-uji', $pelamar->fresh()->google_id);
        $this->post(route('pelamar.keluar'));
        $callback($start())->assertRedirect(route('pelamar.riwayat'));
        $this->assertAuthenticatedAs($pelamar, 'pelamar');
    }
}
