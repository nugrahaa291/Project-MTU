<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lowongan;
use Illuminate\View\View;

class LowonganController extends Controller
{
    public function index(): View
    {
        $lowongan = Lowongan::terbuka()
            ->denganJumlahDiterima()
            ->with('jabatan')
            ->latest('tanggal_buka')
            ->paginate(9);

        return view('publik.lowongan.index', compact('lowongan'));
    }

    public function show(Lowongan $lowongan): View
    {
        $lowongan->load('jabatan');

        return view('publik.lowongan.show', compact('lowongan'));
    }
}
