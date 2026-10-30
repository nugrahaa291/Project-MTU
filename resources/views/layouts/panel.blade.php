<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Dashboard') - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@php
    $user = auth()->user();
    $menuHrd = [
        ['hrd.dashboard', 'Dashboard', 'hrd.dashboard'],
        ['hrd.lowongan.index', 'Lowongan', 'hrd.lowongan.*'],
        ['hrd.lamaran.index', 'Lamaran Masuk', 'hrd.lamaran.*'],
        ['hrd.karyawan.index', 'Karyawan', 'hrd.karyawan.*'],
        ['hrd.absensi.index', 'Absensi', 'hrd.absensi.index'],
        ['hrd.absensi.rekap', 'Rekap Absensi', 'hrd.absensi.rekap'],
        ['hrd.kalender.index', 'Hari Libur', 'hrd.kalender.*'],
        ['hrd.jabatan.index', 'Jabatan', 'hrd.jabatan.*'],
    ];
    $menuKaryawan = [['karyawan.beranda', 'Absensi Saya', 'karyawan.*']];
    $menu = $user->isHrd() ? $menuHrd : $menuKaryawan;
@endphp

<div class="min-h-screen lg:flex">
    {{-- SIDEBAR --}}
    <aside id="sidebar" class="hidden w-full shrink-0 bg-ink text-gray-300 lg:block lg:min-h-screen lg:w-64">
        <div class="px-6 py-5 text-2xl font-bold text-white">{{ config('app.name') }}<span class="text-brand">.</span></div>
        <p class="px-6 pb-2 text-[11px] font-semibold tracking-widest text-gray-400 uppercase">Menu</p>
        <nav class="space-y-1 px-3 pb-6">
            @foreach ($menu as [$rute, $label, $pola])
                <a href="{{ route($rute) }}"
                   class="block rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs($pola) ? 'bg-brand text-white' : 'hover:bg-ink-soft hover:text-white' }}">
                    {{ $label }}
                </a>
            @endforeach
            <a href="{{ route('lowongan.index') }}" target="_blank"
               class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-ink-soft hover:text-white">Lihat Portal Publik &nearr;</a>
        </nav>
    </aside>

    {{-- KONTEN --}}
    <div class="min-w-0 flex-1">
        <header class="flex items-center justify-between border-b border-line bg-white px-4 py-3 lg:px-8">
            <button type="button" data-toggle-sidebar class="btn btn-outline btn-sm lg:hidden">&#9776; Menu</button>
            <h1 class="hidden text-lg font-bold lg:block">@yield('judul', 'Dashboard')</h1>
            <div class="flex items-center gap-3 text-sm">
                <div class="text-right leading-tight">
                    <p class="font-semibold">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500">{{ $user->role->label() }}</p>
                </div>
                <a href="{{ route('password.edit') }}" class="btn btn-outline btn-sm">Ganti Password</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-dark btn-sm">Keluar</button>
                </form>
            </div>
        </header>

        <main class="p-4 lg:p-8">
            <h1 class="mb-4 text-xl font-bold lg:hidden">@yield('judul', 'Dashboard')</h1>
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
