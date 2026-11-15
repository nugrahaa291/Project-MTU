@extends('layouts.panel')
@section('judul', 'Rekap Absensi')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Rekapitulasi Absensi & Driver MTU</h2>
            <p class="text-sm text-gray-500">Pantau kehadiran, keterlambatan, hari kerja, dan jadwal libur driver secara terintegrasi.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-outline btn-sm">
                &darr; Unduh Excel
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline btn-sm">
                &darr; Unduh CSV
            </a>
        </div>
    </div>

    {{-- FILTER CEPAT & RENTANG TANGGAL --}}
    <div class="card mb-6 p-4">
        {{-- TOMBOL QUICK PERIOD --}}
        <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-gray-100 pb-3">
            <span class="text-xs font-semibold uppercase text-gray-400">Periode Cepat:</span>
            <a href="{{ request()->fullUrlWithQuery(['quick' => 'hari_ini', 'dari' => null, 'sampai' => null]) }}"
               class="rounded px-2.5 py-1 text-xs font-medium {{ ($quick === 'hari_ini' || ($dari === $hariIni && $sampai === $hariIni && $quick !== 'custom')) ? 'bg-brand text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Hari Ini
            </a>
            <a href="{{ request()->fullUrlWithQuery(['quick' => '7_hari', 'dari' => null, 'sampai' => null]) }}"
               class="rounded px-2.5 py-1 text-xs font-medium {{ $quick === '7_hari' ? 'bg-brand text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                7 Hari Terakhir
            </a>
            <a href="{{ request()->fullUrlWithQuery(['quick' => 'bulan_ini', 'dari' => null, 'sampai' => null]) }}"
               class="rounded px-2.5 py-1 text-xs font-medium {{ $quick === 'bulan_ini' ? 'bg-brand text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Bulan Ini
            </a>
            <a href="{{ request()->fullUrlWithQuery(['quick' => 'bulan_lalu', 'dari' => null, 'sampai' => null]) }}"
               class="rounded px-2.5 py-1 text-xs font-medium {{ $quick === 'bulan_lalu' ? 'bg-brand text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Bulan Sebelumnya
            </a>
        </div>

        {{-- FORM FILTER UTAMA --}}
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" name="dari" value="{{ $dari }}" class="input w-full text-xs">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="input w-full text-xs">
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Cari Karyawan / Driver</label>
                <input type="text" name="cari" value="{{ request('cari') }}" placeholder="Cari Nama, NIP, atau NIK..." class="input w-full text-xs">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Proyek Penugasan</label>
                <select name="proyek" class="input w-full text-xs">
                    <option value="">Semua Proyek</option>
                    @foreach ($daftarProyek as $p)
                        <option value="{{ $p }}" {{ request('proyek') === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Status Kehadiran</label>
                <select name="status" class="input w-full text-xs">
                    <option value="">Semua Status</option>
                    <option value="hadir" {{ request('status') === 'hadir' ? 'selected' : '' }}>Hadir</option>
                    <option value="terlambat" {{ request('status') === 'terlambat' ? 'selected' : '' }}>Terlambat</option>
                    <option value="izin" {{ request('status') === 'izin' ? 'selected' : '' }}>Izin</option>
                    <option value="sakit" {{ request('status') === 'sakit' ? 'selected' : '' }}>Sakit</option>
                    <option value="cuti" {{ request('status') === 'cuti' ? 'selected' : '' }}>Cuti</option>
                    <option value="libur_terjadwal" {{ request('status') === 'libur_terjadwal' ? 'selected' : '' }}>Libur Terjadwal</option>
                    <option value="alpa" {{ request('status') === 'alpa' ? 'selected' : '' }}>Alpa</option>
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-6 flex items-center justify-between pt-2">
                <div class="text-xs text-gray-500">
                    Menampilkan periode: <strong>{{ \Carbon\Carbon::parse($dari)->translatedFormat('d M Y') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($sampai)->translatedFormat('d M Y') }}</strong>
                    ({{ count($baris) }} data ditemukan)
                </div>
                <div class="flex gap-2">
                    <a href="{{ url()->current() }}" class="btn btn-outline btn-sm">Reset Filter</a>
                    <button type="submit" class="btn btn-dark btn-sm">Tampilkan</button>
                </div>
            </div>
        </form>
    </div>

    {{-- TABEL HASIL REKAP --}}
    <div class="table-wrap">
        <table class="table-base">
            <thead>
                <tr>
                    <th class="w-12 text-center">No</th>
                    <th>Nama & Identitas</th>
                    <th>Proyek</th>
                    <th class="text-center font-bold text-gray-800">Hari Kerja Terjadwal</th>
                    <th class="text-center text-emerald-700">Hadir</th>
                    <th class="text-center text-amber-700">Terlambat</th>
                    <th class="text-center text-blue-700">Izin</th>
                    <th class="text-center text-indigo-700">Sakit</th>
                    <th class="text-center text-cyan-700">Cuti</th>
                    <th class="text-center text-purple-700">Libur Terjadwal</th>
                    <th class="text-center text-rose-700">Alpa</th>
                    <th class="text-center">Total Telat (Mnt)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $idx => $b)
                    <tr>
                        <td class="text-center text-xs text-gray-400">{{ $idx + 1 }}</td>
                        <td>
                            <p class="font-medium text-gray-900">{{ $b['karyawan']->nama }}</p>
                            <p class="font-mono text-xs text-gray-500">NIP: {{ $b['karyawan']->nip ?? '-' }} &bull; NIK: {{ $b['karyawan']->nik }}</p>
                        </td>
                        <td>
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                {{ $b['proyek'] }}
                            </span>
                        </td>
                        <td class="text-center font-bold text-gray-800">{{ $b['hari_kerja_terjadwal'] }}</td>
                        <td class="text-center font-semibold text-emerald-700">{{ $b['hadir'] ?: '-' }}</td>
                        <td class="text-center font-semibold text-amber-700">{{ $b['terlambat'] ?: '-' }}</td>
                        <td class="text-center text-blue-700">{{ $b['izin'] ?: '-' }}</td>
                        <td class="text-center text-indigo-700">{{ $b['sakit'] ?: '-' }}</td>
                        <td class="text-center text-cyan-700">{{ $b['cuti'] ?: '-' }}</td>
                        <td class="text-center font-semibold text-purple-700">{{ $b['libur_terjadwal'] ?: '-' }}</td>
                        <td class="text-center font-semibold text-rose-700">{{ $b['alpa'] ?: '-' }}</td>
                        <td class="text-center text-xs text-gray-600">{{ $b['menit_terlambat'] ? $b['menit_terlambat'].' mnt' : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="py-8 text-center text-gray-500">
                            Tidak ada data absensi untuk filter dan periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between text-xs text-gray-500 gap-2">
        <p>&bull; <strong>Libur Terjadwal:</strong> Driver yang dijadwalkan libur kantor pusat tidak dihitung sebagai Alpa.</p>
        <p>&bull; <strong>Zona Waktu:</strong> Menggunakan WIB (Asia/Jakarta).</p>
    </div>
@endsection
