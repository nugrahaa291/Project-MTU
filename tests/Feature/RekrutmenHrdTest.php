<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatusLamaran;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class RekrutmenHrdTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_tamu_dan_karyawan_tidak_bisa_membuka_area_hrd(): void
    {
        $lamaran = $this->lamaran($this->lowongan());

        $this->get('/hrd')->assertRedirect('/login');
        $this->post(route('hrd.lamaran.terima', $lamaran))->assertRedirect('/login');
        $this->post(route('hrd.lamaran.tolak', $lamaran))->assertRedirect('/login');

        $karyawan = $this->karyawan();
        $this->actingAs($karyawan->user)->get('/hrd')->assertForbidden();
        $this->actingAs($karyawan->user)->post(route('hrd.lamaran.tolak', $lamaran))->assertForbidden();
        $this->assertSame(StatusLamaran::Diajukan, $lamaran->fresh()->status);
    }

    public function test_alur_lengkap_diajukan_seleksi_diterima_membuat_akun_karyawan(): void
    {
        $hrd = $this->hrd();
        $lowongan = $this->lowongan();
        $lamaran = $this->lamaran($lowongan, ['nama' => 'Dewi Lestari', 'email' => 'dewi@example.com']);

        // Langsung terima dari "diajukan" ditolak: harus lewat seleksi dulu
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $lamaran), [
            'email' => 'dewi@example.com', 'tanggal_masuk' => '2026-11-02',
        ])->assertSessionHas('error');
        $this->assertSame(0, Karyawan::count());

        $this->actingAs($hrd)->post(route('hrd.lamaran.proses', $lamaran))->assertSessionHas('success');
        $this->assertSame(StatusLamaran::Seleksi, $lamaran->fresh()->status);

        $response = $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $lamaran), [
            'email' => 'dewi@example.com', 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Cocok',
        ]);

        $karyawan = Karyawan::with('user')->firstOrFail();
        $response->assertRedirect(route('hrd.karyawan.show', $karyawan))->assertSessionHas('password_sementara');

        $this->assertSame(StatusLamaran::Diterima, $lamaran->fresh()->status);
        $this->assertSame($lamaran->id, $karyawan->lamaran_id);
        $this->assertSame($lowongan->jabatan_id, $karyawan->jabatan_id); // jabatan ikut lowongan
        $this->assertSame('Dewi Lestari', $karyawan->nama);
        $this->assertSame($lamaran->nik, $karyawan->nik);
        $this->assertNotNull($karyawan->nip);

        $user = $karyawan->user;
        $this->assertSame(Role::Karyawan, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('dewi@example.com', $user->email);

        // Password sementara benar-benar bisa dipakai login dan tidak tersimpan polos di database
        $password = session('password_sementara');
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->getRawOriginal('password'));
    }

    public function test_kuota_tidak_bisa_dilampaui(): void
    {
        $hrd = $this->hrd();
        $lowongan = $this->lowongan(['kuota' => 1]);
        $a = $this->lamaran($lowongan, ['status' => 'seleksi', 'email' => 'a@example.com']);
        $b = $this->lamaran($lowongan, ['status' => 'seleksi', 'email' => 'b@example.com']);

        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $a), ['email' => 'a@example.com', 'tanggal_masuk' => '2026-11-02']);
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $b), ['email' => 'b@example.com', 'tanggal_masuk' => '2026-11-02'])
            ->assertSessionHas('error');

        $this->assertSame(1, Karyawan::count());
        $this->assertSame(StatusLamaran::Seleksi, $b->fresh()->status);
        $this->assertSame(0, User::where('email', 'b@example.com')->count());
        $this->assertTrue($lowongan->fresh()->isPenuh());
    }

    public function test_email_yang_sudah_dipakai_ditolak_dan_tidak_ada_data_setengah_jadi(): void
    {
        $hrd = $this->hrd();
        $lamaran = $this->lamaran($this->lowongan(), ['status' => 'seleksi']);

        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $lamaran), [
            'email' => $hrd->email, 'tanggal_masuk' => '2026-11-02',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, Karyawan::count());
        $this->assertSame(StatusLamaran::Seleksi, $lamaran->fresh()->status);
    }

    public function test_status_akhir_tidak_bisa_diubah_lagi(): void
    {
        $hrd = $this->hrd();
        $lowongan = $this->lowongan();
        $ditolak = $this->lamaran($lowongan, ['status' => 'ditolak']);
        $diterima = $this->lamaran($lowongan, ['status' => 'diterima']);

        $this->actingAs($hrd)->post(route('hrd.lamaran.proses', $ditolak))->assertSessionHas('error');
        $this->actingAs($hrd)->post(route('hrd.lamaran.tolak', $diterima))->assertSessionHas('error');
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $ditolak), ['email' => 'x@example.com', 'tanggal_masuk' => '2026-11-02'])
            ->assertSessionHas('error');

        $this->assertSame(StatusLamaran::Ditolak, $ditolak->fresh()->status);
        $this->assertSame(StatusLamaran::Diterima, $diterima->fresh()->status);
    }

    public function test_tolak_menyimpan_catatan(): void
    {
        $lamaran = $this->lamaran($this->lowongan());

        $this->actingAs($this->hrd())->post(route('hrd.lamaran.tolak', $lamaran), ['catatan' => 'Kualifikasi kurang'])
            ->assertSessionHas('success');

        $lamaran->refresh();
        $this->assertSame(StatusLamaran::Ditolak, $lamaran->status);
        $this->assertSame('Kualifikasi kurang', $lamaran->catatan);
        $this->assertNotNull($lamaran->diputuskan_pada);
    }

    public function test_berkas_pelamar_hanya_bisa_diunduh_hrd(): void
    {
        Storage::fake('local');
        $lamaran = $this->lamaran($this->lowongan(), ['cv' => 'lamaran/LMR-TEST/cv.pdf']);
        Storage::disk('local')->put('lamaran/LMR-TEST/cv.pdf', '%PDF-1.4 isi');

        $this->get(route('hrd.lamaran.dokumen', [$lamaran, 'cv']))->assertRedirect('/login');
        $this->actingAs($this->karyawan()->user)->get(route('hrd.lamaran.dokumen', [$lamaran, 'cv']))->assertForbidden();

        $this->actingAs($this->hrd())->get(route('hrd.lamaran.dokumen', [$lamaran, 'cv']))->assertOk();
        $this->actingAs($this->hrd())->get(route('hrd.lamaran.dokumen', [$lamaran, 'ijazah']))->assertNotFound(); // kosong
        $this->actingAs($this->hrd())->get(route('hrd.lamaran.dokumen', [$lamaran, 'password']))->assertNotFound(); // bukan jenis berkas
    }

    public function test_lowongan_berlamaran_tidak_bisa_dihapus_dan_kuota_tidak_boleh_di_bawah_terisi(): void
    {
        $hrd = $this->hrd();
        $lowongan = $this->lowongan(['kuota' => 3]);
        $this->lamaran($lowongan, ['status' => 'diterima']);
        $this->lamaran($lowongan, ['status' => 'diterima']);

        $this->actingAs($hrd)->delete(route('hrd.lowongan.destroy', $lowongan))->assertSessionHas('error');
        $this->assertModelExists($lowongan);

        $this->actingAs($hrd)->put(route('hrd.lowongan.update', $lowongan), [
            'judul' => $lowongan->judul, 'jabatan_id' => $lowongan->jabatan_id, 'lokasi' => 'Karawang', 'kuota' => 1,
            'tanggal_buka' => today()->toDateString(), 'tanggal_tutup' => today()->addDay()->toDateString(), 'status' => 'dibuka',
        ])->assertSessionHasErrors('kuota');
    }

    public function test_halaman_hrd_dapat_dirender(): void
    {
        $hrd = $this->hrd();
        $lowongan = $this->lowongan();
        $lamaran = $this->lamaran($lowongan, ['status' => 'seleksi']);
        $karyawan = $this->karyawan();

        foreach ([
            route('hrd.dashboard'), route('hrd.lowongan.index'), route('hrd.lowongan.create'), route('hrd.lowongan.show', $lowongan),
            route('hrd.lowongan.edit', $lowongan), route('hrd.lamaran.index'), route('hrd.lamaran.show', $lamaran),
            route('hrd.karyawan.index'), route('hrd.karyawan.create'), route('hrd.karyawan.show', $karyawan),
            route('hrd.karyawan.edit', $karyawan), route('hrd.absensi.index'), route('hrd.absensi.rekap'),
            route('hrd.kalender.index'), route('hrd.jabatan.index'), route('hrd.jabatan.create'),
            route('lowongan.index'), route('lowongan.show', $lowongan), route('lamaran.create', $lowongan), route('status.form'),
        ] as $url) {
            $this->actingAs($hrd)->get($url)->assertOk();
        }
    }
}
