<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API aplikasi absensi karyawan (Laravel Sanctum)
|--------------------------------------------------------------------------
| Semua URL otomatis diawali /api (contoh: POST /api/login).
| Autentikasi memakai token Bearer: Authorization: Bearer <token>
*/

// Karyawan login dengan NIP + password
Route::post('/login', [Api\AuthController::class, 'login'])->middleware('throttle:30,1');

Route::middleware(['auth:sanctum', 'akun.aktif', 'role:karyawan'])->group(function () {
    Route::post('/logout', [Api\AuthController::class, 'logout']);
    Route::get('/me', [Api\AuthController::class, 'me']);

    Route::put('/password', [Api\AuthController::class, 'gantiPassword'])->middleware('throttle:10,1')->name('api.password');

    Route::middleware('password.diganti')->group(function () {
        // ---- Alur absensi BARU: Standby / Order / Izin ----
        Route::prefix('aktivitas')->group(function () {
            Route::get('/ringkasan', [Api\AktivitasController::class, 'ringkasan']);
            Route::post('/standby', [Api\AktivitasController::class, 'standby']);
            Route::post('/order', [Api\AktivitasController::class, 'order']);
            Route::post('/izin', [Api\AktivitasController::class, 'izin']);
            Route::get('/', [Api\AktivitasController::class, 'riwayat']);
        });

        // ---- Alur LAMA (masuk/pulang), dipertahankan agar riwayat dan data lama tetap bisa diakses ----
        Route::prefix('absensi')->group(function () {
            Route::get('/hari-ini', [Api\AbsensiController::class, 'hariIni']);
            Route::post('/masuk', [Api\AbsensiController::class, 'masuk']);
            Route::post('/pulang', [Api\AbsensiController::class, 'pulang']);
            Route::get('/', [Api\AbsensiController::class, 'riwayat']);
        });
    });
});
