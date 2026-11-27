<?php

namespace Tests\Feature;

use App\Enums\StatusKegiatan;
use App\Exceptions\AbsensiException;
use App\Models\KegiatanAbsensi;
use App\Services\KegiatanAbsensiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

/** Panel admin untuk absensi alur baru: pembatasan akses dan keputusan izin Lain-lain. */
class IzinPanelTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private function izin(\App\Models\Karyawan $k, array $o = []): KegiatanAbsensi
    {
        static $n = 0;
        $n++;

        return KegiatanAbsensi::create([
            'karyawan_id' => $k->id, 'kunci' => 'kunci-izin-uji-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'jenis' => 'izin', 'kategori_izin' => 'lainnya', 'status' => 'menunggu',
            'tanggal' => '2026-11-14', 'dicatat_pada' => now(), 'alasan' => 'Keluarga sakit mendadak',
            ...$o,
        ]);
    }

    /* ----------------------------------------------------------- akses */

    public function test_panel_hanya_untuk_hrd(): void
    {
        $pemohon = $this->karyawan();
        $izin = $this->izin($pemohon);
        $karyawan = $this->karyawan();
        $url = [
            ['get', route('hrd.izin.index')], ['get', route('hrd.kegiatan.index')],
            ['post', route('hrd.izin.setujui', $izin)], ['post', route('hrd.izin.tolak', $izin)],
            ['get', route('hrd.kegiatan.foto', $izin)], ['get', route('hrd.kegiatan.lampiran', $izin)],
        ];

        foreach ($url as [$metode, $alamat]) {
            $this->app['auth']->forgetGuards();
            $this->{$metode}($alamat)->assertRedirect('/login');                       // tamu
            $this->actingAs($karyawan->user)->{$metode}($alamat)->assertForbidden();   // karyawan
        }

        $this->assertSame(StatusKegiatan::Menunggu, $izin->fresh()->status);
    }

    public function test_service_menolak_pemutus_yang_bukan_hrd_walau_route_terlewati(): void
    {
        $izin = $this->izin($this->karyawan());
        $pengganti = $this->karyawan();
        $bukanAdmin = $this->karyawan()->user;

        try {
            app(KegiatanAbsensiService::class)->setujui($izin, $pengganti, $bukanAdmin);
            $this->fail('Seharusnya ditolak');
        } catch (AbsensiException $e) {
            $this->assertSame('bukan_admin', $e->kode);
        }
        $this->assertSame(StatusKegiatan::Menunggu, $izin->fresh()->status);
    }

    /* ------------------------------------------------------- keputusan */

    public function test_izin_tidak_bisa_disetujui_tanpa_pengganti(): void
    {
        $izin = $this->izin($this->karyawan());

        $this->actingAs($this->hrd())->post(route('hrd.izin.setujui', $izin), [])->assertSessionHas('error');
        $this->assertSame(StatusKegiatan::Menunggu, $izin->fresh()->status);
        $this->assertNull($izin->fresh()->pengganti_id);

        $this->actingAs($this->hrd())->post(route('hrd.izin.setujui', $izin), ['pengganti_id' => 99999])->assertSessionHas('error');
        $this->assertSame(StatusKegiatan::Menunggu, $izin->fresh()->status);
    }

    public function test_izin_disetujui_setelah_memilih_pengganti(): void
    {
        $hrd = $this->hrd();
        $pemohon = $this->karyawan();
        $pengganti = $this->karyawan();
        $izin = $this->izin($pemohon);

        $this->actingAs($hrd)->post(route('hrd.izin.setujui', $izin), ['pengganti_id' => $pengganti->id])->assertSessionHas('success');

        $izin->refresh();
        $this->assertSame(StatusKegiatan::Disetujui, $izin->status);
        $this->assertSame($pengganti->id, $izin->pengganti_id);
        $this->assertSame($hrd->id, $izin->diputuskan_oleh);
        $this->assertNotNull($izin->diputuskan_pada);
    }

    public function test_pengganti_tidak_valid_ditolak(): void
    {
        $hrd = $this->hrd();
        $pemohon = $this->karyawan();
        $nonaktif = $this->karyawan();
        $nonaktif->update(['aktif' => false]);
        $sedangIzin = $this->karyawan();
        $this->izin($sedangIzin, ['kategori_izin' => 'libur', 'status' => 'tercatat']); // izin di tanggal yang sama
        $izin = $this->izin($pemohon);

        foreach ([$pemohon->id => 'diri sendiri', $nonaktif->id => 'tidak aktif', $sedangIzin->id => 'sedang izin'] as $id => $alasan) {
            $this->actingAs($hrd)->post(route('hrd.izin.setujui', $izin), ['pengganti_id' => $id])
                ->assertSessionHas('error');
            $this->assertSame(StatusKegiatan::Menunggu, $izin->fresh()->status, "Pengganti $alasan tidak boleh diterima");
        }
    }

    public function test_izin_ditolak_dengan_alasan_dan_alasan_wajib(): void
    {
        $hrd = $this->hrd();
        $izin = $this->izin($this->karyawan());

        $this->actingAs($hrd)->post(route('hrd.izin.tolak', $izin), ['alasan_penolakan' => ''])->assertSessionHas('error');
        $this->assertSame(StatusKegiatan::Menunggu, $izin->fresh()->status);

        $this->actingAs($hrd)->post(route('hrd.izin.tolak', $izin), ['alasan_penolakan' => 'Tidak ada karyawan pengganti'])->assertSessionHas('success');

        $izin->refresh();
        $this->assertSame(StatusKegiatan::Ditolak, $izin->status);
        $this->assertSame('Tidak ada karyawan pengganti', $izin->alasan_penolakan);
        $this->assertNull($izin->pengganti_id);
        $this->assertSame($hrd->id, $izin->diputuskan_oleh);
    }

    public function test_keputusan_bersifat_final(): void
    {
        $hrd = $this->hrd();
        $pengganti = $this->karyawan();
        $izin = $this->izin($this->karyawan());

        $this->actingAs($hrd)->post(route('hrd.izin.tolak', $izin), ['alasan_penolakan' => 'Tidak ada pengganti']);
        $this->actingAs($hrd)->post(route('hrd.izin.setujui', $izin), ['pengganti_id' => $pengganti->id])->assertSessionHas('error');
        $this->assertSame(StatusKegiatan::Ditolak, $izin->fresh()->status);

        $lain = $this->izin($this->karyawan());
        $this->actingAs($hrd)->post(route('hrd.izin.setujui', $lain), ['pengganti_id' => $pengganti->id]);
        $this->actingAs($hrd)->post(route('hrd.izin.tolak', $lain), ['alasan_penolakan' => 'Berubah pikiran'])->assertSessionHas('error');
        $this->assertSame(StatusKegiatan::Disetujui, $lain->fresh()->status);
    }

    public function test_hanya_izin_lain_lain_yang_menunggu_yang_bisa_diputuskan(): void
    {
        $hrd = $this->hrd();
        $pengganti = $this->karyawan();
        $libur = $this->izin($this->karyawan(), ['kategori_izin' => 'libur', 'status' => 'tercatat']);
        $standby = KegiatanAbsensi::create([
            'karyawan_id' => $this->karyawan()->id, 'kunci' => 'kunci-standby-uji-001', 'jenis' => 'standby',
            'status' => 'tercatat', 'tanggal' => '2026-11-14', 'dicatat_pada' => now(),
        ]);

        foreach ([$libur, $standby] as $catatan) {
            $this->actingAs($hrd)->post(route('hrd.izin.setujui', $catatan), ['pengganti_id' => $pengganti->id])->assertSessionHas('error');
            $this->actingAs($hrd)->post(route('hrd.izin.tolak', $catatan), ['alasan_penolakan' => 'tes'])->assertSessionHas('error');
            $this->assertSame(StatusKegiatan::Tercatat, $catatan->fresh()->status);
        }
    }

    /* --------------------------------------------------------- tampilan */

    public function test_halaman_admin_tampil_dan_berkas_hanya_untuk_hrd(): void
    {
        Storage::fake('local');
        $hrd = $this->hrd();
        $k = $this->karyawan();
        $this->izin($k);
        $order = KegiatanAbsensi::create([
            'karyawan_id' => $k->id, 'kunci' => 'kunci-order-uji-0001', 'jenis' => 'order', 'status' => 'tercatat',
            'tanggal' => '2026-11-14', 'dicatat_pada' => now(), 'tujuan' => 'Gudang X', 'lat_aktual' => -6.3, 'lng_aktual' => 107.3,
            'akurasi_meter' => 10, 'nama_lokasi' => 'Jalan Uji', 'jarak_kantor_meter' => 2300, 'foto' => 'kegiatan/order/1/uji.jpg',
        ]);
        Storage::disk('local')->put('kegiatan/order/1/uji.jpg', 'jpeg-uji');

        $this->actingAs($hrd)->get(route('hrd.izin.index'))->assertOk()->assertSee('Keluarga sakit mendadak')->assertSee('Karyawan pengganti');
        $this->actingAs($hrd)->get(route('hrd.kegiatan.index', ['dari' => '2026-11-14', 'sampai' => '2026-11-14']))
            ->assertOk()->assertSee('Gudang X')->assertSee('Jalan Uji')->assertSee('Selfie');
        $this->actingAs($hrd)->get(route('hrd.kegiatan.foto', $order))->assertOk();
        $this->actingAs($hrd)->get(route('hrd.kegiatan.lampiran', $order))->assertNotFound(); // tidak ada SKD
        $this->actingAs($hrd)->get(route('hrd.dashboard'))->assertOk()->assertSee('Izin menunggu keputusan');
    }
}
