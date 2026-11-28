<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna dengan password sementara wajib menggantinya dulu. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Anda harus mengganti password sementara terlebih dahulu.',
                    'kode' => 'wajib_ganti_password',
                ], 403);
            }

            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
