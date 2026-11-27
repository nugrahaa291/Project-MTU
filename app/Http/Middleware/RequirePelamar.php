<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePelamar
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('pelamar')) {
            return $next($request);
        }
        if ($lowongan = $request->route('lowongan')) {
            $request->session()->put('pelamar.lowongan', $lowongan->getKey());
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Masuk sebagai pelamar terlebih dahulu.'], 401);
        }

        return redirect()->route('pelamar.login');
    }
}
