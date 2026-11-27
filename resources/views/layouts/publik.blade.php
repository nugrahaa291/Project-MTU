<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Karier') - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    @php
        $menu = [
            ['beranda', 'Beranda', 'beranda'],
            ['tentang', 'Tentang Kami', 'tentang'],
            ['lowongan.index', 'Lowongan Kerja', 'lowongan.*|lamaran.*'],
            ['status.form', 'Cek Status', 'status.*'],
        ];
    @endphp

    <header class="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('beranda') }}" class="text-2xl font-bold text-ink">
                {{ config('app.name') }}<span class="text-brand">.</span>
            </a>

            {{-- Navigasi layar lebar --}}
            <nav class="hidden items-center gap-1 text-sm font-medium md:flex" aria-label="Menu utama">
                @foreach ($menu as [$rute, $label, $pola])
                    @php $aktif = request()->routeIs(...explode('|', $pola)); @endphp
                    <a href="{{ route($rute) }}"
                       @if ($aktif) aria-current="page" @endif
                       class="rounded-md px-3 py-2 transition {{ $aktif ? 'bg-brand-soft text-brand-dark' : 'text-ink hover:text-brand' }}">{{ $label }}</a>
                @endforeach
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm ml-3">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm ml-3">Login</a>
                @endauth
            </nav>

            {{-- Tombol menu layar kecil --}}
            <button type="button" data-toggle-menu aria-controls="menu-publik" aria-expanded="false" aria-label="Buka menu"
                    class="rounded-md p-2 text-ink hover:bg-gray-100 md:hidden">
                <x-ikon nama="menu" data-ikon-buka />
                <x-ikon nama="close" class="hidden h-6 w-6" data-ikon-tutup />
            </button>
        </div>

        <nav id="menu-publik" class="hidden border-t border-line bg-white px-4 pb-4 md:hidden" aria-label="Menu utama seluler">
            <div class="mx-auto flex max-w-6xl flex-col gap-1 pt-3 text-sm font-medium">
                @foreach ($menu as [$rute, $label, $pola])
                    @php $aktif = request()->routeIs(...explode('|', $pola)); @endphp
                    <a href="{{ route($rute) }}"
                       class="rounded-md px-3 py-2.5 {{ $aktif ? 'bg-brand-soft text-brand-dark' : 'hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary mt-2">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary mt-2">Login</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="flex-1">
        @if (session('success') || session('error'))
            <div class="mx-auto max-w-6xl px-4 pt-6">
                <x-flash />
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-16 bg-ink text-gray-300">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 text-sm md:grid-cols-3">
            <div>
                <p class="text-xl font-bold text-white">{{ config('app.name') }}<span class="text-brand">.</span></p>
                <p class="mt-3 max-w-xs leading-relaxed">{{ config('perusahaan.tagline') }}</p>
            </div>

            <div>
                <p class="font-semibold text-white">Menu</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($menu as [$rute, $label])
                        <li><a href="{{ route($rute) }}" class="hover:text-brand">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="font-semibold text-white">Hubungi Kami</p>
                <ul class="mt-3 space-y-2">
                    <li class="flex gap-2"><x-ikon nama="pin" class="mt-0.5 h-4 w-4 shrink-0 text-brand" />{{ config('perusahaan.kontak.alamat') }}</li>
                    <li class="flex gap-2"><x-ikon nama="phone" class="mt-0.5 h-4 w-4 shrink-0 text-brand" />{{ config('perusahaan.kontak.telepon') }}</li>
                    <li class="flex gap-2"><x-ikon nama="mail" class="mt-0.5 h-4 w-4 shrink-0 text-brand" />{{ config('perusahaan.kontak.email') }}</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-600/60 px-4 py-4 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Hak cipta dilindungi.
        </div>
    </footer>
</body>
</html>