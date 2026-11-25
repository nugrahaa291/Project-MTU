@extends('layouts.panel')
@section('judul', $lowongan->exists ? 'Edit Lowongan' : 'Buat Lowongan')

@section('content')
    <form method="POST" class="card max-w-3xl space-y-4 p-6"
          action="{{ $lowongan->exists ? route('hrd.lowongan.update', $lowongan) : route('hrd.lowongan.store') }}">
        @csrf
        @if ($lowongan->exists) @method('PUT') @endif

            <x-field name="judul" label="Unit" as="select" data-unit-pilih hint="Dokumen yang diminta dari pelamar menyesuaikan unit yang dipilih." required>
            <option value="">Pilih unit...</option>
            @foreach (array_keys(config('dokumen_lamaran.unit')) as $unit)
            <option value="{{ $unit }}" @selected(old('judul', $lowongan->judul) === $unit)>{{ $unit }}</option>
            @endforeach
            {{-- Lowongan lama yang judulnya bukan unit (mis. Staff IT) tetap bisa diedit --}}
            @if ($lowongan->exists && ! array_key_exists($lowongan->judul, config('dokumen_lamaran.unit')))
                <option value="{{ $lowongan->judul }}" @selected(old('judul', $lowongan->judul) === $lowongan->judul)>{{ $lowongan->judul }} (judul lama)</option>
            @endif
        </x-field>
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
                                     @php
            $paketUnit = config('dokumen_lamaran.unit');
            // Lowongan lama (judul bukan unit) menampilkan dokumen sesuai paketnya sendiri
            if ($lowongan->exists && ! array_key_exists($lowongan->judul, $paketUnit)) {
                $paketUnit[$lowongan->judul] = $lowongan->paket_dokumen ?: 'umum';
            }
        @endphp
        <div class="rounded-lg border border-line bg-gray-50 p-4">
            <p class="label">Dokumen yang Dipersiapkan</p>
            <p class="mb-3 text-xs text-gray-500">Otomatis mengikuti unit yang dipilih. Inilah dokumen yang diminta dari pelamar.</p>
            <p data-unit-kosong class="text-sm text-gray-500">Pilih unit untuk melihat daftar dokumen.</p>

            @foreach ($paketUnit as $nama => $paket)
            <ul data-unit-dokumen="{{ $nama }}" class="hidden space-y-1.5 text-sm">
                @foreach ((new \App\Models\Lowongan(['paket_dokumen' => $paket]))->daftarDokumen() as $d)
                <li class="flex gap-2">
                    <x-ikon nama="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand" />
                    <span>
                        {{ $d['label'] }}
                        @if ($d['wajib'])
                        <span class="font-bold text-red-600">*</span>
                        @else
                        <span class="text-gray-400">(opsional)</span>
                        @endif
                    </span>
                </li>
                @endforeach
            </ul>
            @endforeach
            <p class="mt-3 text-xs text-gray-500"><span class="font-bold text-red-600">*</span> wajib diisi pelamar.</p>
        </div>
        <x-field name="persyaratan" label="Persyaratan" as="textarea" rows="5" :value="$lowongan->persyaratan" hint="Satu persyaratan per baris." />
            <div class="flex gap-2">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('hrd.lowongan.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
        @endsection
