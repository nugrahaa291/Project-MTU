<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function form(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // is_active => true: akun nonaktif otomatis gagal login
        $berhasil = Auth::attempt(
            [...$kredensial, 'is_active' => true],
            $request->boolean('ingat'),
        );

        if (! $berhasil) {
            return back()
                ->withErrors(['email' => 'Email atau password salah, atau akun Anda dinonaktifkan.'])
                ->onlyInput('email');
        }

        // Ganti ID sesi setelah login untuk mencegah session fixation
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
