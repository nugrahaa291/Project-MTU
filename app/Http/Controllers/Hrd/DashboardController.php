<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusAbsensi;
use App\Enums\StatusLamaran;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\Lamaran;
use App\Models\Lowongan;
use App\Services\AbsensiService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AbsensiService $absensi): View
    {
        $hariIni = $absensi->sekarang()->toDateString();
        $karyawanAktif = Karyawan::aktif()->count();

        $statistik = [
            'lowongan_dibuka' => Lowongan::terbuka()->count(),
            'lamaran_baru' => Lamaran::where('status', StatusLamaran::Diajukan->value)->count(),
            'lamaran_seleksi' => Lamaran::where('status', StatusLamaran::Seleksi->value)->count(),
            'karyawan_aktif' => $karyawanAktif,
            'sudah_absen' => Absensi::whereDate('tanggal', $hariIni)
                ->whereIn('status', [StatusAbsensi::Hadir->value, StatusAbsensi::Terlambat->value])->count(),
            'terlambat' => Absensi::whereDate('tanggal', $hariIni)
                ->where('status', StatusAbsensi::Terlambat->value)->count(),
        ];

        $lamaranTerbaru = Lamaran::with('lowongan')->latest()->limit(6)->get();

        return view('hrd.dashboard', compact('statistik', 'lamaranTerbaru', 'hariIni'));
    }
}
