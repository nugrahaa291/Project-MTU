<?php

namespace Tests\Feature;

use App\Models\Lamaran;
use App\Models\Pelamar;
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
