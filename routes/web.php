<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Hrd;
use App\Http\Controllers\Karyawan\BerandaController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\Publik;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FASE 1 - Portal publik (tanpa login)
|--------------------------------------------------------------------------
*/
Route::redirect('/', '/lowongan');

Route::get('/lowongan', [Publik\LowonganController::class, 'index'])->name('lowongan.index');
Route::get('/lowongan/{lowongan}', [Publik\LowonganController::class, 'show'])->name('lowongan.show');
Route::get('/lowongan/{lowongan}/lamar', [Publik\LamaranController::class, 'create'])->name('lamaran.create');
Route::post('/lowongan/{lowongan}/lamar', [Publik\LamaranController::class, 'store'])
    ->middleware('throttle:10,1')->name('lamaran.store');
Route::get('/lamaran/berhasil/{lamaran:kode}', [Publik\LamaranController::class, 'berhasil'])->name('lamaran.berhasil');

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
        | FASE 2 & 3 - Dashboard HRD
        |----------------------------------------------------------------------
        */
        Route::middleware('role:hrd')->prefix('hrd')->name('hrd.')->group(function () {
            Route::get('/', Hrd\DashboardController::class)->name('dashboard');

            Route::resource('jabatan', Hrd\JabatanController::class)->except('show');
            Route::resource('lowongan', Hrd\LowonganController::class);

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
            Route::get('absensi', [Hrd\AbsensiController::class, 'index'])->name('absensi.index');
            Route::get('absensi/rekap', [Hrd\AbsensiController::class, 'rekap'])->name('absensi.rekap');
            Route::post('absensi', [Hrd\AbsensiController::class, 'store'])->name('absensi.store');
            Route::get('absensi/{absensi}/foto/{jenis}', [Hrd\AbsensiController::class, 'foto'])->name('absensi.foto');

            Route::get('kalender', [Hrd\KalenderKerjaController::class, 'index'])->name('kalender.index');
            Route::post('kalender', [Hrd\KalenderKerjaController::class, 'store'])->name('kalender.store');
            Route::delete('kalender/{kalender}', [Hrd\KalenderKerjaController::class, 'destroy'])->name('kalender.destroy');
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
