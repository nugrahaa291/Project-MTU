<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FASE 4 - API untuk aplikasi mobile absensi (Laravel Sanctum)
|--------------------------------------------------------------------------
| Semua URL otomatis diawali /api (contoh: POST /api/login).
| Autentikasi memakai token Bearer: Authorization: Bearer <token>
*/

Route::post('/login', [Api\AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'akun.aktif', 'role:karyawan'])->group(function () {
    Route::post('/logout', [Api\AuthController::class, 'logout']);
    Route::get('/me', [Api\AuthController::class, 'me']);

    // Boleh diakses walau password masih sementara (justru dipakai untuk menggantinya)
    Route::put('/password', [Api\AuthController::class, 'gantiPassword'])->name('api.password');

    Route::middleware('password.diganti')->prefix('absensi')->group(function () {
        Route::get('/hari-ini', [Api\AbsensiController::class, 'hariIni']);
        Route::post('/masuk', [Api\AbsensiController::class, 'masuk']);
        Route::post('/pulang', [Api\AbsensiController::class, 'pulang']);
        Route::get('/', [Api\AbsensiController::class, 'riwayat']);
    });
});
