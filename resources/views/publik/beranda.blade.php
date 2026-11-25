@extends('layouts.publik')
@section('judul', 'Beranda')

@section('content')
    {{-- HERO --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-ink via-ink-soft to-ink text-white">
        <div class="pointer-events-none absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-brand/10 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:py-24 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <x-badge warna="brand">Sedang membuka kesempatan karier</x-badge>
                <h1 class="mt-5 text-4xl leading-tight font-bold md:text-5xl">
                    {{ config('perusahaan.tagline') }}
                </h1>
                <p class="mt-5 max-w-xl text-lg text-gray-200">{{ config('perusahaan.ringkasan') }}</p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('lowongan.index') }}" class="btn btn-primary px-6 py-3 text-base">
                        Lihat Lowongan <x-ikon nama="arrow" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('status.form') }}" class="btn border border-white/30 px-6 py-3 text-base text-white hover:bg-white/10">
                        Cek Status Lamaran
                    </a>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-white/15 bg-white/10 p-6 backdrop-blur">
                    <p class="text-sm text-gray-300">Posisi yang sedang dibuka</p>
                    <p class="mt-1 text-6xl font-bold text-brand">{{ $totalTerbuka }}</p>
                    <div class="my-5 h-px bg-white/15"></div>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-center gap-3"><x-ikon nama="document" class="h-5 w-5 text-brand" /> Lamar online, unggah berkas langsung</li>
                        <li class="flex items-center gap-3"><x-ikon nama="check" class="h-5 w-5 text-brand" /> Dapat kode lamaran otomatis</li>
                        <li class="flex items-center gap-3"><x-ikon nama="search" class="h-5 w-5 text-brand" /> Pantau status kapan saja</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- STATISTIK --}}
    <section class="mx-auto -mt-8 max-w-6xl px-4">
        <div class="card relative grid grid-cols-1 divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            @foreach (config('perusahaan.statistik') as $stat)
                <div class="px-6 py-6 text-center">
                    <p class="text-3xl font-bold text-brand">{{ $stat['angka'] }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- TENTANG SINGKAT --}}
    <section class="mx-auto max-w-6xl px-4 py-16">
        <div class="grid items-center gap-10 md:grid-cols-2">
            <div>
                <p class="text-sm font-semibold tracking-wide text-brand uppercase">Tentang Kami</p>
                <h2 class="mt-2 text-3xl font-bold">Mengenal {{ config('app.name') }}</h2>
                <div class="mt-4 h-1 w-14 rounded bg-brand"></div>
                <p class="mt-5 leading-relaxed text-gray-600">{{ config('perusahaan.tentang.0') }}</p>
                <a href="{{ route('tentang') }}" class="btn btn-dark mt-6">Selengkapnya <x-ikon nama="arrow" class="h-4 w-4" /></a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (config('perusahaan.keunggulan') as $item)
                    <div class="card p-5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-soft text-brand"><x-ikon :nama="$item['ikon']" class="h-5 w-5" /></span>
                        <h3 class="mt-3 font-bold">{{ $item['judul'] }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $item['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- LOWONGAN TERBARU --}}
    <section class="bg-white py-16">
        <div class="mx-auto max-w-6xl px-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-brand uppercase">Karier</p>
                    <h2 class="mt-2 text-3xl font-bold">Lowongan Terbaru</h2>
                </div>
                <a href="{{ route('lowongan.index') }}" class="text-sm font-semibold text-brand hover:text-brand-dark">Lihat semua lowongan &rarr;</a>
            </div>

            @if ($terbaru->isEmpty())
                <div class="mt-8 rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                    <p class="font-semibold text-ink">Belum ada lowongan yang dibuka.</p>
                    <p class="mt-1 text-sm">Silakan kembali lagi nanti.</p>
                </div>
            @else
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($terbaru as $item)
                        <x-kartu-lowongan :item="$item" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ALUR MELAMAR --}}
    <section class="mx-auto max-w-6xl px-4 py-16">
        <div class="text-center">
            <p class="text-sm font-semibold tracking-wide text-brand uppercase">Cara Melamar</p>
            <h2 class="mt-2 text-3xl font-bold">Empat langkah mudah</h2>
        </div>

        @php
            $langkah = [
                ['Pilih lowongan', 'Telusuri posisi yang sesuai dengan keahlian Anda.'],
                ['Isi form lamaran', 'Lengkapi data diri, lalu unggah CV dan KTP.'],
                ['Simpan kode lamaran', 'Kode dipakai untuk memantau perkembangan seleksi.'],
                ['Pantau status', 'Cek hasilnya di menu Cek Status kapan saja.'],
            ];
        @endphp
        <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($langkah as $i => [$judul, $isi])
                <li class="card p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand font-bold text-white">{{ $i + 1 }}</span>
                    <h3 class="mt-4 font-bold">{{ $judul }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $isi }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- AJAKAN --}}
    <section class="mx-auto max-w-6xl px-4">
        <div class="rounded-2xl bg-gradient-to-r from-brand to-brand-dark px-6 py-12 text-center text-white md:px-12">
            <h2 class="text-2xl font-bold md:text-3xl">Siap bergabung bersama kami?</h2>
            <p class="mx-auto mt-3 max-w-xl text-white/90">Kirim lamaran Anda hari ini dan jadilah bagian dari tim {{ config('app.name') }}.</p>
            <a href="{{ route('lowongan.index') }}" class="btn mt-6 bg-white px-6 py-3 text-base text-brand-dark hover:bg-gray-100">Mulai Melamar</a>
        </div>
    </section>
@endsection
