@extends('layouts.panel')
@section('judul', 'Edit Pengguna: ' . $user->name)

@section('content')
    <div class="max-w-2xl">
        <div class="mb-4">
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-brand hover:underline">&larr; Kembali ke Daftar Pengguna</a>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card space-y-5 p-6">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-bold text-gray-900">Perbarui Data Pengguna</h2>

            <x-field name="name" label="Nama Lengkap" :value="$user->name" required />

            <x-field name="email" label="Alamat Email" type="email" :value="$user->email" required />

            <div>
                <label for="role" class="mb-1 block text-sm font-semibold text-gray-700">Peran Pengguna (Role) <span class="text-red-500">*</span></label>
                @if ($user->karyawan()->exists())
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                    <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-700 border border-gray-200">
                        <span class="font-bold">{{ $user->role->label() }}</span> (Terkunci karena akun ini terikat dengan data Karyawan: NIP {{ $user->karyawan->nip }})
                    </div>
                @elseif ($user->id === auth()->id())
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                    <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-700 border border-gray-200">
                        <span class="font-bold">{{ $user->role->label() }}</span> (Akun Anda sendiri tidak dapat diubah perannya)
                    </div>
                @else
                    <select name="role" id="role" class="input w-full" required>
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}" @selected(old('role', $user->role->value) === $r->value)>
                                {{ $r->label() }} ({{ $r->value }})
                            </option>
                        @endforeach
                    </select>
                @endif
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 space-y-1">
                <p><span class="font-semibold">Status Akun:</span> {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                <p><span class="font-semibold">Keharusan Ganti Password:</span> {{ $user->must_change_password ? 'Ya (Belum ganti password)' : 'Tidak (Normal)' }}</p>
                <p><span class="font-semibold">Terdaftar Sejak:</span> {{ $user->created_at->translatedFormat('d F Y H:i') }}</p>
            </div>

            <div class="flex items-center gap-3 pt-3">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
