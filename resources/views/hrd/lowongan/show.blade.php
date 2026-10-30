@extends('layouts.panel')
@section('judul', 'Detail Lowongan')

@section('content')
    <div class="card max-w-3xl space-y-4 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold">{{ $lowongan->judul }}</h2>
                <p class="text-sm text-gray-500">{{ $lowongan->jabatan->nama }} &middot; {{ $lowongan->lokasi }}</p>
            </div>
            <x-badge :warna="$lowongan->bisaDilamar() ? 'green' : 'slate'">{{ $lowongan->labelStatus() }}</x-badge>
        </div>

        <dl class="grid gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-gray-500">Dibuka</dt><dd class="font-medium">{{ $lowongan->tanggal_buka->translatedFormat('d M Y') }}</dd></div>
            <div><dt class="text-gray-500">Ditutup</dt><dd class="font-medium">{{ $lowongan->tanggal_tutup->translatedFormat('d M Y') }}</dd></div>
            <div><dt class="text-gray-500">Kuota terisi</dt><dd class="font-medium">{{ $lowongan->jumlahDiterima() }} / {{ $lowongan->kuota }}</dd></div>
            <div><dt class="text-gray-500">Total lamaran</dt><dd class="font-medium">{{ $lowongan->lamaran_count }}</dd></div>
        </dl>

        <div><h3 class="font-bold">Deskripsi</h3><p class="whitespace-pre-line text-gray-700">{{ $lowongan->deskripsi ?: '-' }}</p></div>
        <div><h3 class="font-bold">Persyaratan</h3><p class="whitespace-pre-line text-gray-700">{{ $lowongan->persyaratan ?: '-' }}</p></div>

        <div class="flex flex-wrap gap-2 border-t border-line pt-4">
            <a href="{{ route('hrd.lamaran.index', ['lowongan_id' => $lowongan->id]) }}" class="btn btn-primary">Lihat Lamaran</a>
            <a href="{{ route('hrd.lowongan.edit', $lowongan) }}" class="btn btn-outline">Edit</a>
            <a href="{{ route('lowongan.show', $lowongan) }}" target="_blank" class="btn btn-outline">Lihat di Portal</a>
            <form method="POST" action="{{ route('hrd.lowongan.destroy', $lowongan) }}" data-konfirmasi="Hapus lowongan ini?">
                @csrf @method('DELETE')<button class="btn btn-danger">Hapus</button>
            </form>
        </div>
    </div>
@endsection
