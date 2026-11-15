<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Jabatan;
use App\Models\JadwalDriver;
use App\Models\JadwalImportBatch;
use App\Models\JadwalRiwayatPerubahan;
use App\Models\Karyawan;
use App\Models\User;
use App\Services\JadwalDriverService;
use App\Services\JadwalImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class JadwalDriverTest extends TestCase
{
    use RefreshDatabase;
    use SiapkanData;

    private function buatDriver(string $nama, string $nip, string $nik, string $proyek = 'Wingbox'): Karyawan
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

    public function test_admin_dapat_melihat_halaman_jadwal_driver_kalender_dan_tabel(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');

        // Tambah satu jadwal libur
        JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-15',
            'status_jadwal' => 'libur',
            'sumber' => 'manual_cabang',
            'proyek' => 'Wingbox',
            'keterangan' => 'Libur terjadwal',
        ]);

        // Cek Kalender view (kompatibilitas link lama)
        $resKalender = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'view' => 'kalender',
            'tahun' => 2026,
            'bulan' => 9,
        ]));
        $resKalender->assertOk();
        $resKalender->assertSee('Jadwal Libur Driver');
        $resKalender->assertSee('Diki Maulana');
        $resKalender->assertSee('Titik Merah');

        // Cek Tabel view
        $resTabel = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'view' => 'tabel',
            'tahun' => 2026,
            'bulan' => 9,
        ]));
        $resTabel->assertOk();
        $resTabel->assertSee('MTU2001');
        $resTabel->assertSee('Wingbox');
    }

    public function test_halaman_utama_langsung_tampilkan_tabel_dengan_4_tombol_dan_tanpa_tab(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Ahmad Fauzi', 'MTU2004', '3215010404940004', 'Logistik');

        JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-18',
            'status_jadwal' => 'libur',
            'sumber' => 'manual_cabang',
            'proyek' => 'Logistik',
            'keterangan' => 'Libur cabang',
        ]);

        $res = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'tahun' => 2026,
            'bulan' => 9,
        ]));

        $res->assertOk();
        // Cek Judul & Subjudul
        $res->assertSee('Jadwal Libur Driver');
        $res->assertSee('Kelola jadwal kerja dan libur driver PT Multi Talent Universal');

        // Cek 4 Tombol Aksi Bagian Atas
        $res->assertSee('+ Tambah Libur Driver');
        $res->assertSee('Import Jadwal Libur');
        $res->assertSee('Template Excel');
        $res->assertSee('Kalender');

        // Cek Kolom Tabel
        $res->assertSee('Nama Driver');
        $res->assertSee('NIP');
        $res->assertSee('NIK MTU');
        $res->assertSee('Proyek');
        $res->assertSee('Tanggal Libur');
        $res->assertSee('Driver Pengganti');
        $res->assertSee('Sumber Jadwal');
        $res->assertSee('Aksi');
        $res->assertSee('Ahmad Fauzi');
        $res->assertSee('MTU2004');

        // Cek Kalender Bulanan memiliki 7 kolom hari horizontal compact (Sen - Min)
        $res->assertSee('Sen');
        $res->assertSee('Sel');
        $res->assertSee('Rab');
        $res->assertSee('Kam');
        $res->assertSee('Jum');
        $res->assertSee('Sab');
        $res->assertSee('Min');

        // Pastikan tab toggle lama tidak ada
        $res->assertDontSee('role="group"', false);

        // Pastikan kalender berada di halaman (inline) bukan modal dialog kalender
        $res->assertSee('id="sectionKalender"', false);
        $res->assertDontSee('<dialog id="modalKalender"', false);

        // Pastikan input filter lama (tanggal_mulai, tanggal_akhir, status) tidak ada di UI
        $res->assertDontSee('Tanggal Mulai</label>', false);
        $res->assertDontSee('Tanggal Akhir</label>', false);
        $res->assertDontSee('Semua Status', false);
    }

    public function test_klik_tanggal_di_kalender_langsung_memfilter_tabel(): void
    {
        $admin = $this->admin();
        $driver1 = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');
        $driver2 = $this->buatDriver('Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer');

        // Driver 1 libur 15 Sep 2026
        JadwalDriver::create([
            'karyawan_id' => $driver1->id,
            'tanggal' => '2026-09-15',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
            'proyek' => 'Wingbox',
            'keterangan' => 'Libur 15 Sep',
        ]);

        // Driver 2 libur 20 Sep 2026
        JadwalDriver::create([
            'karyawan_id' => $driver2->id,
            'tanggal' => '2026-09-20',
            'status_jadwal' => 'libur',
            'sumber' => 'manual_cabang',
            'proyek' => 'Trailer',
            'keterangan' => 'Libur 20 Sep',
        ]);

        // 1. Klik tanggal 15 Sep 2026: Hanya Diki Maulana muncul, jadwal 20 Sep tidak muncul
        $res15 = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'tahun' => 2026,
            'bulan' => 9,
            'tanggal' => '2026-09-15',
        ]));
        $res15->assertOk();
        $res15->assertSee('Diki Maulana');
        $res15->assertSee('MTU2001');
        $res15->assertSee('Libur 15 Sep');
        $res15->assertDontSee('Libur 20 Sep');
        $this->assertCount(1, $res15->viewData('daftarJadwalTabel'));
        $this->assertSame('Diki Maulana', $res15->viewData('daftarJadwalTabel')->first()->karyawan->nama);

        // 2. Klik tanggal tanpa driver libur (misal 16 Sep 2026): tampilkan empty state sesuai ketentuan
        $res16 = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'tahun' => 2026,
            'bulan' => 9,
            'tanggal' => '2026-09-16',
        ]));
        $res16->assertOk();
        $res16->assertSee('Tidak ada driver libur pada tanggal ini.');
        $this->assertCount(0, $res16->viewData('daftarJadwalTabel'));
    }

    public function test_kombinasi_filter_tanggal_dan_proyek(): void
    {
        $admin = $this->admin();
        $driverWingbox = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');
        $driverTrailer = $this->buatDriver('Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer');

        // Kedua driver libur pada tanggal yang sama: 15 Sep 2026
        JadwalDriver::create([
            'karyawan_id' => $driverWingbox->id,
            'tanggal' => '2026-09-15',
            'status_jadwal' => 'libur',
            'proyek' => 'Wingbox',
            'keterangan' => 'Libur Wingbox',
        ]);
        JadwalDriver::create([
            'karyawan_id' => $driverTrailer->id,
            'tanggal' => '2026-09-15',
            'status_jadwal' => 'libur',
            'proyek' => 'Trailer',
            'keterangan' => 'Libur Trailer',
        ]);

        // Filter: Tanggal 15 Sep 2026 + Proyek Wingbox = hanya driver Wingbox yang muncul
        $res = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'tahun' => 2026,
            'bulan' => 9,
            'tanggal' => '2026-09-15',
            'proyek' => 'Wingbox',
        ]));
        $res->assertOk();
        $res->assertSee('Diki Maulana');
        $res->assertSee('Libur Wingbox');
        $res->assertDontSee('Libur Trailer');
        $this->assertCount(1, $res->viewData('daftarJadwalTabel'));
        $this->assertSame('Wingbox', $res->viewData('daftarJadwalTabel')->first()->proyek);
    }

    public function test_filter_rentang_tanggal_bekerja_pada_tabel_jadwal(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');

        JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-05',
            'status_jadwal' => 'libur',
            'proyek' => 'Wingbox',
            'keterangan' => 'Libur 1',
        ]);

        JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-20',
            'status_jadwal' => 'libur',
            'proyek' => 'Wingbox',
            'keterangan' => 'Libur 2',
        ]);

        // Filter rentang 01-10 September (hanya Libur 1 muncul)
        $resFilter = $this->actingAs($admin)->get(route('admin.jadwal-driver.index', [
            'tanggal_mulai' => '2026-09-01',
            'tanggal_akhir' => '2026-09-10',
        ]));
        $resFilter->assertOk();
        $resFilter->assertSee('Libur 1');
        $resFilter->assertDontSee('Libur 2');
    }

    public function test_indikator_titik_merah_muncul_pada_tanggal_ada_driver_libur(): void
    {
        $driver1 = $this->buatDriver('Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer');
        $driver2 = $this->buatDriver('Budi Santoso', 'MTU2003', '3215010303930003', 'Wingbox');

        JadwalDriver::create([
            'karyawan_id' => $driver1->id,
            'tanggal' => '2026-09-10',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
            'proyek' => 'Trailer',
        ]);
        JadwalDriver::create([
            'karyawan_id' => $driver2->id,
            'tanggal' => '2026-09-10',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
            'proyek' => 'Wingbox',
        ]);

        $service = app(JadwalDriverService::class);
        $dataKalender = $service->ambilKalenderBulanan(2026, 9);

        // Cari tanggal 10
        $tgl10 = collect($dataKalender['daftar_hari'])->firstWhere('tanggal', '2026-09-10');
        $this->assertNotNull($tgl10);
        $this->assertTrue($tgl10['ada_driver_libur']);
        $this->assertSame(2, $tgl10['jumlah_driver_libur']);
        $this->assertCount(2, $tgl10['daftar_driver_libur']);

        // Tanggal 11 tanpa driver libur
        $tgl11 = collect($dataKalender['daftar_hari'])->firstWhere('tanggal', '2026-09-11');
        $this->assertNotNull($tgl11);
        $this->assertFalse($tgl11['ada_driver_libur']);
        $this->assertSame(0, $tgl11['jumlah_driver_libur']);
    }

    public function test_admin_dapat_mengunduh_template_matriks_dan_standar(): void
    {
        $admin = $this->admin();

        $resMatriks = $this->actingAs($admin)->get(route('admin.jadwal-driver.template', ['format' => 'matriks']));
        $resMatriks->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $resMatriks->headers->get('Content-Type'));

        $resStandar = $this->actingAs($admin)->get(route('admin.jadwal-driver.template', ['format' => 'tabel_standar']));
        $resStandar->assertOk();
    }

    public function test_import_format_matriks_berhasil_membuat_batch_dan_jadwal(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox');

        // Buat file spreadsheet matriks sementara
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No', 'Nama Driver', 'NIP', 'NIK MTU', 'PROJECT', '01-Sep', '02-Sep', '03-Sep'],
            [1, 'Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox', '1', 'L', '1'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_mtx') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpFile);

        $uploaded = new UploadedFile($tmpFile, 'jadwal_september.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        // 1. Preview
        $resPreview = $this->actingAs($admin)->post(route('admin.jadwal-driver.preview'), [
            'file_jadwal' => $uploaded,
            'tahun' => 2026,
            'bulan' => 9,
        ]);
        $resPreview->assertOk();
        $resPreview->assertSee('Matriks Kantor Pusat');
        $resPreview->assertSee('Diki Maulana');

        // Ambil token dari view preview
        $token = $resPreview->viewData('token');
        $this->assertNotEmpty($token);

        // 2. Konfirmasi Simpan
        $resStore = $this->actingAs($admin)->post(route('admin.jadwal-driver.store-import'), [
            'token' => $token,
            'konfirmasi' => '1',
            'timpa_jadwal_lama' => '1',
        ]);
        $resStore->assertRedirect();

        // Verifikasi Batch tersimpan
        $this->assertDatabaseHas('jadwal_import_batch', [
            'nama_file' => 'jadwal_september.xlsx',
            'periode_bulan' => '2026-09',
            'total_driver' => 1,
            'total_libur' => 1,
            'total_kerja' => 2,
        ]);

        // Verifikasi Jadwal Driver tersimpan
        $this->assertDatabaseHas('jadwal_driver', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-02 00:00:00',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
        ]);
        $this->assertDatabaseHas('jadwal_driver', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-01 00:00:00',
            'status_jadwal' => 'kerja',
        ]);

        @unlink($tmpFile);
    }

    public function test_admin_dapat_import_jadwal_langsung_dari_file_explorer_tanpa_preview(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['No', 'Nama Driver', 'NIP', 'NIK MTU', 'PROJECT', '10-Sep', '11-Sep'],
            [1, 'Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer', 'L', '1'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_direct_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpFile);

        $uploaded = new UploadedFile($tmpFile, 'jadwal_direct.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        // Eksekusi rute direct-import
        $res = $this->actingAs($admin)->post(route('admin.jadwal-driver.direct-import'), [
            'file_jadwal' => $uploaded,
            'tahun' => 2026,
            'bulan' => 9,
        ]);

        $res->assertRedirect(route('admin.jadwal-driver.index', ['tahun' => 2026, 'bulan' => 9]));
        $res->assertSessionHas('success');

        // Verifikasi batch dan data masuk
        $this->assertDatabaseHas('jadwal_import_batch', [
            'nama_file' => 'jadwal_direct.xlsx',
            'periode_bulan' => '2026-09',
            'total_driver' => 1,
            'total_libur' => 1,
            'total_kerja' => 1,
        ]);

        $this->assertDatabaseHas('jadwal_driver', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-10 00:00:00',
            'status_jadwal' => 'libur',
        ]);

        @unlink($tmpFile);
    }

    public function test_rute_import_lama_mengarahkan_ke_halaman_utama(): void
    {
        $admin = $this->admin();
        $res = $this->actingAs($admin)->get(route('admin.jadwal-driver.import'));
        $res->assertRedirect(route('admin.jadwal-driver.index'));
    }

    public function test_import_menandai_driver_tidak_dikenal_dan_dapat_diunduh_csv_laporan(): void
    {
        $admin = $this->admin();

        // File dengan driver tidak terdaftar di database
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['NIP', 'NIK MTU', 'Nama Driver', 'Proyek', 'Tanggal', 'Status Jadwal'],
            ['MTU9999', '3215999999999999', 'Driver Fiktif', 'Trailer', '2026-09-10', 'Libur'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_inv') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpFile);

        $uploaded = new UploadedFile($tmpFile, 'jadwal_fiktif.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $resPreview = $this->actingAs($admin)->post(route('admin.jadwal-driver.preview'), [
            'file_jadwal' => $uploaded,
        ]);
        $resPreview->assertOk();
        $resPreview->assertSee('Ditemukan 1 Baris Bermasalah');
        $resPreview->assertSee('Driver tidak ditemukan di database');

        $token = $resPreview->viewData('token');

        // Unduh laporan baris bermasalah
        $resCsv = $this->actingAs($admin)->get(route('admin.jadwal-driver.download-invalid', ['token' => $token]));
        $resCsv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resCsv->headers->get('Content-Type'));

        @unlink($tmpFile);
    }

    public function test_import_bersifat_idempotent_mencegah_duplikasi_jadwal(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001');

        $service = app(JadwalImportService::class);
        $items = [
            [
                'karyawan_id' => $driver->id,
                'tanggal' => '2026-09-05',
                'status_jadwal' => 'libur',
                'proyek' => 'Wingbox',
                'driver_pengganti_id' => null,
                'keterangan' => 'Import Pertama',
            ],
        ];

        // Eksekusi import pertama
        $service->eksekusiImport($items, 'file1.xlsx', 'matriks', $admin->id);
        $this->assertSame(1, JadwalDriver::where('karyawan_id', $driver->id)->whereDate('tanggal', '2026-09-05')->count());

        // Eksekusi import kedua untuk periode & driver yang sama
        $service->eksekusiImport($items, 'file1.xlsx', 'matriks', $admin->id);
        // Tetap harus 1 baris (tidak duplikat)
        $this->assertSame(1, JadwalDriver::where('karyawan_id', $driver->id)->whereDate('tanggal', '2026-09-05')->count());
    }

    public function test_admin_bisa_menambah_libur_driver_manual_mendadak(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer');
        $pengganti = $this->buatDriver('Budi Santoso', 'MTU2003', '3215010303930003', 'Trailer');

        $res = $this->actingAs($admin)->post(route('admin.jadwal-driver.manual'), [
            'karyawan_id' => $driver->id,
            'tanggal_mulai' => '2026-09-20',
            'tanggal_selesai' => '2026-09-21',
            'proyek' => 'Trailer',
            'driver_pengganti_id' => $pengganti->id,
            'keterangan' => 'Izin keluarga mendadak',
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Cek 2 hari tersimpan
        $this->assertDatabaseHas('jadwal_driver', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-20 00:00:00',
            'status_jadwal' => 'libur',
            'sumber' => 'manual_cabang',
            'driver_pengganti_id' => $pengganti->id,
        ]);
        $this->assertDatabaseHas('jadwal_driver', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-21 00:00:00',
            'status_jadwal' => 'libur',
            'sumber' => 'manual_cabang',
        ]);

        // Cek riwayat perubahan tercatat
        $this->assertDatabaseHas('jadwal_riwayat_perubahan', [
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-20 00:00:00',
            'status_baru' => 'libur',
            'diubah_oleh' => $admin->id,
        ]);
    }

    public function test_bentrok_pribadi_driver_pengganti_ditolak_jika_sedang_libur(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001');
        $pengganti = $this->buatDriver('Teguh Setiawan', 'MTU2002', '3215010202920002');

        // Jadwalkan pengganti libur pada tanggal yang sama
        JadwalDriver::create([
            'karyawan_id' => $pengganti->id,
            'tanggal' => '2026-09-25',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
        ]);

        // Mencoba jadwalkan libur driver dengan pengganti yang sedang libur
        $res = $this->actingAs($admin)->post(route('admin.jadwal-driver.manual'), [
            'karyawan_id' => $driver->id,
            'tanggal_mulai' => '2026-09-25',
            'driver_pengganti_id' => $pengganti->id,
            'keterangan' => 'Libur',
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('sedang dijadwalkan libur', (string) session('error'));
    }

    public function test_admin_dapat_menentukan_driver_pengganti_dan_mencatat_riwayat(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001');
        $pengganti = $this->buatDriver('Budi Santoso', 'MTU2003', '3215010303930003');

        $jadwal = JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-28',
            'status_jadwal' => 'libur',
            'sumber' => 'import_pusat',
            'status_verifikasi' => 'perlu_verifikasi',
        ]);

        $res = $this->actingAs($admin)->post(route('admin.jadwal-driver.pengganti', $jadwal), [
            'driver_pengganti_id' => $pengganti->id,
            'alasan' => 'Menjaga armada Wingbox tetap operasional',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $jadwal->refresh();
        $this->assertSame($pengganti->id, $jadwal->driver_pengganti_id);
        $this->assertSame('disetujui', $jadwal->status_verifikasi);

        $this->assertDatabaseHas('jadwal_riwayat_perubahan', [
            'jadwal_id' => $jadwal->id,
            'driver_pengganti_baru' => $pengganti->id,
            'diubah_oleh' => $admin->id,
        ]);
    }

    public function test_admin_dapat_membatalkan_jadwal_libur(): void
    {
        $admin = $this->admin();
        $driver = $this->buatDriver('Diki Maulana', 'MTU2001', '3215010101900001');

        $jadwal = JadwalDriver::create([
            'karyawan_id' => $driver->id,
            'tanggal' => '2026-09-30',
            'status_jadwal' => 'libur',
            'sumber' => 'manual_cabang',
        ]);

        $res = $this->actingAs($admin)->delete(route('admin.jadwal-driver.destroy', $jadwal));
        $res->assertRedirect();

        $this->assertDatabaseMissing('jadwal_driver', ['id' => $jadwal->id]);
    }

    public function test_hrd_dan_karyawan_dilarang_mengimpor_atau_mengubah_jadwal_driver(): void
    {
        $hrd = $this->hrd();
        $karyawan = User::factory()->create(['role' => Role::Karyawan]);

        // HRD dilarang
        $this->actingAs($hrd)->get(route('admin.jadwal-driver.index'))->assertForbidden();
        $this->actingAs($hrd)->get(route('admin.jadwal-driver.import'))->assertForbidden();
        $this->actingAs($hrd)->post(route('admin.jadwal-driver.manual'), [])->assertForbidden();

        // Karyawan dilarang
        $this->actingAs($karyawan)->get(route('admin.jadwal-driver.index'))->assertForbidden();
    }
}
