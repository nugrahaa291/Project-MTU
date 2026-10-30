<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Menyajikan aplikasi absensi (PWA) di /absen. Datanya sendiri lewat /api/* (lihat routes/api.php). */
class PwaController extends Controller
{
    public function index(): Response
    {
        $versi = collect(['app.js', 'lib.js', 'api.js', 'style.css'])
            ->map(fn ($f) => @filemtime(public_path("pwa/$f")) ?: 0)
            ->max();

        return response()
            ->view('pwa.index', ['versi' => $versi])
            ->withHeaders([
                // Hanya skrip dari domain sendiri: token login tidak bisa dibaca skrip luar
                'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; "
                    ."font-src https://fonts.gstatic.com; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; "
                    ."worker-src 'self'; manifest-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'",
                'Permissions-Policy' => 'camera=(self), geolocation=(self), microphone=()',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'same-origin',
            ]);
    }

    public function serviceWorker(): BinaryFileResponse
    {
        return response()->file(public_path('pwa/sw.js'), [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            // Mengizinkan SW dari /absen/sw.js mengelola scope /absen (bukan seluruh situs)
            'Service-Worker-Allowed' => '/absen',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function manifest(): JsonResponse
    {
        $nama = config('app.name');

        return response()->json([
            'name' => "Absensi {$nama}",
            'short_name' => 'Absensi',
            'description' => 'Absen masuk dan pulang dengan GPS dan selfie.',
            'lang' => 'id',
            'start_url' => '/absen',
            'scope' => '/absen',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f5f6f8',
            'theme_color' => '#30323a',
            'icons' => [
                ['src' => '/pwa/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
