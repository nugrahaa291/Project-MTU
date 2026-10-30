@extends('layouts.panel')
@section('judul', 'Dashboard HRD')

@section('content')
    @php
        $kartu = [
            ['Lowongan dibuka', $statistik['lowongan_dibuka'], route('hrd.lowongan.index')],
            ['Lamaran baru', $statistik['lamaran_baru'], route('hrd.lamaran.index', ['status' => 'diajukan'])],
            ['Dalam seleksi', $statistik['lamaran_seleksi'], route('hrd.lamaran.index', ['status' => 'seleksi'])],
            ['Karyawan aktif', $statistik['karyawan_aktif'], route('hrd.karyawan.index')],
            ['Sudah absen hari ini', $statistik['sudah_absen'], route('hrd.absensi.index')],
            ['Terlambat hari ini', $statistik['terlambat'], route('hrd.absensi.index', ['status' => 'terlambat'])],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($kartu as [$label, $angka, $url])
            <a href="{{ $url }}" class="card block border-l-4 border-l-brand p-5 transition hover:shadow-md">
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-bold">{{ $angka }}</p>
            </a>
        @endforeach
    </div>

    <h2 class="mt-8 mb-3 text-lg font-bold">Lamaran Terbaru</h2>
    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Pelamar</th><th>Lowongan</th><th>Status</th><th>Masuk</th><th></th></tr></thead>
            <tbody>
                @forelse ($lamaranTerbaru as $l)
                    <tr>
                        <td class="font-medium">{{ $l->nama }}</td>
                        <td>{{ $l->lowongan->judul }}</td>
                        <td><x-badge :warna="$l->status->warna()">{{ $l->status->label() }}</x-badge></td>
                        <td class="text-gray-500">{{ $l->created_at->diffForHumans() }}</td>
                        <td class="text-right"><a href="{{ route('hrd.lamaran.show', $l) }}" class="font-semibold text-brand">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-gray-500">Belum ada lamaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
