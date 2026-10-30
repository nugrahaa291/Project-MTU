@extends('layouts.panel')
@section('judul', 'Absensi Saya')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-6">
            <h2 class="text-lg font-bold">{{ $karyawan->nama }}</h2>
            <p class="text-sm text-gray-500">{{ $karyawan->jabatan->nama }} &middot; NIP {{ $karyawan->nip }}</p>

            <div class="mt-5 rounded-lg bg-canvas p-4 text-sm">
                <p class="text-gray-500">Hari ini</p>
                @if ($hariIni)
                    <x-badge :warna="$hariIni->status->warna()" class="mt-1">{{ $hariIni->status->label() }}</x-badge>
                    <p class="mt-2">Masuk: <strong>{{ $hariIni->jam_masuk ? substr($hariIni->jam_masuk, 0, 5) : '-' }}</strong>
                       &middot; Pulang: <strong>{{ $hariIni->jam_pulang ? substr($hariIni->jam_pulang, 0, 5) : '-' }}</strong></p>
                @else
                    <p class="mt-1 font-medium">Belum ada absensi.</p>
                @endif
            </div>
            <a href="{{ route('pwa') }}" class="btn btn-primary mt-4 w-full">Buka Aplikasi Absensi</a>
            <p class="mt-3 text-xs text-gray-500">Absen masuk &amp; pulang (GPS + selfie) dilakukan lewat aplikasi absensi di HP Anda. Halaman ini hanya untuk melihat riwayat.</p>
        </div>

        <div class="table-wrap lg:col-span-2">
            <table class="table-base">
                <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($riwayat as $a)
                        <tr>
                            <td>{{ $a->tanggal->translatedFormat('D, d M Y') }}</td>
                            <td>{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-' }}@if ($a->menit_terlambat) <span class="text-xs text-amber-600">(+{{ $a->menit_terlambat }} mnt)</span>@endif</td>
                            <td>{{ $a->jam_pulang ? substr($a->jam_pulang, 0, 5) : '-' }}</td>
                            <td><x-badge :warna="$a->status->warna()">{{ $a->status->label() }}</x-badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-gray-500">Belum ada riwayat absensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $riwayat->links() }}</div>
@endsection
