<?php

namespace Tests\Feature;

use App\Models\Lamaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class PortalPublikTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_halaman_lowongan_hanya_menampilkan_yang_terbuka(): void
    {
        $this->lowongan(['judul' => 'Lowongan Terbuka']);
        $this->lowongan(['judul' => 'Lowongan Ditutup', 'status' => 'ditutup']);
        $this->lowongan(['judul' => 'Lowongan Kedaluwarsa', 'tanggal_tutup' => today()->subDay()]);
        $this->lowongan(['judul' => 'Lowongan Belum Buka', 'tanggal_buka' => today()->addDay()]);

        $this->get('/lowongan')
            ->assertOk()
            ->assertSee('Lowongan Terbuka')
            ->assertDontSee('Lowongan Ditutup')
            ->assertDontSee('Lowongan Kedaluwarsa')
            ->assertDontSee('Lowongan Belum Buka');
    }

    public function test_pelamar_bisa_melamar_dan_berkas_tersimpan_privat(): void
    {
        Storage::fake('local');
        $lowongan = $this->lowongan();

        $response = $this->post(route('lamaran.store', $lowongan), $this->dataLamaran(['no_hp' => '+62 812-3456-7890']));

        $lamaran = Lamaran::firstOrFail();
        $response->assertRedirect(route('lamaran.berhasil', $lamaran));

        $this->assertMatchesRegularExpression('/^LMR-[A-Z0-9]{8}$/', $lamaran->kode);
        $this->assertSame('diajukan', $lamaran->status->value);
        $this->assertSame('081234567890', $lamaran->no_hp); // dinormalisasi
        Storage::disk('local')->assertExists($lamaran->cv);
        Storage::disk('local')->assertExists($lamaran->ktp);

        $this->get(route('lamaran.berhasil', $lamaran))->assertOk()->assertSee($lamaran->kode);
    }

    public function test_nik_yang_sama_tidak_bisa_melamar_dua_kali_di_lowongan_yang_sama(): void
    {
        Storage::fake('local');
        $lowongan = $this->lowongan();

        $this->post(route('lamaran.store', $lowongan), $this->dataLamaran());
        $this->post(route('lamaran.store', $lowongan), $this->dataLamaran())->assertSessionHasErrors('nik');

        $this->assertSame(1, Lamaran::count());
    }

    public function test_nik_yang_sama_boleh_melamar_lowongan_lain(): void
    {
        Storage::fake('local');

        $this->post(route('lamaran.store', $this->lowongan()), $this->dataLamaran());
        $lain = $this->lowongan(['judul' => 'Lowongan Lain']);
        $this->post(route('lamaran.store', $lain), $this->dataLamaran())->assertSessionHasNoErrors();

        $this->assertSame(2, Lamaran::count());
    }

    public function test_validasi_berkas_dan_data(): void
    {
        Storage::fake('local');
        $lowongan = $this->lowongan();

        $this->post(route('lamaran.store', $lowongan), $this->dataLamaran([
            'nik' => '123',
            'no_hp' => '12345',
            'tanggal_lahir' => today()->subYears(10)->toDateString(),
            'cv' => $this->png('bukan-cv.png'),          // CV harus PDF/DOC
            'pas_foto' => $this->pdf('pasfoto.pdf'),     // pas foto harus gambar
        ]))->assertSessionHasErrors(['nik', 'no_hp', 'tanggal_lahir', 'cv', 'pas_foto']);

        $this->assertSame(0, Lamaran::count());
    }

    public function test_lowongan_ditutup_atau_penuh_tidak_menerima_lamaran(): void
    {
        Storage::fake('local');

        $ditutup = $this->lowongan(['status' => 'ditutup']);
        $this->post(route('lamaran.store', $ditutup), $this->dataLamaran())->assertRedirect(route('lowongan.show', $ditutup));
        $this->get(route('lamaran.create', $ditutup))->assertRedirect(route('lowongan.show', $ditutup));

        $penuh = $this->lowongan(['kuota' => 1, 'judul' => 'Penuh']);
        $this->lamaran($penuh, ['status' => 'diterima']);
        $this->post(route('lamaran.store', $penuh), $this->dataLamaran(['nik' => '3215010101990099']))->assertRedirect(route('lowongan.show', $penuh));

        $this->assertSame(1, Lamaran::count()); // hanya lamaran yang dibuat langsung di database
    }

    public function test_cek_status_butuh_kode_dan_nomor_hp_yang_cocok(): void
    {
        $lamaran = $this->lamaran($this->lowongan(), ['no_hp' => '081234567890', 'nama' => 'Siti Aminah']);

        $this->post(route('status.cek'), ['kode' => strtolower($lamaran->kode), 'no_hp' => '+62 812 3456 7890'])
            ->assertOk()->assertSee('Siti Aminah')->assertSee('Diajukan');

        $this->followingRedirects()
            ->post(route('status.cek'), ['kode' => $lamaran->kode, 'no_hp' => '089999999999'])
            ->assertSee('tidak cocok')->assertDontSee('Siti Aminah');

        // Mencari hanya dengan nama tidak mungkin
        $this->post(route('status.cek'), ['nama' => 'Siti Aminah'])->assertSessionHasErrors(['kode', 'no_hp']);
    }
}
