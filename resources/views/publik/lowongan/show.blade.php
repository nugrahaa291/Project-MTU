@extends('layouts.publik')
@section('judul', $lowongan->judul)

@section('content')
    <section class="border-b border-line">
        <div class="mx-auto max-w-4xl px-5 pt-10 pb-6">
            <a href="{{ route('lowongan.index') }}" class="text-sm text-focus">&larr; Semua lowongan</a>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ $lowongan->judul }}</h1>
            <p class="mt-2 text-gray-600">{{ $lowongan->jabatan->nama }} &middot; {{ $lowongan->lokasi }}</p>
        </div>
    </section>

    <div class="mx-auto grid max-w-4xl gap-8 px-5 py-8 md:grid-cols-3">
        <div class="space-y-6 md:col-span-2">
            <div class="border-b border-line pb-6">
                <h2 class="mb-2 text-lg font-bold">Deskripsi Pekerjaan</h2>
                <p class="whitespace-pre-line text-gray-700">{{ $lowongan->deskripsi ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <div class="border-b border-line pb-6">
                <h2 class="mb-2 text-lg font-bold">Persyaratan</h2>
                <p class="whitespace-pre-line text-gray-700">{{ $lowongan->persyaratan ?: 'Belum ada persyaratan khusus.' }}</p>
            </div>
        </div>

        <aside class="card order-first h-fit md:order-last md:sticky md:top-24 space-y-3 p-5 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-500">Status</span>
                <x-badge :warna="$lowongan->bisaDilamar() ? 'green' : 'red'">{{ $lowongan->labelStatus() }}</x-badge>
            </div>
            <div class="flex justify-between"><span class="text-gray-500">Dibuka</span><span class="font-medium">{{ $lowongan->tanggal_buka->translatedFormat('d M Y') }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Ditutup</span><span class="font-medium">{{ $lowongan->tanggal_tutup->translatedFormat('d M Y') }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Sisa kuota</span><span class="font-medium">{{ $lowongan->sisaKuota() }} dari {{ $lowongan->kuota }}</span></div>

            @if ($lowongan->bisaDilamar())
                <a href="{{ route('lamaran.create', $lowongan) }}" class="btn btn-primary w-full">Lamar Sekarang</a>
            @else
                <p class="rounded-md bg-gray-50 p-3 text-center text-gray-500">Lowongan ini tidak menerima lamaran.</p>
            @endif
        </aside>
    </div>
@endsection
