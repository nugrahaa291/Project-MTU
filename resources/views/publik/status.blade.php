@extends('layouts.publik')
@section('judul', 'Cek Status Lamaran')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-12">
        <h1 class="text-2xl font-bold">Cek Status Lamaran</h1>
        <p class="mb-6 text-gray-600">Masukkan kode lamaran dan nomor HP yang Anda daftarkan.</p>

        <form method="POST" action="{{ route('status.cek') }}" class="card space-y-4 p-6">
            @csrf
            <x-field name="kode" label="Kode Lamaran" placeholder="LMR-XXXXXXXX" :value="request('kode')" required />
            <x-field name="no_hp" label="Nomor HP" placeholder="081234567890" inputmode="tel" required />
            <button class="btn btn-primary w-full">Cek Status</button>
        </form>

        @if ($lamaran)
            @php
                $tahap = [
                    ['Diajukan', true],
                    ['Seleksi', in_array($lamaran->status->value, ['seleksi', 'diterima'])],
                    [$lamaran->status->value === 'ditolak' ? 'Ditolak' : 'Diterima', in_array($lamaran->status->value, ['diterima', 'ditolak'])],
                ];
            @endphp
            <div class="card mt-6 p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-500">{{ $lamaran->kode }}</p>
                        <h2 class="text-lg font-bold">{{ $lamaran->nama }}</h2>
                        <p class="text-sm text-gray-600">{{ $lamaran->lowongan->judul }}</p>
                    </div>
                    <x-badge :warna="$lamaran->status->warna()">{{ $lamaran->status->label() }}</x-badge>
                </div>

                <ol class="mt-6 flex items-center">
                    @foreach ($tahap as $i => [$nama, $selesai])
                        <li class="flex flex-1 flex-col items-center text-center text-xs">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full font-bold
                                {{ $selesai ? ($lamaran->status->value === 'ditolak' && $i === 2 ? 'bg-red-500 text-white' : 'bg-brand text-white') : 'bg-gray-200 text-gray-500' }}">
                                {{ $i + 1 }}
                            </span>
                            <span class="mt-1 font-medium">{{ $nama }}</span>
                        </li>
                    @endforeach
                </ol>

                <p class="mt-6 text-xs text-gray-500">Dikirim pada {{ $lamaran->created_at->translatedFormat('d F Y, H:i') }}</p>
                @if ($lamaran->status->value === 'diterima')
                    <p class="mt-3 rounded-md bg-green-50 p-3 text-sm text-green-800">Selamat! HRD akan menghubungi Anda melalui email/WhatsApp untuk informasi akun dan jadwal masuk.</p>
                @endif
            </div>
        @endif
    </div>
@endsection
