@extends('layouts.panel')
@section('judul', 'Edit Karyawan')

@section('content')
    <form method="POST" action="{{ route('hrd.karyawan.update', $karyawan) }}" class="card max-w-3xl space-y-4 p-6">
        @csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="nama" label="Nama Lengkap" :value="$karyawan->nama" required />
            <x-field name="no_hp" label="Nomor HP" :value="$karyawan->no_hp" required />
            <x-field name="jabatan_id" label="Jabatan" as="select" required>
                @foreach ($jabatan as $j)
                    <option value="{{ $j->id }}" @selected(old('jabatan_id', $karyawan->jabatan_id) == $j->id)>{{ $j->nama }}</option>
                @endforeach
            </x-field>
            <div>
                <span class="label">Email / NIK</span>
                <p class="py-2 text-sm text-gray-600">{{ $karyawan->user->email }} &middot; {{ $karyawan->nik }}</p>
            </div>
        </div>
        <x-field name="alamat" label="Alamat" as="textarea" rows="3" :value="$karyawan->alamat" />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $karyawan->aktif)) class="rounded border-gray-300 text-brand focus:ring-brand">
            Karyawan aktif <span class="text-gray-500">(menonaktifkan akan memutus login web &amp; aplikasi mobile)</span>
        </label>

        <div class="flex gap-2">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('hrd.karyawan.show', $karyawan) }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
@endsection
