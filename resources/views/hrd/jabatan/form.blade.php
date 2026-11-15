@extends('layouts.panel')
@section('judul', $jabatan->exists ? 'Edit Jabatan' : 'Tambah Jabatan')

@section('content')
    @php $rute = request()->routeIs('admin.*') ? 'admin' : 'hrd'; @endphp
    <form method="POST" class="card max-w-xl space-y-4 p-6"
          action="{{ $jabatan->exists ? route($rute.'.jabatan.update', $jabatan) : route($rute.'.jabatan.store') }}">
        @csrf
        @if ($jabatan->exists) @method('PUT') @endif

        <x-field name="nama" label="Nama Jabatan" :value="$jabatan->nama" required />
        <x-field name="deskripsi" label="Deskripsi" as="textarea" rows="3" :value="$jabatan->deskripsi" />
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $jabatan->aktif)) class="rounded border-gray-300 text-brand focus:ring-brand">
            Aktif (bisa dipilih untuk lowongan baru)
        </label>

        <div class="flex gap-2">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route($rute.'.jabatan.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
@endsection
