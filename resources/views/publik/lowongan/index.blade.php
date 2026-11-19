@extends('layouts.publik')
@section('judul', 'Lowongan Kerja')

@section('content')
    <section class="mx-auto max-w-6xl px-5 pt-12 pb-8 md:pt-16">
        <p class="mb-2 text-sm text-gray-600">PT Multi Talent Universal</p>
        <h1 class="text-4xl font-semibold tracking-tight md:text-6xl">Lowongan Kerja<span class="text-brand">.</span></h1>
        <p class="mt-4 text-gray-600">Temukan posisi yang sesuai dengan pengalaman Anda.</p>
    </section>

    <section aria-label="Daftar lowongan" class="mx-auto max-w-6xl px-5">
        @if ($lowongan->isEmpty())
            <div class="border-t border-line py-8">
                <h2 class="text-lg font-semibold">Belum ada lowongan yang dibuka.</h2>
                <p class="mt-1 text-sm text-gray-600">Silakan kembali lagi nanti.</p>
            </div>
        @else
            <div class="divide-y divide-line border-t-2 border-ink">
                @foreach ($lowongan as $item)
                    <article class="grid items-center gap-4 py-6 md:grid-cols-[minmax(0,1.5fr)_minmax(10rem,1fr)_auto]">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-gray-600">{{ $item->jabatan->nama }}</p>
                            <h2 class="my-1 text-xl font-semibold tracking-tight">{{ $item->judul }}</h2>
                            <p class="text-sm text-gray-600">{{ $item->lokasi }} &middot; {{ $item->sisaKuota() }} posisi tersedia</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600">Batas lamaran</p>
                            <time class="text-sm font-medium" datetime="{{ $item->tanggal_tutup->toDateString() }}">{{ $item->tanggal_tutup->translatedFormat('d F Y') }}</time>
                        </div>
                        <a class="inline-flex min-h-11 items-center font-semibold text-focus" href="{{ route('lowongan.show', $item) }}">
                            Detail dan persyaratan <span class="ml-2" aria-hidden="true">&rarr;</span>
                        </a>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $lowongan->links() }}</div>
        @endif
    </section>
@endsection
