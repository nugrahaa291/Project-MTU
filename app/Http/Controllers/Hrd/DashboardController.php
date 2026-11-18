<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusLamaran;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KegiatanAbsensi;
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
            'absen_hari_ini' => KegiatanAbsensi::whereDate('tanggal', $hariIni)->distinct('karyawan_id')->count('karyawan_id'),
            'izin_menunggu' => KegiatanAbsensi::izinMenunggu()->count(),
        ];

        $lamaranTerbaru = Lamaran::with('lowongan')->latest()->limit(6)->get();

        return view('hrd.dashboard', compact('statistik', 'lamaranTerbaru', 'hariIni'));
    }
}
