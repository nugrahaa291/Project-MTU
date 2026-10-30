<?php

use App\Exceptions\AbsensiException;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'akun.aktif' => EnsureAccountActive::class,
            'password.diganti' => EnsurePasswordChanged::class,
        ]);

        // Pengguna yang sudah login dan membuka halaman login diarahkan ke dashboard sesuai perannya
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Absensi yang ditolak aturan bisnis => balasan JSON rapi untuk aplikasi mobile
        $exceptions->render(fn (AbsensiException $e, Request $request) => response()->json([
            'message' => $e->getMessage(),
            'kode' => $e->kode,
        ], $e->status));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
