@extends('layouts.panel')
@section('judul', 'Detail Lowongan')

@section('content')
    <div class="card max-w-3xl space-y-5 p-6">
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

        <div class="text-sm">
            <span class="text-gray-500">Paket dokumen pelamar:</span>
            <span class="font-medium">{{ config('dokumen_lamaran.paket.'.($lowongan->paket_dokumen ?: 'umum').'.label') }}</span>
        </div>

        <div>
            <h3 class="font-bold">Dokumen yang Dipersiapkan</h3>
            @if (filled($lowongan->deskripsi))
                <p class="whitespace-pre-line text-gray-700">{{ $lowongan->deskripsi }}</p>
            @else
                {{-- Belum diisi manual: tampilkan daftar standar sesuai unit --}}
                <ul class="mt-1 space-y-1.5 text-gray-700">
                    @foreach ($lowongan->daftarDokumen() as $d)
                        <li class="flex gap-2">
                            <x-ikon nama="check" class="mt-0.5 h-5 w-5 shrink-0 text-brand" />
                            <span>
                                {{ $d['label'] }}
                                @if ($d['wajib'])
                                    <span class="font-bold text-red-600">*</span>
                                @else
                                    <span class="text-sm text-gray-400">(opsional)</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-gray-500"><span class="font-bold text-red-600">*</span> wajib diisi pelamar.</p>
            @endif
        </div>

        <div>
            <h3 class="font-bold">Persyaratan</h3>
            <p class="whitespace-pre-line text-gray-700">{{ $lowongan->persyaratan ?: '-' }}</p>
        </div>

        <div class="border-t border-line pt-4">
            <a href="{{ route('hrd.lowongan.index') }}" class="btn btn-outline gap-2">
                <x-ikon nama="arrow" class="h-4 w-4 rotate-180" /> Kembali
            </a>
        </div>
    </div>
@endsection