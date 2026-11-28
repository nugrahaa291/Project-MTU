<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PeriksaSesiPelamar
{
    public function handle(Request $request, Closure $next): Response
    {
        $pelamar = $request->user('pelamar');
        if ($pelamar?->user_id) {
            $sidik = $pelamar->sidikPasswordKaryawan();
            if (! $sidik || ! hash_equals($sidik, (string) $request->session()->get('pelamar.sidik'))) {
                Auth::guard('pelamar')->logout();
                $request->session()->forget('pelamar');
                $request->session()->regenerate(true);
            }
        }

        return $next($request);
    }
}
