<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\Jabatan;
use App\Models\JadwalDriver;
use App\Models\Karyawan;
use App\Models\User;
use App\Services\AbsensiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class RekapAbsensiBaruTest extends TestCase
{
    use RefreshDatabase;
    use SiapkanData;

    private function buatKaryawan(string $nama, string $nip, string $nik, string $proyek = 'Wingbox'): Karyawan
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Driver'], ['aktif' => true]);
        $user = User::factory()->create([
            'role' => Role::Karyawan,
            'name' => $nama,
            'email' => strtolower(str_replace(' ', '', $nama)) . '@mtu.test',
        ]);

        return Karyawan::create([
            'user_id' => $user->id,
            'jabatan_id' => $jabatan->id,
            'nama' => $nama,
            'nip' => $nip,
            'nik' => $nik,
            'proyek' => $proyek,
            'no_hp' => '081234567890',
            'alamat' => 'Karawang',
            'tanggal_masuk' => '2026-01-01',
            'aktif' => true,
        ]);
    }

    public function test_rekap_absensi_mendukung_filter_rentang_tanggal_fleksibel(): void
    {
        $admin = $this->admin();
        $k1 = $this->buatKaryawan('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');

        // Absensi tanggal 2026-11-05 Hadir, dan tanggal 2026-11-10 Terlambat
        Absensi::create([
            'karyawan_id' => $k1->id,
            'tanggal' => '2026-11-05',
            'status' => StatusAbsensi::Hadir,
        ]);
        Absensi::create([
            'karyawan_id' => $k1->id,
            'tanggal' => '2026-11-10',
            'status' => StatusAbsensi::Terlambat,
            'menit_terlambat' => 25,
        ]);

        // Filter rentang: 2026-11-01 s/d 2026-11-07 (hanya masuk tanggal 5)
        $res = $this->actingAs($admin)->get(route('admin.absensi.rekap', [
            'dari' => '2026-11-01',
            'sampai' => '2026-11-07',
        ]));

        $res->assertOk();
        $res->assertSee('Diki Maulana');
        $res->assertSee('MTU2001');

        $baris = $res->viewData('baris');
        $this->assertCount(1, $baris);
        $this->assertSame(1, $baris[0]['hadir']);
        $this->assertSame(0, $baris[0]['terlambat']); // tanggal 10 di luar rentang
    }

    public function test_rekap_absensi_pencarian_terpadu_nama_nik_nip(): void
    {
        $admin = $this->admin();
        $this->buatKaryawan('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');
        $this->buatKaryawan('Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer');

        // Cari berdasarkan NIK MTU
        $resNik = $this->actingAs($admin)->get(route('admin.absensi.rekap', ['cari' => '3215010202920002']));
        $resNik->assertOk();
        $resNik->assertSee('Teguh Setiawan');
        $resNik->assertDontSee('Diki Maulana');

        // Cari berdasarkan NIP
        $resNip = $this->actingAs($admin)->get(route('admin.absensi.rekap', ['cari' => 'MTU2001']));
        $resNip->assertOk();
        $resNip->assertSee('Diki Maulana');
        $resNip->assertDontSee('Teguh Setiawan');
    }

    public function test_driver_libur_terjadwal_tidak_dihitung_sebagai_alpa(): void
    {
        $admin = $this->admin();
        $driver = $this->buatKaryawan('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');

        // Jadwal libur driver pada tanggal hari kerja biasa (misal: 2026-11-16 hari Senin)
        JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-11-16',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
            'proyek' => 'Wingbox',
        ]);

        // Cek tandaiAlpa di AbsensiService: tidak boleh menandai alpa untuk driver ini
        $service = app(AbsensiService::class);
        $senin = CarbonImmutable::createFromDate(2026, 11, 16);
        $jumlahAlpa = $service->tandaiAlpa($senin);

        $this->assertSame(0, $jumlahAlpa);
        $this->assertDatabaseMissing('absensi', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-11-16',
            'status' => 'alpa',
        ]);

        // Cek halaman rekap: kolom libur terjadwal bernilai 1
        $resRekap = $this->actingAs($admin)->get(route('admin.absensi.rekap', [
            'dari' => '2026-11-16',
            'sampai' => '2026-11-16',
        ]));
        $resRekap->assertOk();

        $baris = $resRekap->viewData('baris');
        $this->assertSame(1, $baris[0]['libur_terjadwal']);
        $this->assertSame(0, $baris[0]['alpa']);
    }

    public function test_ekspor_excel_dan_csv_rekap_absensi_berhasil(): void
    {
        $admin = $this->admin();
        $this->buatKaryawan('Diki Maulana', 'MTU2001', '3215010101900001');

        // Ekspor Excel
        $resExcel = $this->actingAs($admin)->get(route('admin.absensi.rekap', [
            'dari' => '2026-11-01',
            'sampai' => '2026-11-30',
            'export' => 'excel',
        ]));
        $resExcel->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $resExcel->headers->get('Content-Type'));

        // Ekspor CSV
        $resCsv = $this->actingAs($admin)->get(route('admin.absensi.rekap', [
            'dari' => '2026-11-01',
            'sampai' => '2026-11-30',
            'export' => 'csv',
        ]));
        $resCsv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resCsv->headers->get('Content-Type'));
    }
}
