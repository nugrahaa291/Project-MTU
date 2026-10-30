@extends('layouts.panel')
@section('judul', 'Tambah Karyawan')

@section('content')
    <form method="POST" action="{{ route('hrd.karyawan.store') }}" class="card max-w-3xl space-y-4 p-6">
        @csrf
        <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
            Untuk pegawai lama yang tidak melalui portal lamaran. Karyawan baru dari lamaran dibuat otomatis saat pelamar diterima.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="nama" label="Nama Lengkap" required />
            <x-field name="email" label="Email (untuk login)" type="email" required />
            <x-field name="nik" label="NIK (16 digit)" inputmode="numeric" maxlength="16" required />
            <x-field name="no_hp" label="Nomor HP" required />
            <x-field name="jabatan_id" label="Jabatan" as="select" required>
                <option value="">Pilih...</option>
                @foreach ($jabatan as $j)
                    <option value="{{ $j->id }}" @selected(old('jabatan_id') == $j->id)>{{ $j->nama }}</option>
                @endforeach
            </x-field>
            <x-field name="tanggal_masuk" label="Tanggal Masuk" type="date" :value="now()->toDateString()" required />
        </div>
        <x-field name="alamat" label="Alamat" as="textarea" rows="3" />
        <div class="flex gap-2">
            <button class="btn btn-primary">Simpan &amp; Buat Akun</button>
            <a href="{{ route('hrd.karyawan.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
@endsection
