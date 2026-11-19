@extends('layouts.publik')
@section('judul', 'Lowongan Kerja')

@section('content')
    {{-- HERO --}}
    <section class="border-b-4 border-brand bg-ink text-white">
        <div class="mx-auto max-w-6xl px-4 py-12 md:py-16">
            <h1 class="text-4xl font-bold tracking-tight md:text-6xl">Lowongan Kerja</h1>
            
            <p class="mt-3 max-w-2xl text-lg text-gray-100">
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
                    <article class="card flex flex-col p-6 transition hover:-translate-y-1 hover:shadow-lg">
                        <x-badge warna="brand" class="self-start">{{ $item->jabatan->nama }}</x-badge>
                        <h2 class="mt-3 text-lg font-bold">{{ $item->judul }}</h2>

                        <dl class="mt-3 space-y-1 text-sm text-gray-600">
                            <div>Lokasi: <span class="font-medium text-ink">{{ $item->lokasi }}</span></div>
                            <div>Ditutup: <span class="font-medium text-ink">{{ $item->tanggal_tutup->translatedFormat('d F Y') }}</span></div>
                            <div>Sisa kuota: <span class="font-medium text-ink">{{ $item->sisaKuota() }} orang</span></div>
                        </dl>

                        <a href="{{ route('lowongan.show', $item) }}" class="btn btn-primary mt-5 self-start">Detail dan persyaratan</a>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">{{ $lowongan->links() }}</div>
        @endif
    </section>
@endsection
