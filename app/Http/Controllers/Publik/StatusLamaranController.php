<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lamaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusLamaranController extends Controller
{
    public function form(): View
    {
        return view('publik.status', ['lamaran' => null]);
    }

    public function cek(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20'],
            'no_hp' => ['required', 'string', 'max:20'],
        ]);

        // Wajib kode DAN nomor HP yang cocok: nama saja tidak cukup, supaya
        // orang lain tidak bisa mengintip status lamaran orang lain.
        $lamaran = Lamaran::with('lowongan')
            ->where('kode', strtoupper(trim($data['kode'])))
            ->where('no_hp', Lamaran::normalisasiHp($data['no_hp']))
            ->first();

        if (! $lamaran) {
            return redirect()->route('status.form')
                ->withErrors(['kode' => 'Kode lamaran atau nomor HP tidak cocok.'])
                ->withInput($request->only('kode'));
        }

        return view('publik.status', compact('lamaran'));
    }
}
