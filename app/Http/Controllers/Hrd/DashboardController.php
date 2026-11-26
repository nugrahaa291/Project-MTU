<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusLamaran;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KegiatanAbsensi;
use App\Models\Lamaran;
use App\Models\Lowongan;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $statistik = [
            'lowongan_dibuka' => Lowongan::terbuka()->count(),
            'lamaran_baru' => Lamaran::where('status', StatusLamaran::Diajukan->value)->count(),
            'lamaran_seleksi' => Lamaran::where('status', StatusLamaran::Seleksi->value)->count(),
            'karyawan_aktif' => Karyawan::aktif()->count(),
            'absen_hari_ini' => KegiatanAbsensi::whereDate('tanggal', now(config('absensi.timezone'))->toDateString())->distinct('karyawan_id')->count('karyawan_id'),
            'izin_menunggu' => KegiatanAbsensi::izinMenunggu()->count(),
        ];

        $lamaranTerbaru = Lamaran::with('lowongan')
            ->where(function ($q) {
                $q->where('status', '!=', StatusLamaran::Diterima->value)
                    ->orWhere(function ($sub) {
                        $sub->where('status', StatusLamaran::Diterima->value)
                            ->where(function ($tgl) {
                                $batas = now()->subDays(3);
                                $tgl->where('diputuskan_pada', '>=', $batas)
                                    ->orWhere(function ($fallback) use ($batas) {
                                        $fallback->whereNull('diputuskan_pada')
                                            ->where('updated_at', '>=', $batas);
                                    });
                            });
                    });
            })
            ->latest()
            ->limit(6)
            ->get();

        return view('hrd.dashboard', compact('statistik', 'lamaranTerbaru'));
    }
}
