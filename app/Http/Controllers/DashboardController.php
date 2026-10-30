<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pintu masuk setelah login: mengarahkan ke dashboard sesuai peran pengguna. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->isHrd() ? 'hrd.dashboard' : 'karyawan.beranda');
    }
}
