<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hrd;
use App\Http\Controllers\Karyawan\BerandaController;
use App\Http\Controllers\Publik;
use App\Http\Controllers\PwaController;
use App\Http\Middleware\RequirePelamar;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FASE 1 - Portal publik (tanpa login)
|--------------------------------------------------------------------------
*/
Route::get('/', [Publik\ProfilController::class, 'beranda'])->name('beranda');
Route::get('/tentang-kami', [Publik\ProfilController::class, 'tentang'])->name('tentang');

Route::get('/lowongan', [Publik\LowonganController::class, 'index'])->name('lowongan.index');
Route::get('/lowongan/{lowongan}', [Publik\LowonganController::class, 'show'])->name('lowongan.show');
Route::prefix('pelamar')->name('pelamar.')->group(function () {
    Route::get('masuk', [Publik\AkunPelamarController::class, 'form'])->name('login');
    Route::post('masuk', [Publik\AkunPelamarController::class, 'masuk'])->middleware('throttle:10,1')->name('login.store');
    Route::post('masuk-karyawan', [Publik\AkunPelamarController::class, 'masukKaryawan'])->middleware('throttle:10,1')->name('login.karyawan');
    Route::get('daftar', [Publik\AkunPelamarController::class, 'form'])->name('daftar');
    Route::post('daftar', [Publik\AkunPelamarController::class, 'daftar'])->middleware('throttle:5,1')->name('daftar.store');
    Route::get('google', [Publik\GooglePelamarController::class, 'mulai'])->middleware('throttle:20,1')->name('google');
    Route::get('google/callback', [Publik\GooglePelamarController::class, 'callback'])->name('google.callback');
});

Route::middleware(RequirePelamar::class)->group(function () {
    Route::get('/pelamar/lamaran', [Publik\AkunPelamarController::class, 'riwayat'])->name('pelamar.riwayat');
    Route::post('/pelamar/hubungkan-karyawan', [Publik\AkunPelamarController::class, 'hubungkanKaryawan'])->middleware('throttle:10,1')->name('pelamar.hubungkan.karyawan');
    Route::post('/pelamar/lamaran/tautkan', [Publik\AkunPelamarController::class, 'tautkanLamaran'])->middleware('throttle:5,1')->name('pelamar.tautkan');
    Route::post('/pelamar/keluar', [Publik\AkunPelamarController::class, 'keluar'])->name('pelamar.keluar');
    Route::get('/lowongan/{lowongan}/lamar', [Publik\LamaranController::class, 'create'])->name('lamaran.create');
    Route::post('/lowongan/{lowongan}/lamar', [Publik\LamaranController::class, 'store'])
        ->middleware('throttle:10,1')->name('lamaran.store');
    Route::get('/lamaran/berhasil/{lamaran:kode}', [Publik\LamaranController::class, 'berhasil'])->name('lamaran.berhasil');
});

Route::get('/cek-status', [Publik\StatusLamaranController::class, 'form'])->name('status.form');
Route::post('/cek-status', [Publik\StatusLamaranController::class, 'cek'])
    ->middleware('throttle:20,1')->name('status.cek');

/*
|--------------------------------------------------------------------------
| Aplikasi absensi karyawan (PWA) - data lewat /api/*
|--------------------------------------------------------------------------
*/
Route::get('/absen', [PwaController::class, 'index'])->name('pwa');
Route::get('/absen/sw.js', [PwaController::class, 'serviceWorker']);
Route::get('/absen/manifest.webmanifest', [PwaController::class, 'manifest']);

/*
|--------------------------------------------------------------------------
| Login web (HRD & karyawan)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'form'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'akun.aktif'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Halaman ganti password harus bisa dibuka walau password masih sementara
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.diganti')->group(function () {
        // Mengarahkan ke dashboard yang sesuai peran
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        /*
        |----------------------------------------------------------------------
        | Dashboard & Manajemen Administrator (Akses Penuh)
        |----------------------------------------------------------------------
        */
        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', Admin\DashboardController::class)->name('dashboard');

            // Lowongan & Lamaran Masuk (Akses Penuh)
            Route::resource('lowongan', Hrd\LowonganController::class);
            Route::get('interview', [Hrd\LamaranController::class, 'interview'])->name('interview.index');
            Route::post('lamaran/{lamaran}/interview', [Hrd\LamaranController::class, 'simpanInterview'])->name('interview.store');
            Route::post('lamaran/{lamaran}/berkas', [Hrd\LamaranController::class, 'proses'])->name('lamaran.berkas');
            Route::get('lamaran', [Hrd\LamaranController::class, 'index'])->name('lamaran.index');
            Route::get('lamaran/{lamaran}', [Hrd\LamaranController::class, 'show'])->name('lamaran.show');
            Route::get('lamaran/{lamaran}/dokumen/{jenis}', [Hrd\LamaranController::class, 'dokumen'])->name('lamaran.dokumen');
            Route::post('lamaran/{lamaran}/proses', [Hrd\LamaranController::class, 'proses'])->name('lamaran.proses');
            Route::post('lamaran/{lamaran}/terima', [Hrd\LamaranController::class, 'terima'])->name('lamaran.terima');
            Route::post('lamaran/{lamaran}/tolak', [Hrd\LamaranController::class, 'tolak'])->name('lamaran.tolak');

            // Karyawan (CRUD Penuh)
            Route::resource('karyawan', Hrd\KaryawanController::class)->except('destroy');
            Route::post('karyawan/{karyawan}/reset-password', [Hrd\KaryawanController::class, 'resetPassword'])->name('karyawan.reset-password');

            // Absensi & Rekap
            Route::get('absensi', [Hrd\AbsensiController::class, 'index'])->name('absensi.index');
            Route::get('absensi/rekap', [Hrd\AbsensiController::class, 'rekap'])->name('absensi.rekap');
            Route::post('absensi', [Hrd\AbsensiController::class, 'store'])->name('absensi.store');
            Route::get('absensi/{absensi}/foto/{jenis}', [Hrd\AbsensiController::class, 'foto'])->name('absensi.foto');

            // Data Master untuk Admin (Unit Operasional / Armada MTU)
            Route::resource('jabatan', Hrd\JabatanController::class)->except('show');
            Route::resource('unit', Hrd\JabatanController::class)->except('show');

            // Jadwal Libur Driver (Operasional Cabang MTU)
            Route::get('jadwal-driver', [Admin\JadwalDriverController::class, 'index'])->name('jadwal-driver.index');
            Route::post('jadwal-driver/direct-import', [Admin\JadwalDriverController::class, 'directImport'])->name('jadwal-driver.direct-import');
            Route::get('jadwal-driver/template', [Admin\JadwalDriverController::class, 'exportTemplate'])->name('jadwal-driver.template');
            Route::get('jadwal-driver/import', [Admin\JadwalDriverController::class, 'importForm'])->name('jadwal-driver.import');
            Route::post('jadwal-driver/preview', [Admin\JadwalDriverController::class, 'previewImport'])->name('jadwal-driver.preview');
            Route::post('jadwal-driver/store-import', [Admin\JadwalDriverController::class, 'storeImport'])->name('jadwal-driver.store-import');
            Route::get('jadwal-driver/download-invalid', [Admin\JadwalDriverController::class, 'downloadInvalidReport'])->name('jadwal-driver.download-invalid');
            Route::post('jadwal-driver/manual', [Admin\JadwalDriverController::class, 'storeManual'])->name('jadwal-driver.manual');
            Route::put('jadwal-driver/{jadwal}', [Admin\JadwalDriverController::class, 'update'])->name('jadwal-driver.update');
            Route::post('jadwal-driver/{jadwal}/pengganti', [Admin\JadwalDriverController::class, 'tentukanPengganti'])->name('jadwal-driver.pengganti');
            Route::delete('jadwal-driver/{jadwal}', [Admin\JadwalDriverController::class, 'destroy'])->name('jadwal-driver.destroy');

            // Kalender Kerja / Hari Libur (Admin mengelola Jadwal Driver)
            Route::get('kalender', [Admin\JadwalDriverController::class, 'index'])->name('kalender.index');
            Route::post('kalender', [Hrd\KalenderKerjaController::class, 'store'])->name('kalender.store');
            Route::delete('kalender/{kalender}', [Hrd\KalenderKerjaController::class, 'destroy'])->name('kalender.destroy');

            // Manajemen Pengguna
            Route::resource('users', Admin\UserController::class)->except('show');
            Route::post('users/{user}/toggle-status', [Admin\UserController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::post('users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

            // Monitoring Sistem (Dinonaktifkan aman sesuai instruksi) & Pengaturan Sistem
            Route::get('monitoring', fn () => redirect()->route('admin.dashboard'))->name('monitoring');
            Route::get('pengaturan', [Admin\PengaturanController::class, 'index'])->name('pengaturan');
            Route::post('pengaturan', [Admin\PengaturanController::class, 'update'])->name('pengaturan.update');
        });

        /*
        |----------------------------------------------------------------------
        | FASE 2 & 3 - Dashboard HRD
        |----------------------------------------------------------------------
        */
        Route::middleware('role:hrd')->prefix('hrd')->name('hrd.')->group(function () {
            Route::get('/', Hrd\DashboardController::class)->name('dashboard');

            Route::resource('jabatan', Hrd\JabatanController::class)->except('show');
            Route::resource('lowongan', Hrd\LowonganController::class);

            Route::get('interview', [Hrd\LamaranController::class, 'interview'])->name('interview.index');
            Route::post('lamaran/{lamaran}/interview', [Hrd\LamaranController::class, 'simpanInterview'])->name('interview.store');
            Route::post('lamaran/{lamaran}/berkas', [Hrd\LamaranController::class, 'proses'])->name('lamaran.berkas');
            Route::get('lamaran', [Hrd\LamaranController::class, 'index'])->name('lamaran.index');
            Route::get('lamaran/{lamaran}', [Hrd\LamaranController::class, 'show'])->name('lamaran.show');
            Route::get('lamaran/{lamaran}/dokumen/{jenis}', [Hrd\LamaranController::class, 'dokumen'])->name('lamaran.dokumen');
            Route::post('lamaran/{lamaran}/proses', [Hrd\LamaranController::class, 'proses'])->name('lamaran.proses');
            Route::post('lamaran/{lamaran}/terima', [Hrd\LamaranController::class, 'terima'])->name('lamaran.terima');
            Route::post('lamaran/{lamaran}/tolak', [Hrd\LamaranController::class, 'tolak'])->name('lamaran.tolak');

            Route::resource('karyawan', Hrd\KaryawanController::class)->except('destroy');
            Route::post('karyawan/{karyawan}/reset-password', [Hrd\KaryawanController::class, 'resetPassword'])->name('karyawan.reset-password');

            /*
            |------------------------------------------------------------------
            | Absensi (pemantauan oleh HRD)
            |------------------------------------------------------------------
            */
            /*
            |------------------------------------------------------------------
            | Absensi alur BARU: Standby / Order / Izin
            |------------------------------------------------------------------
            */
            Route::get('kegiatan', [Hrd\KegiatanController::class, 'index'])->name('kegiatan.index');
            Route::get('kegiatan/{kegiatan}/foto', [Hrd\KegiatanController::class, 'foto'])->name('kegiatan.foto');
            Route::get('kegiatan/{kegiatan}/lampiran', [Hrd\KegiatanController::class, 'lampiran'])->name('kegiatan.lampiran');
            Route::get('izin', [Hrd\IzinController::class, 'index'])->name('izin.index');
            Route::post('izin/{kegiatan}/setujui', [Hrd\IzinController::class, 'setujui'])->name('izin.setujui');
            Route::post('izin/{kegiatan}/tolak', [Hrd\IzinController::class, 'tolak'])->name('izin.tolak');

            /* Absensi alur LAMA (masuk/pulang): riwayat dipertahankan */
            Route::get('absensi', [Hrd\AbsensiController::class, 'index'])->name('absensi.index');
            Route::get('absensi/rekap', [Hrd\AbsensiController::class, 'rekap'])->name('absensi.rekap');
            Route::get('absensi/{absensi}/foto/{jenis}', [Hrd\AbsensiController::class, 'foto'])->name('absensi.foto');

            Route::get('kalender', [Hrd\KalenderKerjaController::class, 'index'])->name('kalender.index');
        });

        /*
        |----------------------------------------------------------------------
        | Area karyawan (web): lihat riwayat absensi sendiri
        |----------------------------------------------------------------------
        */
        Route::middleware('role:karyawan')->prefix('karyawan')->name('karyawan.')->group(function () {
            Route::get('/', BerandaController::class)->name('beranda');
        });
    });
});
