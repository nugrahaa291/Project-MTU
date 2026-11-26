@extends('layouts.publik')
@section('judul', 'Lowongan Kerja')

@section('content')
    {{-- HERO --}}
    <section class="bg-gradient-to-br from-ink via-ink-soft to-brand/70 text-white">
        <div class="mx-auto max-w-6xl px-4 py-16 text-center">
            <h1 class="text-4xl font-bold md:text-5xl">Lowongan Kerja</h1>
            <div class="mx-auto my-5 h-1 w-16 rounded bg-brand"></div>
            <p class="mx-auto max-w-2xl text-lg text-gray-100">
                Bergabunglah bersama {{ config('app.name') }}. Pilih posisi yang sesuai, kirim lamaran, dan pantau statusnya kapan saja.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12">
        @if ($lowongan->isEmpty())
            <div class="card p-10 text-center text-gray-500">
                <p class="text-lg font-semibold text-ink">Belum ada lowongan yang dibuka.</p>
                <p class="mt-1 text-sm">Silakan kembali lagi nanti.</p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
             @foreach ($lowongan as $item)
                <x-kartu-lowongan :item="$item" />
                @endforeach
            </div>

            <div class="mt-8">{{ $lowongan->links() }}</div>
        @endif
    </section>
@endsection
