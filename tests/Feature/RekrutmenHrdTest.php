<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatusLamaran;
use App\Models\Karyawan;
use App\Models\Pelamar;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class RekrutmenHrdTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private function berkasValid(): array
    {
        $disk = Storage::fake('local');
        foreach (['x/cv.pdf', 'x/f.png', 'x/k.png'] as $path) {
            $disk->put($path, 'berkas uji');
        }

        return [
            'verifikasi' => ['cv' => 'valid', 'ktp' => 'valid', 'pas_foto' => 'valid'],
            'alasan_seleksi' => 'Pengalaman sesuai persyaratan dan didukung CV.',
        ];
    }

    private function penilaian(string $rekomendasi = 'lanjut'): array
    {
        return [
            'tanggal_interview' => today()->toDateString(), 'pewawancara' => 'Ibu Dewi',
            'hasil_interview' => 'Dapat menjelaskan langkah penanganan gangguan.',
            'rekomendasi_interview' => $rekomendasi,
        ];
    }

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
        $pelamar = Pelamar::create(['nama' => 'Dewi Lestari', 'email' => 'dewi@example.com']);
        $lamaran = $this->lamaran($lowongan, ['pelamar_id' => $pelamar->id, 'nama' => $pelamar->nama, 'email' => $pelamar->email]);

        // Langsung terima dari "diajukan" ditolak: harus lewat seleksi dulu
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $lamaran), [
            'email' => 'dewi@example.com', 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Memenuhi kualifikasi.',
        ])->assertSessionHas('error');
        $this->assertSame(0, Karyawan::count());

        $this->actingAs($hrd)->post(route('hrd.lamaran.proses', $lamaran), $this->berkasValid())->assertSessionHas('success');
        $this->assertSame(StatusLamaran::Seleksi, $lamaran->fresh()->status);

        $this->get(route('hrd.interview.index'))->assertOk()->assertSee($lamaran->kode);
        $this->get(route('hrd.lamaran.show', $lamaran))->assertOk()->assertSee('Simpan hasil interview')->assertDontSee('Terima sebagai Karyawan');
        $this->post(route('hrd.interview.store', $lamaran), $this->penilaian())->assertSessionHas('success');
        $this->get(route('hrd.interview.index', ['tahap' => 'dinilai']))->assertOk()->assertSee($lamaran->kode);
        $this->get(route('hrd.interview.index'))->assertDontSee($lamaran->kode);
        $this->get(route('hrd.lamaran.show', $lamaran))->assertOk()->assertSee('Terima sebagai Karyawan');
        $this->assertSame(0, Karyawan::count());

        $this->post(route('hrd.lamaran.terima', $lamaran), [
            'email' => $lamaran->email, 'tanggal_masuk' => today()->toDateString(), 'catatan' => '  ',
        ])->assertSessionHasErrors('catatan');
        $this->assertSame(0, Karyawan::count());

        $response = $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $lamaran), [
            'email' => 'dewi@example.com', 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Cocok',
        ]);

        $karyawan = Karyawan::with('user')->firstOrFail();
        $response->assertRedirect(route('hrd.karyawan.show', $karyawan))->assertSessionHas('pin_sementara');

        $this->assertSame(StatusLamaran::Diterima, $lamaran->fresh()->status);
        $this->assertSame($hrd->id, $lamaran->fresh()->diperiksa_oleh);
        $this->assertSame($hrd->id, $lamaran->fresh()->dinilai_oleh);
        $this->assertSame($hrd->id, $lamaran->fresh()->diputuskan_oleh);
        $this->assertSame($lamaran->id, $karyawan->lamaran_id);
        $this->assertSame($karyawan->id, $pelamar->lamaran()->sole()->karyawan->id);
        $this->assertSame($lowongan->jabatan_id, $karyawan->jabatan_id); // jabatan ikut lowongan
        $this->assertSame('Dewi Lestari', $karyawan->nama);
        $this->assertSame($lamaran->nik, $karyawan->nik);
        $this->assertNotNull($karyawan->nip);

        $user = $karyawan->user;
        $this->assertSame(Role::Karyawan, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('dewi@example.com', $user->email);

        // PIN sementara benar-benar cocok dengan hash dan tidak tersimpan polos di database
        $password = session('pin_sementara');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $password);              // PIN sementara 6 digit
        $this->assertTrue(Pin::valid($password));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->getRawOriginal('password'));
    }

    public function test_kuota_tidak_bisa_dilampaui(): void
    {
        $hrd = $this->hrd();
        $lowongan = $this->lowongan(['kuota' => 1]);
        $a = $this->lamaran($lowongan, ['status' => 'seleksi', 'email' => 'a@example.com']);
        $b = $this->lamaran($lowongan, ['status' => 'seleksi', 'email' => 'b@example.com']);

        foreach ([$a, $b] as $lamaran) {
            $this->actingAs($hrd)->post(route('hrd.lamaran.proses', $lamaran), $this->berkasValid())->assertSessionHas('success');
            $this->post(route('hrd.interview.store', $lamaran), $this->penilaian())->assertSessionHas('success');
        }
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $a), ['email' => 'a@example.com', 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Lolos penilaian.']);
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $b), ['email' => 'b@example.com', 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Lolos penilaian.'])
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
            'email' => $hrd->email, 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Memenuhi kualifikasi.',
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

        $this->actingAs($hrd)->post(route('hrd.lamaran.proses', $ditolak), $this->berkasValid())->assertSessionHas('error');
        $this->actingAs($hrd)->post(route('hrd.lamaran.tolak', $diterima), ['catatan' => 'Sudah final'])->assertSessionHas('error');
        $this->actingAs($hrd)->post(route('hrd.lamaran.terima', $ditolak), ['email' => 'x@example.com', 'tanggal_masuk' => '2026-11-02', 'catatan' => 'Lolos penilaian.'])
            ->assertSessionHas('error');

        $this->assertSame(StatusLamaran::Ditolak, $ditolak->fresh()->status);
        $this->assertSame(StatusLamaran::Diterima, $diterima->fresh()->status);
    }

    public function test_alasan_berkas_dan_hasil_interview_wajib_sebelum_keputusan(): void
    {
        $lamaran = $this->lamaran($this->lowongan(), ['status' => 'seleksi']);
        $this->actingAs($this->admin());
        $berkas = $this->berkasValid();
        $terima = ['email' => $lamaran->email, 'tanggal_masuk' => today()->toDateString(), 'catatan' => 'Sesuai persyaratan.'];
        $this->post(route('admin.interview.store', $lamaran), $this->penilaian())->assertSessionHas('error');
        $this->post(route('admin.lamaran.terima', $lamaran), $terima)->assertSessionHas('error');
        $this->post(route('admin.lamaran.proses', $lamaran), [...$berkas, 'alasan_seleksi' => '   '])->assertSessionHasErrors('alasan_seleksi');
        $parsial = ['verifikasi' => ['ktp' => 'perbaikan'], 'alasan_seleksi' => 'Periksa ulang scan KTP.'];
        $this->post(route('admin.lamaran.berkas', $lamaran), $parsial)->assertSessionHas('success');
        $this->post(route('admin.lamaran.proses', $lamaran), $parsial)->assertSessionHas('error');
        $this->assertNull($lamaran->fresh()->lolos_berkas_pada);
        $this->assertSame('perbaikan', $lamaran->fresh()->verifikasi_berkas['ktp']);
        Storage::disk('local')->delete('x/k.png');
        $this->post(route('admin.lamaran.proses', $lamaran), $berkas)->assertSessionHas('error');
        Storage::disk('local')->put('x/k.png', 'berkas');
        $this->post(route('admin.lamaran.proses', $lamaran), $berkas)->assertSessionHas('success');
        $this->post(route('admin.lamaran.terima', $lamaran), $terima)->assertSessionHas('error');
        $this->post(route('admin.interview.store', $lamaran), ['hasil_interview' => ' '])->assertSessionHasErrors(['hasil_interview', 'tanggal_interview', 'pewawancara', 'rekomendasi_interview']);
        $this->post(route('admin.interview.store', $lamaran), $this->penilaian('tidak_lanjut'))->assertSessionHas('success');
        $this->post(route('admin.interview.store', $lamaran), $this->penilaian())->assertSessionHas('error');
        $this->post(route('admin.lamaran.terima', $lamaran), [...$terima, 'catatan' => '  '])->assertSessionHasErrors('catatan');
        $this->post(route('admin.lamaran.terima', $lamaran), $terima)->assertSessionHas('error');
        $this->post(route('admin.lamaran.tolak', $lamaran), ['alasan_penolakan' => '  '])->assertSessionHasErrors('alasan_penolakan');
        $this->post(route('admin.lamaran.tolak', $lamaran), ['alasan_penolakan' => 'Belum menguasai prosedur.'])->assertSessionHas('success');
        $this->assertSame(0, Karyawan::count());
        $this->get(route('admin.interview.index', ['tahap' => 'dinilai']))->assertOk()->assertSee($lamaran->kode);
        $this->post(route('status.cek'), ['kode' => $lamaran->kode, 'no_hp' => $lamaran->no_hp])
            ->assertOk()->assertSee('Ditolak')->assertDontSee('Belum menguasai prosedur.')->assertDontSee($berkas['alasan_seleksi'])->assertDontSee($this->penilaian()['hasil_interview']);
    }

    public function test_paket_driver_cukup_satu_pendukung_valid_dan_paklaring_opsional(): void
    {
        $disk = Storage::fake('local');
        $lowongan = $this->lowongan(['paket_dokumen' => 'trailer']);
        $kolom = array_keys(array_filter($lowongan->paketDokumen()));
        $unggahan = [];
        foreach (array_diff($kolom, ['pendukung']) as $jenis) {
            $unggahan[$jenis] = 'driver/'.$jenis.'.pdf';
            $disk->put($unggahan[$jenis], 'berkas');
        }
        $lamaran = $this->lamaran($lowongan, [...$unggahan, 'cv' => null, 'akte_lahir' => 'driver/akte.pdf']);
        $disk->put('driver/akte.pdf', 'berkas');
        $data = ['alasan_seleksi' => 'Pengalaman trailer sesuai.', 'verifikasi' => array_fill_keys(array_keys($unggahan), 'valid')];
        $this->actingAs($this->hrd())->post(route('hrd.lamaran.proses', $lamaran), $data)->assertSessionHas('error');
        $data['verifikasi']['akte_lahir'] = 'valid';
        $this->post(route('hrd.lamaran.proses', $lamaran), $data)->assertSessionHas('success');
        $this->assertNotNull($lamaran->fresh()->lolos_berkas_pada);
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
        $admin = $this->admin();
        $hrd = $this->hrd();
        $lowongan = $this->lowongan();
        $lamaran = $this->lamaran($lowongan, ['status' => 'seleksi']);
        $karyawan = $this->karyawan();

        // Halaman yang diizinkan untuk HRD (Dashboard, Lowongan, Lamaran Masuk, Karyawan View Only, Publik)
        foreach ([
            route('hrd.dashboard'), route('hrd.lowongan.index'), route('hrd.lowongan.create'), route('hrd.lowongan.show', $lowongan),
            route('hrd.lowongan.edit', $lowongan), route('hrd.lamaran.index'), route('hrd.lamaran.show', $lamaran),
            route('hrd.karyawan.index'), route('hrd.karyawan.show', $karyawan),
            route('lowongan.index'), route('lowongan.show', $lowongan), route('status.form'),
        ] as $url) {
            $this->actingAs($hrd)->get($url)->assertOk();
        }

        // HRD dilarang mengakses form create dan edit karyawan (View Only)
        $this->actingAs($hrd)->get(route('hrd.karyawan.create'))->assertForbidden();
        $this->actingAs($hrd)->get(route('hrd.karyawan.edit', $karyawan))->assertForbidden();

        // Halaman Admin (Akses Penuh termasuk Karyawan CRUD, Absensi, Kalender, Jabatan)
        foreach ([
            route('admin.dashboard'), route('admin.lowongan.index'), route('admin.lamaran.index'),
            route('admin.karyawan.index'), route('admin.karyawan.create'), route('admin.karyawan.show', $karyawan),
            route('admin.karyawan.edit', $karyawan), route('admin.absensi.index'), route('admin.absensi.rekap'),
            route('admin.kalender.index'), route('admin.jabatan.index'), route('admin.jabatan.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
