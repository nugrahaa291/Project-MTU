<?php

use App\Services\AbsensiService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Menandai ALPA untuk karyawan yang tidak absen. Default: hari ini (WIB).
// Contoh manual untuk tanggal tertentu: php artisan absensi:tandai-alpa 2026-10-27
Artisan::command('absensi:tandai-alpa {tanggal?}', function (AbsensiService $absensi) {
    $tanggal = $this->argument('tanggal')
        ? \Carbon\CarbonImmutable::parse($this->argument('tanggal'), config('absensi.timezone'))
        : $absensi->sekarang();

    $jumlah = $absensi->tandaiAlpa($tanggal);

    $this->info("Selesai: {$jumlah} karyawan ditandai alpa untuk {$tanggal->toDateString()}.");
})->purpose('Tandai alpa karyawan yang tidak absen pada hari kerja');

// Jalan tiap malam pukul 23:30 WIB (butuh scheduler aktif: php artisan schedule:work / cron schedule:run)
Schedule::command('absensi:tandai-alpa')->dailyAt('23:30')->timezone(config('absensi.timezone'));
