<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Dashboard') - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('pwa/fonts/poppins.css') }}">
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
        ['hrd.kegiatan.index', 'Aktivitas Absensi', 'hrd.kegiatan.*'],
        ['hrd.izin.index', 'Pengajuan Izin', 'hrd.izin.*'],
        ['hrd.absensi.index', 'Absensi Lama', 'hrd.absensi.index'],
        ['hrd.absensi.rekap', 'Rekap Absensi Lama', 'hrd.absensi.rekap'],
        ['hrd.kalender.index', 'Hari Libur', 'hrd.kalender.*'],
        ['hrd.jabatan.index', 'Jabatan', 'hrd.jabatan.*'],
    ];
    $menuKaryawan = [['karyawan.beranda', 'Absensi Saya', 'karyawan.*']];
    $menu = $user->isHrd() ? $menuHrd : $menuKaryawan;
    $izinMenunggu = $user->isHrd() ? \App\Models\KegiatanAbsensi::izinMenunggu()->count() : 0;
@endphp

<div class="min-h-screen lg:flex">
    {{-- SIDEBAR --}}
    <aside id="sidebar" class="hidden w-full shrink-0 border-r border-line bg-white text-ink-soft lg:block lg:min-h-screen lg:w-64">
        <div class="px-6 py-5 text-3xl font-bold tracking-tighter text-ink">MTU<span class="text-brand">.</span></div>
        <p class="px-6 pb-2 text-[11px] font-semibold tracking-widest text-gray-600 uppercase">Menu</p>
        <nav class="space-y-1 px-3 pb-6">
            @foreach ($menu as [$rute, $label, $pola])
                <a href="{{ route($rute) }}"
                   @if(request()->routeIs($pola)) aria-current="page" @endif
                   class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-canvas">
                    {{ $label }}
                    @if ($rute === 'hrd.izin.index' && $izinMenunggu > 0)
                        <span class="ml-1 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-ink">{{ $izinMenunggu }}</span>
                    @endif
                </a>
            @endforeach
            <a href="{{ route('lowongan.index') }}" target="_blank"
               class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-canvas">Lihat Portal Publik &nearr;</a>
        </nav>
    </aside>

    {{-- KONTEN --}}
    <div class="min-w-0 flex-1">
        <header class="panel-header flex items-center justify-between border-b border-line bg-white px-4 py-3 lg:px-8">
            <button type="button" data-toggle-sidebar aria-controls="sidebar" aria-expanded="false" class="btn btn-outline btn-sm lg:hidden">&#9776; Menu</button>
            <span class="hidden text-sm font-medium text-gray-600 lg:block">{{ $user->isHrd() ? 'Ruang HRD' : 'Ruang karyawan' }}</span>
            <details class="relative ml-auto min-w-0 max-w-[65%]">
                <summary class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 text-sm">
                    <span class="truncate font-semibold">{{ $user->name }}</span><span aria-hidden="true">⌄</span>
                </summary>
                <div class="absolute right-0 z-50 grid w-48 gap-1 rounded-lg border border-line bg-white p-2 shadow-md">
                    <a href="{{ route('password.edit') }}" class="btn btn-outline">Ganti Password</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="btn btn-dark w-full">Keluar</button>
                    </form>
                </div>
            </details>
        </header>

        <main class="panel-content mx-auto max-w-7xl px-5 py-6 lg:px-8 lg:py-8">
            <h1 class="mb-6 text-2xl font-semibold tracking-tight lg:text-3xl">@yield('judul', 'Dashboard')</h1>
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
