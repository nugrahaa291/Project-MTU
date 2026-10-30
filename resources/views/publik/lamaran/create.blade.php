@extends('layouts.publik')
@section('judul', 'Lamar: '.$lowongan->judul)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        <a href="{{ route('lowongan.show', $lowongan) }}" class="text-sm text-gray-500 hover:text-brand">&larr; Kembali ke detail lowongan</a>
        <h1 class="mt-2 text-2xl font-bold">Form Lamaran</h1>
        <p class="mb-6 text-gray-600">{{ $lowongan->judul }} &middot; {{ $lowongan->jabatan->nama }}</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Mohon periksa kembali isian Anda. Ada {{ $errors->count() }} kesalahan (ditandai merah di bawah).
            </div>
        @endif

        <form method="POST" action="{{ route('lamaran.store', $lowongan) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <fieldset class="card space-y-4 p-6">
                <legend class="px-1 text-base font-bold">Data Diri</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="nik" label="NIK (16 digit)" inputmode="numeric" maxlength="16" required />
                    <x-field name="nama" label="Nama Lengkap" required />
                    <x-field name="email" label="Email" type="email" hint="Dipakai untuk akun karyawan bila Anda diterima." required />
                    <x-field name="no_hp" label="Nomor HP / WhatsApp" placeholder="081234567890" inputmode="tel" required />
                    <x-field name="jenis_kelamin" label="Jenis Kelamin" as="select" required>
                        <option value="">Pilih...</option>
                        <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                    </x-field>
                    <x-field name="pendidikan" label="Pendidikan Terakhir" as="select" required>
                        <option value="">Pilih...</option>
                        @foreach (\App\Models\Lamaran::PENDIDIKAN as $p)
                            <option value="{{ $p }}" @selected(old('pendidikan') === $p)>{{ $p }}</option>
                        @endforeach
                    </x-field>
                    <x-field name="tempat_lahir" label="Tempat Lahir" required />
                    <x-field name="tanggal_lahir" label="Tanggal Lahir" type="date" required />
                </div>
                <x-field name="alamat" label="Alamat Lengkap" as="textarea" rows="3" required resize/>
            </fieldset>

            <fieldset class="card space-y-4 p-6">
                <legend class="px-1 text-base font-bold">Berkas</legend>
                <p class="text-sm text-gray-500">Berkas disimpan aman dan hanya dapat dilihat oleh HRD.</p>
                <x-field name="cv" label="CV" type="file" accept=".pdf,.doc,.docx" hint="PDF/DOC/DOCX, maks. 3 MB." required />
                <x-field name="pas_foto" label="Pas Foto" type="file" accept=".jpg,.jpeg,.png" hint="JPG/PNG, maks. 2 MB." required />
                <x-field name="ktp" label="KTP" type="file" accept=".jpg,.jpeg,.png,.pdf" hint="JPG/PNG/PDF, maks. 2 MB." required />
                <x-field name="ijazah" label="Ijazah (opsional)" type="file" accept=".jpg,.jpeg,.png,.pdf" hint="JPG/PNG/PDF, maks. 3 MB." />
            </fieldset>

            <button class="btn btn-primary w-full py-3 text-base">Kirim Lamaran</button>
        </form>
    </div>
@endsection
