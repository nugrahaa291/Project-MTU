@extends('layouts.panel')
@section('judul', 'Monitoring Sistem')

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-900">Kesehatan & Status Operasional</h2>
        <p class="text-sm text-gray-500">Informasi kesehatan server, konektivitas database, sesi aktif, dan audit aktivitas administratif.</p>
    </div>

    {{-- KARTU STATUS TEKNIS SERVER --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-5 border-l-4 border-l-emerald-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Koneksi Database</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $ringkasan['db_status'] }}</p>
            <p class="mt-2 text-xs text-gray-500 font-mono">{{ $ringkasan['db_version'] }}</p>
        </div>

        <div class="card p-5 border-l-4 border-l-blue-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Lingkungan Runtime</p>
            <p class="mt-1 text-xl font-bold text-gray-900">PHP {{ $ringkasan['php_version'] }}</p>
            <p class="mt-2 text-xs text-gray-500">Laravel {{ $ringkasan['laravel_version'] }} ({{ $ringkasan['app_env'] }})</p>
        </div>

        <div class="card p-5 border-l-4 border-l-indigo-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Ruang Penyimpanan</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $diskInfo['free'] }} <span class="text-xs font-normal text-gray-500">bebas</span></p>
            <p class="mt-2 text-xs text-gray-500">Total: {{ $diskInfo['total'] }} ({{ $diskInfo['persen_terpakai'] }}% terpakai)</p>
        </div>

        <div class="card p-5 border-l-4 border-l-amber-500">
            <p class="text-xs font-semibold text-gray-500 uppercase">Sesi & Token</p>
            <p class="mt-1 text-xl font-bold text-gray-900">{{ $ringkasan['total_sesi'] }} Sesi</p>
            <p class="mt-2 text-xs text-gray-500">{{ $ringkasan['total_token_aktif'] }} token API Sanctum aktif</p>
        </div>
    </div>

    {{-- RINGKASAN DATA OPERASIONAL --}}
    <div class="mt-6 card p-5">
        <h3 class="font-bold text-gray-900 mb-3">Statistik Entitas Sistem Aktual</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Total Pengguna</p>
                <p class="text-2xl font-bold text-gray-900">{{ $ringkasan['total_pengguna'] }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Admin / HRD</p>
                <p class="text-2xl font-bold text-gray-900">{{ $ringkasan['total_admin'] }} / {{ $ringkasan['total_hrd'] }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Akun Karyawan</p>
                <p class="text-2xl font-bold text-gray-900">{{ $ringkasan['total_karyawan'] }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Pengguna Aktif</p>
                <p class="text-2xl font-bold text-emerald-600">{{ $ringkasan['user_aktif'] }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Total Absensi</p>
                <p class="text-2xl font-bold text-gray-900">{{ $ringkasan['total_absensi'] }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-xs text-gray-500">Total Lamaran</p>
                <p class="text-2xl font-bold text-gray-900">{{ $ringkasan['total_lamaran'] }}</p>
            </div>
        </div>
    </div>

    {{-- LOG AKTIVITAS ADMINISTRATIF --}}
    <div class="mt-8">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Audit Log Aktivitas Riil</h3>
                <p class="text-xs text-gray-500">Semua aksi administratif dan otentikasi tercatat di sini secara transparan.</p>
            </div>
            <form method="GET" class="flex gap-2">
                <select name="aksi" class="input w-48">
                    <option value="">Semua Aksi</option>
                    <option value="login" @selected(request('aksi') === 'login')>Login Web</option>
                    <option value="login_api" @selected(request('aksi') === 'login_api')>Login API Mobile</option>
                    <option value="logout" @selected(request('aksi') === 'logout')>Logout</option>
                    <option value="buat_pengguna" @selected(request('aksi') === 'buat_pengguna')>Tambah Pengguna</option>
                    <option value="ubah_pengguna" @selected(request('aksi') === 'ubah_pengguna')>Ubah Pengguna</option>
                    <option value="aktifkan_pengguna" @selected(request('aksi') === 'aktifkan_pengguna')>Aktifkan Pengguna</option>
                    <option value="nonaktifkan_pengguna" @selected(request('aksi') === 'nonaktifkan_pengguna')>Nonaktifkan Pengguna</option>
                    <option value="reset_password" @selected(request('aksi') === 'reset_password')>Reset Password</option>
                    <option value="ganti_password" @selected(request('aksi') === 'ganti_password')>Ganti Password Sendiri</option>
                    <option value="ubah_pengaturan" @selected(request('aksi') === 'ubah_pengaturan')>Ubah Pengaturan</option>
                </select>
                <button class="btn btn-dark">Filter</button>
                @if (request('aksi'))
                    <a href="{{ route('admin.monitoring') }}" class="btn btn-outline">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Deskripsi</th>
                        <th>IP & Info Perangkat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-xs text-gray-600 font-mono">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td>
                                @if ($log->user)
                                    <p class="font-medium text-gray-900">{{ $log->user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $log->user->role->label() }}</p>
                                @else
                                    <span class="text-xs text-gray-400 italic">Sistem / Tamu</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $warnaAksi = match ($log->action) {
                                        'login', 'login_api' => 'blue',
                                        'buat_pengguna' => 'green',
                                        'ubah_pengguna', 'ubah_pengaturan' => 'purple',
                                        'aktifkan_pengguna' => 'green',
                                        'nonaktifkan_pengguna' => 'red',
                                        'reset_password', 'ganti_password' => 'amber',
                                        default => 'slate',
                                    };
                                @endphp
                                <x-badge :warna="$warnaAksi">{{ str_replace('_', ' ', $log->action) }}</x-badge>
                            </td>
                            <td class="text-sm text-gray-800">{{ $log->description }}</td>
                            <td class="text-xs text-gray-500">
                                <p class="font-mono">{{ $log->ip_address ?? '-' }}</p>
                                @if ($log->user_agent)
                                    <p class="text-[10px] text-gray-400 truncate max-w-xs" title="{{ $log->user_agent }}">{{ $log->user_agent }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-500">Belum ada riwayat aktivitas yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
@endsection
