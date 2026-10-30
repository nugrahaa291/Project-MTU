@extends('layouts.publik')
@section('judul', $lowongan->judul)

@section('content')
    <section class="bg-ink text-white">
        <div class="mx-auto max-w-4xl px-4 py-12">
            <a href="{{ route('lowongan.index') }}" class="text-sm text-gray-300 hover:text-white">&larr; Semua lowongan</a>
            <h1 class="mt-3 text-3xl font-bold">{{ $lowongan->judul }}</h1>
            <p class="mt-2 text-gray-300">{{ $lowongan->jabatan->nama }} &middot; {{ $lowongan->lokasi }}</p>
        </div>
    </section>

    <div class="mx-auto grid max-w-4xl gap-6 px-4 py-10 md:grid-cols-3">
        <div class="space-y-6 md:col-span-2">
            <div class="card p-6">
                <h2 class="mb-2 text-lg font-bold">Deskripsi Pekerjaan</h2>
                <p class="whitespace-pre-line text-gray-700">{{ $lowongan->deskripsi ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <div class="card p-6">
                <h2 class="mb-2 text-lg font-bold">Persyaratan</h2>
                <p class="whitespace-pre-line text-gray-700">{{ $lowongan->persyaratan ?: 'Belum ada persyaratan khusus.' }}</p>
            </div>
        </div>

        <aside class="card h-fit space-y-3 p-6 text-sm">
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
