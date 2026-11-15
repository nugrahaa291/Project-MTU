@extends('layouts.panel')
@section('judul', 'Dashboard Admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Dashboard Utama Administrator</h2>
            <p class="text-sm text-gray-500">Kelola seluruh modul sistem: rekrutmen, karyawan, absensi, data master, serta otorisasi pengguna.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Tambah Pengguna</a>
        </div>
    </div>

    {{-- KARTU OPERASIONAL (FITUR ADMIN HRD SEBELUMNYA) --}}
    <h3 class="mb-3 text-xs font-semibold tracking-wider text-gray-400 uppercase">Statistik Operasional Perusahaan</h3>
    @php
        $kartuOperasional = [
            ['Lowongan dibuka', $statistik['lowongan_dibuka'], route('admin.lowongan.index'), 'border-l-brand'],
            ['Lamaran baru', $statistik['lamaran_baru'], route('admin.lamaran.index', ['status' => 'diajukan']), 'border-l-blue-500'],
            ['Dalam seleksi', $statistik['lamaran_seleksi'], route('admin.lamaran.index', ['status' => 'seleksi']), 'border-l-amber-500'],
            ['Karyawan aktif', $statistik['karyawan_aktif'], route('admin.karyawan.index'), 'border-l-emerald-600'],
            ['Sudah absen hari ini', $statistik['sudah_absen'], route('admin.absensi.index'), 'border-l-teal-600'],
            ['Terlambat hari ini', $statistik['terlambat'], route('admin.absensi.index', ['status' => 'terlambat']), 'border-l-rose-500'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($kartuOperasional as [$label, $angka, $url, $border])
            <a href="{{ $url }}" class="card block border-l-4 {{ $border }} p-5 transition hover:shadow-md">
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $angka }}</p>
            </a>
        @endforeach
    </div>

    {{-- KARTU PENGELOLAAN SISTEM --}}
    <h3 class="mt-8 mb-3 text-xs font-semibold tracking-wider text-gray-400 uppercase">Statistik Akun & Hak Akses</h3>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.users.index') }}" class="card block p-4 transition hover:shadow-md">
            <p class="text-xs text-gray-500 uppercase">Total Pengguna</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $statistik['total_user_aktif'] + $statistik['total_user_nonaktif'] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $statistik['total_user_aktif'] }} aktif &bull; {{ $statistik['total_user_nonaktif'] }} nonaktif</p>
        </a>

        <a href="{{ route('admin.users.index', ['role' => 'admin']) }}" class="card block p-4 transition hover:shadow-md">
            <p class="text-xs text-gray-500 uppercase">Administrator</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $statistik['total_admin'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Pengelola penuh sistem</p>
        </a>

        <a href="{{ route('admin.users.index', ['role' => 'hrd']) }}" class="card block p-4 transition hover:shadow-md">
            <p class="text-xs text-gray-500 uppercase">Akun HRD</p>
            <p class="mt-1 text-2xl font-bold text-brand">{{ $statistik['total_hrd'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Seleksi & monitoring karyawan</p>
        </a>

        <a href="{{ route('admin.users.index', ['role' => 'karyawan']) }}" class="card block p-4 transition hover:shadow-md">
            <p class="text-xs text-gray-500 uppercase">Akun Karyawan</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $statistik['total_karyawan'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Akses PWA mobile & web</p>
        </a>
    </div>

    {{-- TABEL LAMARAN TERBARU & PENGGUNA TERBARU --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        {{-- LAMARAN TERBARU --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-bold text-gray-900">Lamaran Masuk Terbaru</h3>
                <a href="{{ route('admin.lamaran.index') }}" class="text-xs font-semibold text-brand hover:underline">Lihat Semua &rarr;</a>
            </div>
            <div class="table-wrap">
                <table class="table-base">
                    <thead><tr><th>Pelamar</th><th>Lowongan</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($lamaranTerbaru as $l)
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-900">{{ $l->nama }}</p>
                                    <p class="text-xs text-gray-400">{{ $l->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="text-xs text-gray-600">{{ $l->lowongan->judul }}</td>
                                <td><x-badge :warna="$l->status->warna()">{{ $l->status->label() }}</x-badge></td>
                                <td class="text-right">
                                    <a href="{{ route('admin.lamaran.show', $l) }}" class="font-semibold text-brand text-xs">Buka &rarr;</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500">Belum ada lamaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- PENGGUNA TERBARU --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-bold text-gray-900">Akun Pengguna Terdaftar</h3>
                <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-brand hover:underline">Kelola Pengguna &rarr;</a>
            </div>
            <div class="table-wrap">
                <table class="table-base">
                    <thead><tr><th>Nama & Email</th><th>Peran</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($penggunaTerbaru as $u)
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-900">{{ $u->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $u->email }}</p>
                                </td>
                                <td><span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $u->role->label() }}</span></td>
                                <td>
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $u->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                        {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="font-semibold text-brand text-xs">Edit &rarr;</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-sm text-gray-500">Belum ada pengguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
