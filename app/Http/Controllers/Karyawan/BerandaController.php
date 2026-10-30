<?php

namespace App\Http\Controllers\Karyawan;

use App\Http\Controllers\Controller;
use App\Services\AbsensiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BerandaController extends Controller
{
    public function __invoke(Request $request, AbsensiService $service): View
    {
        $karyawan = $request->user()->karyawan;
        abort_if(! $karyawan, 404, 'Data karyawan untuk akun ini tidak ditemukan. Hubungi HRD.');

        $karyawan->load('jabatan');
        $hariIni = $service->sekarang()->toDateString();

        return view('karyawan.beranda', [
            'karyawan' => $karyawan,
            'hariIni' => $karyawan->absensi()->whereDate('tanggal', $hariIni)->first(),
            'riwayat' => $karyawan->absensi()->latest('tanggal')->paginate(15),
        ]);
    }
}
