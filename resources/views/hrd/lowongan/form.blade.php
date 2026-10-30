@extends('layouts.panel')
@section('judul', $lowongan->exists ? 'Edit Lowongan' : 'Buat Lowongan')

@section('content')
    <form method="POST" class="card max-w-3xl space-y-4 p-6"
          action="{{ $lowongan->exists ? route('hrd.lowongan.update', $lowongan) : route('hrd.lowongan.store') }}">
        @csrf
        @if ($lowongan->exists) @method('PUT') @endif

        <x-field name="judul" label="Judul Lowongan" :value="$lowongan->judul" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="jabatan_id" label="Jabatan" as="select" hint="Jabatan otomatis dipakai saat pelamar diterima." required>
                <option value="">Pilih...</option>
                @foreach ($jabatan as $j)
                    <option value="{{ $j->id }}" @selected(old('jabatan_id', $lowongan->jabatan_id) == $j->id)>{{ $j->nama }}</option>
                @endforeach
            </x-field>
            <x-field name="lokasi" label="Lokasi Penempatan" :value="$lowongan->lokasi" required />
            <x-field name="kuota" label="Kuota" type="number" min="1" :value="$lowongan->kuota" required />
            <x-field name="status" label="Status" as="select" required>
                @foreach (\App\Enums\StatusLowongan::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $lowongan->status?->value) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </x-field>
            <x-field name="tanggal_buka" label="Tanggal Dibuka" type="date" :value="$lowongan->tanggal_buka?->toDateString()" required />
            <x-field name="tanggal_tutup" label="Tanggal Ditutup" type="date" :value="$lowongan->tanggal_tutup?->toDateString()" required />
        </div>
        <x-field name="deskripsi" label="Deskripsi Pekerjaan" as="textarea" rows="5" :value="$lowongan->deskripsi" />
        <x-field name="persyaratan" label="Persyaratan" as="textarea" rows="5" :value="$lowongan->persyaratan" hint="Satu persyaratan per baris." />

        <div class="flex gap-2">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('hrd.lowongan.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
@endsection
