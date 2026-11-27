<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lowongan;
use Illuminate\View\View;

/** Halaman profil kantor: Beranda dan Tentang Kami. */
class ProfilController extends Controller
{
    public function beranda(): View
    {
        $terbaru = Lowongan::terbuka()
            ->denganJumlahDiterima()
            ->with('jabatan')
            ->latest('tanggal_buka')
            ->limit(3)
            ->get();

        $totalTerbuka = Lowongan::terbuka()->count();

        return view('publik.beranda', compact('terbaru', 'totalTerbuka'));
    }

    public function tentang(): View
    {
        return view('publik.tentang');
    }
}
