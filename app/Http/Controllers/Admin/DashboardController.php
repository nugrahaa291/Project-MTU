<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Enums\StatusLamaran;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\Lamaran;
use App\Models\Lowongan;
use App\Models\User;
use App\Services\AbsensiService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AbsensiService $absensi): View
    {
        $hariIni = $absensi->sekarang()->toDateString();
        $karyawanAktif = Karyawan::aktif()->count();

        $statistik = [
            // Statistik Operasional (Dasar Dashboard Admin HRD sebelumnya)
            'lowongan_dibuka' => Lowongan::terbuka()->count(),
            'lamaran_baru' => Lamaran::where('status', StatusLamaran::Diajukan->value)->count(),
            'lamaran_seleksi' => Lamaran::where('status', StatusLamaran::Seleksi->value)->count(),
            'karyawan_aktif' => $karyawanAktif,
            'sudah_absen' => Absensi::whereDate('tanggal', $hariIni)
                ->whereIn('status', [StatusAbsensi::Hadir->value, StatusAbsensi::Terlambat->value])->count(),
            'terlambat' => Absensi::whereDate('tanggal', $hariIni)
                ->where('status', StatusAbsensi::Terlambat->value)->count(),

            // Statistik Pengguna & Akun Sistem
            'total_admin' => User::where('role', Role::Admin)->count(),
            'total_hrd' => User::where('role', Role::Hrd)->count(),
            'total_karyawan' => User::where('role', Role::Karyawan)->count(),
            'total_user_aktif' => User::where('is_active', true)->count(),
            'total_user_nonaktif' => User::where('is_active', false)->count(),
            'total_lowongan' => Lowongan::count(),
            'total_pelamar' => Lamaran::count(),
            'total_pelamar_diterima' => Lamaran::where('status', StatusLamaran::Diterima->value)->count(),
        ];

        $lamaranTerbaru = Lamaran::with('lowongan')->latest()->limit(6)->get();

        $penggunaTerbaru = User::latest('id')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('statistik', 'lamaranTerbaru', 'penggunaTerbaru', 'hariIni'));
    }
}
