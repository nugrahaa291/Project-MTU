<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.ganti-password', ['wajib' => $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:password_lama', Password::min(8)->letters()->numbers()],
        ], [
            'password_lama.current_password' => 'Password lama tidak sesuai.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $user = $request->user();
        $user->password = $request->password;
        $user->forceFill(['must_change_password' => false])->save();

        return redirect()->route('dashboard')->with('success', 'Password berhasil diganti.');
    }
}
