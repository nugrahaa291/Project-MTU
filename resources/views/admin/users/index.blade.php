@extends('layouts.panel')
@section('judul', 'Manajemen Pengguna')

@section('content')
    @if (session('password_sementara'))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
            <p class="font-bold">Password sementara</p>
            <p class="mt-1 font-mono text-lg font-bold select-all">{{ session('password_sementara') }}</p>
            <p class="mt-1 text-xs text-amber-700">Salin password ini sekarang dan sampaikan kepada pengguna.</p>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" data-ajax-filter data-ajax-target="#ajax-results" class="flex flex-wrap items-center gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Cari nama atau email..." class="input w-64">
            <select name="role" class="input w-40">
                <option value="">Semua Peran</option>
                <option value="admin" @selected(request('role') === 'admin')>Administrator</option>
                <option value="hrd" @selected(request('role') === 'hrd')>HRD</option>
                <option value="karyawan" @selected(request('role') === 'karyawan')>Karyawan</option>
            </select>
            <select name="status" class="input w-36">
                <option value="">Semua Status</option>
                <option value="1" @selected(request('status') === '1')>Aktif</option>
                <option value="0" @selected(request('status') === '0')>Nonaktif</option>
            </select>
            @if (request()->hasAny(['q', 'role', 'status']))
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Reset</a>
            @endif
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Tambah Pengguna</a>
    </div>

    <div id="ajax-results">
    <div class="table-wrap">
        <table class="table-base">
            <thead>
                <tr>
                    <th>Pengguna</th>
                    <th>Peran</th>
                    <th>Profil Terikat</th>
                    <th>Status Akun</th>
                    <th>Keamanan</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <p class="font-medium text-gray-900">{{ $u->name }}</p>
                            <p class="text-xs text-gray-500">{{ $u->email }}</p>
                        </td>
                        <td>
                            @php
                                $warnaRole = match ($u->role->value) {
                                    'admin' => 'purple',
                                    'hrd' => 'blue',
                                    'karyawan' => 'green',
                                    default => 'slate',
                                };
                            @endphp
                            <x-badge :warna="$warnaRole">{{ $u->role->label() }}</x-badge>
                        </td>
                        <td class="text-xs text-gray-600">
                            @if ($u->karyawan)
                                <span class="font-medium">NIP: {{ $u->karyawan->nip }}</span>
                                <p class="text-[11px] text-gray-400">Pegawai Terdaftar</p>
                            @else
                                <span class="text-gray-400">Akun Sistem</span>
                            @endif
                        </td>
                        <td>
                            <x-badge :warna="$u->is_active ? 'green' : 'slate'">
                                {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                            </x-badge>
                        </td>
                        <td>
                            @if ($u->must_change_password)
                                <x-badge warna="amber">Wajib Ganti Password</x-badge>
                            @else
                                <span class="text-xs text-gray-500">Normal</span>
                            @endif
                        </td>
                        <td class="space-x-1 text-right whitespace-nowrap">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-outline btn-sm">Edit</a>

                            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="inline" data-konfirmasi="Reset password untuk {{ $u->name }}?">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm">Reset password</button>
                            </form>

                            @if ($u->id === auth()->id())
                                <span class="inline-block px-2 text-xs text-gray-400 italic">Akun Anda</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.toggle-status', $u) }}" class="inline" data-konfirmasi="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $u->name }}?">
                                    @csrf
                                    <button type="submit" class="btn {{ $u->is_active ? 'btn-danger' : 'btn-primary' }} btn-sm">
                                        {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-500">Tidak ada pengguna yang cocok dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
    </div>
@endsection
