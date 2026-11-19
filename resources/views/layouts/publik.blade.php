<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Karier') - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('pwa/fonts/poppins.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">

    <header class="sticky top-0 z-30 border-b border-line bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-2 px-4 py-2">
            <a href="{{ route('lowongan.index') }}" class="text-2xl font-bold text-ink">
                MTU<span class="text-brand">.</span>
            </a>
            <nav class="flex items-center gap-3 text-sm font-medium">
                <a href="{{ route('lowongan.index') }}" class="inline-flex min-h-11 items-center hover:text-brand-dark">Lowongan Kerja</a>
                <a href="{{ route('status.form') }}" class="inline-flex min-h-11 items-center hover:text-brand-dark">Cek Status</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Login</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto max-w-6xl px-4 has-[*]:pt-6">
            <x-flash />
        </div>
        @yield('content')
    </main>

    <footer class="mt-12 bg-ink text-gray-300">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm">
            <p class="font-semibold text-white">{{ config('app.name') }}</p>
            <p class="mt-1">Portal rekrutmen dan absensi karyawan.</p>
            <p class="mt-6 border-t border-gray-600 pt-4 text-center text-gray-400">&copy; {{ date('Y') }} {{ config('app.name') }}. Hak cipta dilindungi.</p>
        </div>
    </footer>
</body>
</html>
