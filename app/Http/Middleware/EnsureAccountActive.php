<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Memutus akses pengguna yang dinonaktifkan walau sesi/token-nya masih hidup. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($request->expectsJson()) {
                $user->tokens()->delete();

                return response()->json([
                    'message' => 'Akun Anda dinonaktifkan. Hubungi HRD.',
                    'kode' => 'akun_nonaktif',
                ], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi HRD.']);
        }

        return $next($request);
    }
}
