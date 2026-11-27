<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lamaran;
use App\Models\Pelamar;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AkunPelamarController extends Controller
{
    public function form(Request $request): View|RedirectResponse
    {
        if ($request->user('pelamar')) {
            return redirect()->route('pelamar.riwayat');
        }

        return view('pelamar.masuk', ['daftar' => $request->routeIs('pelamar.daftar')]);
    }

    public function daftar(Request $request): RedirectResponse
    {
        abort_if($request->user('pelamar'), 403);
        $request->merge(['email' => Str::lower(trim($request->string('email')))]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:pelamar,email'],
            'password' => ['required', 'confirmed', Password::min(8), 'max:72'],
        ]);
        try {
            $pelamar = Pelamar::create($data);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['email' => 'Email sudah digunakan.']);
        }
        Auth::guard('pelamar')->login($pelamar);

        return $this->selesai($request);
    }

    public function masuk(Request $request): RedirectResponse
    {
        abort_if($request->user('pelamar'), 403);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $data['email'] = Str::lower(trim($data['email']));
        if (! Auth::guard('pelamar')->attempt($data)) {
            return back()->withErrors(['email' => 'Email atau password tidak cocok.'])->onlyInput('email');
        }

        return $this->selesai($request);
    }

    protected function selesai(Request $request): RedirectResponse
    {
        $request->session()->regenerate();
        $lowongan = $request->session()->pull('pelamar.lowongan');

        return $lowongan ? redirect()->route('lamaran.create', $lowongan) : redirect()->route('pelamar.riwayat');
    }

    public function riwayat(Request $request): View
    {
        return view('pelamar.riwayat', [
            'pelamar' => $request->user('pelamar'),
            'daftar' => $request->user('pelamar')->lamaran()->with('lowongan')->latest()->paginate(12),
        ]);
    }

    public function tautkanLamaran(Request $request): RedirectResponse
    {
        $data = $request->validate(['kode' => 'required|string|max:20', 'no_hp' => 'required|string|max:20']);
        try {
            $jumlah = Lamaran::whereNull('pelamar_id')->where('kode', strtoupper(trim($data['kode'])))
                ->where('no_hp', Lamaran::normalisasiHp($data['no_hp']))
                ->update(['pelamar_id' => $request->user('pelamar')->id]);
        } catch (UniqueConstraintViolationException) {
            $jumlah = 0;
        }

        return back()->with($jumlah ? 'success' : 'error', $jumlah ? 'Lamaran berhasil ditautkan.' : 'Lamaran tidak dapat ditautkan. Periksa kode dan nomor HP.');
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::guard('pelamar')->logout();
        $request->session()->forget('pelamar');
        $request->session()->regenerate(true);

        return redirect()->route('beranda');
    }
}
