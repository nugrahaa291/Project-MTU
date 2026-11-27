<?php

namespace Tests\Feature;

use App\Models\Lamaran;
use App\Models\Pelamar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class PortalPublikTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private function masukSebagaiPelamar(): void
    {
        $this->actingAs(Pelamar::create(['nama' => 'Pelamar', 'email' => 'pelamar@example.test']), 'pelamar');
    }

        public function test_beranda_menampilkan_profil_dan_lowongan_terbuka_saja(): void
    {
        $this->lowongan(['judul' => 'Lowongan Terbuka']);
        $this->lowongan(['judul' => 'Lowongan Ditutup', 'status' => 'ditutup']);

        $this->get('/')
            ->assertOk()
            ->assertSee(config('perusahaan.tagline'))
            ->assertSee('Lowongan Terbuka')
            ->assertDontSee('Lowongan Ditutup');
    }

    public function test_beranda_tetap_tampil_tanpa_lowongan(): void
    {
        $this->get('/')->assertOk()->assertSee('Belum ada lowongan yang dibuka.');
    }

    public function test_halaman_tentang_kami_menampilkan_visi_dan_misi(): void
    {
        $this->get('/tentang-kami')
            ->assertOk()
            ->assertSee('Visi')
            ->assertSee('Misi')
            ->assertSee(config('perusahaan.kontak.email'));
    }

    public function test_navbar_publik_memuat_empat_menu_utama(): void
    {
        $this->get('/')
            ->assertSee(route('beranda'), false)
            ->assertSee(route('tentang'), false)
            ->assertSee(route('lowongan.index'), false)
            ->assertSee(route('status.form'), false)
            ->assertSee('Beranda')
            ->assertSee('Tentang Kami')
            ->assertSee('Lowongan Kerja')
            ->assertSee('Cek Status');
    }

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

        /** Data lamaran driver: tanpa CV, dengan dokumen paket driver. */
    private function dataDriver(array $override = []): array
    {
        $data = $this->dataLamaran();
        unset($data['cv']);

        return [
            ...$data,
            'sim' => $this->png('sim.png'),
            'kk' => $this->png('kk.png'),
            'skck' => $this->pdf('skck.pdf'),
            'domisili' => $this->pdf('domisili.pdf'),
            'paklaring' => $this->pdf('paklaring.pdf'),
            'jenis_pendukung' => 'akte_lahir',
            'dokumen_pendukung' => $this->pdf('akte.pdf'),
            'surat_lamaran' => $this->pdf('surat.pdf'),
            ...$override,
        ];
    }

    public function test_form_driver_bertahap_dan_menandai_dokumen_wajib_dan_opsional(): void
    {
        $this->masukSebagaiPelamar();
        $trailer = $this->lowongan(['judul' => 'Trailer', 'paket_dokumen' => 'trailer']);
        $wingbox = $this->lowongan(['judul' => 'Wingbox', 'paket_dokumen' => 'wingbox']);

        $this->get(route('lamaran.create', $trailer))
            ->assertOk()
            ->assertSee('Data Diri')
            ->assertSee('Dokumen')
            ->assertSee('Surat Lamaran')
            ->assertSee('Paklaring (opsional)')
            ->assertDontSee('CV');

        $this->get(route('lamaran.create', $wingbox))
            ->assertOk()
            ->assertDontSee('Paklaring (opsional)');
    }

    public function test_lamaran_driver_menyimpan_dokumen_sesuai_pilihan(): void
    {
        $this->masukSebagaiPelamar();
        Storage::fake('local');
        $lowongan = $this->lowongan(['judul' => 'Trailer', 'paket_dokumen' => 'trailer']);

        $this->post(route('lamaran.store', $lowongan), $this->dataDriver())->assertRedirect();

        $lamaran = Lamaran::firstOrFail();
        foreach (['pas_foto', 'ktp', 'sim', 'kk', 'skck', 'domisili', 'paklaring', 'akte_lahir', 'surat_lamaran'] as $kolom) {
            Storage::disk('local')->assertExists($lamaran->{$kolom});
        }
        $this->assertNull($lamaran->cv);
        $this->assertNull($lamaran->ijazah); // hanya akte lahir yang dipilih
    }

    public function test_dokumen_wajib_driver_harus_diisi_tetapi_paklaring_trailer_opsional(): void
    {
        $this->masukSebagaiPelamar();
        $lowongan = $this->lowongan(['paket_dokumen' => 'trailer']);

        $this->post(route('lamaran.store', $lowongan), $this->dataDriver([
            'sim' => null, 'skck' => null, 'surat_lamaran' => null,
            'jenis_pendukung' => null, 'dokumen_pendukung' => null,
            'paklaring' => null,
        ]))
            ->assertSessionHasErrors(['sim', 'skck', 'surat_lamaran', 'jenis_pendukung', 'dokumen_pendukung'])
            ->assertSessionDoesntHaveErrors('paklaring');

        $this->assertSame(0, Lamaran::count());
    }

    public function test_paklaring_wajib_untuk_wingbox_dan_cdd_fuso(): void
    {
        $this->masukSebagaiPelamar();
        foreach (['wingbox', 'cdd_fuso'] as $paket) {
            $lowongan = $this->lowongan(['paket_dokumen' => $paket]);

            $this->post(route('lamaran.store', $lowongan), $this->dataDriver(['paklaring' => null]))
                ->assertSessionHasErrors('paklaring');
        }
    }

    public function test_jenis_dokumen_pendukung_harus_salah_satu_dari_tiga_pilihan(): void
    {
        $this->masukSebagaiPelamar();
        $lowongan = $this->lowongan(['paket_dokumen' => 'cdd_fuso']);

        $this->post(route('lamaran.store', $lowongan), $this->dataDriver(['jenis_pendukung' => 'sim']))
            ->assertSessionHasErrors('jenis_pendukung');
    }

    public function test_detail_lowongan_menampilkan_daftar_dokumen(): void
    {
        $lowongan = $this->lowongan(['paket_dokumen' => 'trailer']);

        $this->get(route('lowongan.show', $lowongan))
            ->assertOk()
            ->assertSee('Dokumen yang Dipersiapkan')
            ->assertSee('SIM')
            ->assertSee('Ijazah / Akte Lahir / Buku Nikah');
    }

        public function test_hrd_memilih_unit_dan_paket_dokumen_mengikuti_unit(): void
    {
        $hrd = $this->hrd();
        $jabatan = \App\Models\Jabatan::firstOrCreate(['nama' => 'Driver'], ['aktif' => true]);
        $dasar = [
            'jabatan_id' => $jabatan->id,
            'lokasi' => 'Karawang',
            'kuota' => 2,
            'tanggal_buka' => today()->toDateString(),
            'tanggal_tutup' => today()->addDays(10)->toDateString(),
            'status' => 'dibuka',
        ];

            foreach (['Driver Trailer' => 'trailer', 'Driver Wingbox' => 'wingbox', 'Driver CDD' => 'cdd_fuso', 'Driver Fuso' => 'cdd_fuso'] as $unit => $paket) {
            $this->actingAs($hrd)->post(route('hrd.lowongan.store'), [...$dasar, 'judul' => $unit])
                ->assertSessionHasNoErrors();

            $this->assertSame($paket, \App\Models\Lowongan::where('judul', $unit)->value('paket_dokumen'));
        }
    }

    public function test_lowongan_baru_hanya_boleh_memakai_unit_yang_tersedia(): void
    {
        $jabatan = \App\Models\Jabatan::firstOrCreate(['nama' => 'Driver'], ['aktif' => true]);

        $this->actingAs($this->hrd())->post(route('hrd.lowongan.store'), [
            'judul' => 'Staff IT',
            'jabatan_id' => $jabatan->id,
            'lokasi' => 'Karawang',
            'kuota' => 1,
            'tanggal_buka' => today()->toDateString(),
            'tanggal_tutup' => today()->addDays(10)->toDateString(),
            'status' => 'dibuka',
        ])->assertSessionHasErrors('judul');
    }
    
    public function test_kirim_lamaran_lewat_fetch_membalas_json_berisi_alamat_tujuan(): void
    {
        $this->masukSebagaiPelamar();
        Storage::fake('local');
        $lowongan = $this->lowongan(['paket_dokumen' => 'trailer']);

        $this->post(route('lamaran.store', $lowongan), $this->dataDriver(), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $this->assertSame(1, Lamaran::count());
    }

    public function test_kirim_lamaran_lewat_fetch_menolak_dengan_422_dan_daftar_error(): void
    {
        $this->masukSebagaiPelamar();
        $lowongan = $this->lowongan(['paket_dokumen' => 'trailer']);

        $this->post(route('lamaran.store', $lowongan), $this->dataDriver(['sim' => null, 'nik' => '123']), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sim', 'nik']);

        $this->assertSame(0, Lamaran::count());
    }

        public function test_halaman_lamaran_terkirim_menampilkan_kode_dan_langkah_selanjutnya(): void
    {
        $this->masukSebagaiPelamar();
        Storage::fake('local');
        $lowongan = $this->lowongan(['judul' => 'Driver Trailer', 'paket_dokumen' => 'trailer']);
        $this->post(route('lamaran.store', $lowongan), $this->dataDriver());
        $lamaran = Lamaran::firstOrFail();

        $this->get(route('lamaran.berhasil', $lamaran))
            ->assertOk()
            ->assertSee($lamaran->kode)
            ->assertSee('Anda telah terdaftar sebagai calon')
            ->assertSee('Driver Trailer')
            ->assertSee('Langkah selanjutnya')
            ->assertSee(route('pelamar.riwayat'), false);
    }

        public function test_halaman_detail_lowongan_hrd_punya_tombol_kembali_dan_aksi(): void
    {
        $lowongan = $this->lowongan([
            'judul' => 'Driver Wingbox',
            'paket_dokumen' => 'wingbox',
            'persyaratan' => 'Usia 25 - 45 Tahun',
        ]);

        $this->actingAs($this->hrd())->get(route('hrd.lowongan.show', $lowongan))
            ->assertOk()
            ->assertSee('Driver Wingbox')
            ->assertSee('Kembali')
            ->assertSee(route('hrd.lowongan.index'), false)
            ->assertSee(route('hrd.lowongan.edit', $lowongan), false)
            ->assertSee('Usia 25 - 45 Tahun');
    }

    public function test_pelamar_bisa_melamar_dan_berkas_tersimpan_privat(): void
    {
        $this->masukSebagaiPelamar();
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
        $this->masukSebagaiPelamar();
        Storage::fake('local');
        $lowongan = $this->lowongan();

        $this->post(route('lamaran.store', $lowongan), $this->dataLamaran());
        $this->post(route('lamaran.store', $lowongan), $this->dataLamaran())->assertSessionHasErrors('nik');

        $this->assertSame(1, Lamaran::count());
    }

    public function test_nik_yang_sama_boleh_melamar_lowongan_lain(): void
    {
        $this->masukSebagaiPelamar();
        Storage::fake('local');

        $this->post(route('lamaran.store', $this->lowongan()), $this->dataLamaran());
        $lain = $this->lowongan(['judul' => 'Lowongan Lain']);
        $this->post(route('lamaran.store', $lain), $this->dataLamaran())->assertSessionHasNoErrors();

        $this->assertSame(2, Lamaran::count());
    }

    public function test_validasi_berkas_dan_data(): void
    {
        $this->masukSebagaiPelamar();
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
        $this->masukSebagaiPelamar();
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


