@extends('layouts.panel')
@section('judul', 'Tambah Pengguna Baru')

@section('content')
    <div class="max-w-2xl">
        <div class="mb-4">
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-brand hover:underline">&larr; Kembali ke Daftar Pengguna</a>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="card space-y-5 p-6">
            @csrf

            <h2 class="text-lg font-bold text-gray-900">Form Akun Pengguna</h2>

            <x-field name="name" label="Nama Lengkap" placeholder="Masukkan nama pengguna" required />

            <x-field name="email" label="Alamat Email" type="email" placeholder="contoh: user@portal-pt.test" required />

            <div>
                <label for="role" class="mb-1 block text-sm font-semibold text-gray-700">Peran Pengguna (Role) <span class="text-red-500">*</span></label>
                <select name="role" id="role" class="input w-full" required>
                    @foreach ($roles as $r)
                        <option value="{{ $r->value }}" @selected(old('role') === $r->value)>
                            {{ $r->label() }} ({{ $r->value }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Administrator: kontrol penuh sistem &bull; HRD: kelola rekrutmen & absensi &bull; Karyawan: akun pegawai.
                </p>
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <x-field name="password" label="Password Sementara (Opsional)" type="password" placeholder="Kosongkan untuk membuat password acak otomatis" />
            <p class="-mt-3 text-xs text-gray-500">Jika dikosongkan, sistem akan mengenerate password acak 10 karakter dan mewajibkan pengguna menggantinya saat login pertama.</p>

            <div class="flex items-center gap-3 pt-3">
                <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
