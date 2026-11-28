@extends('layouts.panel')
@section('judul', 'Detail Lamaran')

@section('content')
    @php
        $status = $lamaran->status;
        $bisaPeriksa = ! $lamaran->lolos_berkas_pada && $status->bisaPindahKe(\App\Enums\StatusLamaran::Ditolak);
        $rute = request()->routeIs('admin.*') ? 'admin' : 'hrd';
    @endphp
    <a href="{{ route($rute.'.lamaran.index') }}" class="mb-3 inline-block text-sm text-gray-500 hover:text-brand">&larr; Daftar lamaran</a>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- DATA PELAMAR --}}
        <div class="space-y-6 xl:col-span-2">
            <div class="card p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold">{{ $lamaran->nama }}</h2>
                        <p class="font-mono text-xs text-gray-500">{{ $lamaran->kode }}</p>
                    </div>
                    <x-badge :warna="$status->warna()">{{ $status->label() }}</x-badge>
                </div>

                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'NIK' => $lamaran->nik,
                        'Email' => $lamaran->email,
                        'No. HP' => $lamaran->no_hp,
                        'Jenis kelamin' => $lamaran->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                        'Tempat, tgl lahir' => $lamaran->tempat_lahir.', '.$lamaran->tanggal_lahir->translatedFormat('d F Y'),
                        'Pendidikan' => $lamaran->pendidikan,
                        'Melamar' => $lamaran->lowongan->judul,
                        'Dikirim' => $lamaran->created_at->translatedFormat('d F Y, H:i'),
                    ] as $k => $v)
                        <div><dt class="text-gray-500">{{ $k }}</dt><dd class="font-medium">{{ $v }}</dd></div>
                    @endforeach
                    <div class="sm:col-span-2"><dt class="text-gray-500">Alamat</dt><dd class="font-medium">{{ $lamaran->alamat }}</dd></div>
                </dl>
            </div>

            @include('hrd.lamaran.pemeriksaan')
            @if ($lamaran->lolos_berkas_pada)
                @include('hrd.lamaran.penilaian')
            @endif

            @if ($lamaran->catatan || $lamaran->diputuskan_pada)
                <div class="card p-6 text-sm">
                    <h3 class="mb-1 font-bold">Keputusan akhir · {{ $status->label() }}</h3>
                    <p class="text-gray-500">{{ $lamaran->pengambilKeputusan?->name }} {{ $lamaran->diputuskan_pada?->translatedFormat('d F Y, H:i') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $lamaran->catatan ?: '-' }}</p>
                </div>
            @endif
        </div>

        {{-- AKSI --}}
        <aside class="space-y-4">
            <div class="card p-5 text-sm">
                <p class="text-gray-500">Kuota lowongan</p>
                <p class="text-2xl font-bold">{{ $lamaran->lowongan->diterima_count }} / {{ $lamaran->lowongan->kuota }}</p>
            </div>

            @if ($status === \App\Enums\StatusLamaran::Seleksi && $lamaran->dinilai_pada && $lamaran->rekomendasi_interview === 'lanjut')
                <form method="POST" action="{{ route($rute.'.lamaran.terima', $lamaran) }}" class="card space-y-3 border-green-200 p-5" data-konfirmasi="Terima pelamar ini dan buat akun karyawan?">
                    @csrf
                    <h3 class="font-bold text-green-700">Keputusan penerimaan</h3>
                    <p class="text-xs text-gray-500">Sistem otomatis membuat akun karyawan dengan PIN sementara.</p>
                    <x-field name="email" label="Email akun" type="email" :value="$lamaran->email" required />
                    <x-field name="tanggal_masuk" label="Tanggal masuk kerja" type="date" :value="now()->toDateString()" required />
                    <x-field name="catatan" label="Alasan penerimaan" as="textarea" maxlength="1000" hint="Jelaskan bagaimana kualifikasi, berkas, dan hasil interview mendukung penerimaan." required />
                    <button class="btn w-full bg-green-600 text-white hover:bg-green-700">Terima sebagai Karyawan</button>
                </form>
            @endif

            @if ($bisaPeriksa || ($status === \App\Enums\StatusLamaran::Seleksi && $lamaran->dinilai_pada))
                <form method="POST" action="{{ route($rute.'.lamaran.tolak', $lamaran) }}" class="card space-y-3 p-5" data-konfirmasi="Tolak lamaran ini? Keputusan tidak dapat dibatalkan.">
                    @csrf
                    <h3 class="font-bold text-red-700">Tolak</h3>
                    <x-field name="alasan_penolakan" label="Alasan penolakan (internal)" as="textarea" maxlength="1000" required />
                    <button class="btn btn-danger w-full">Tolak Lamaran</button>
                </form>
            @endif

            @if ($lamaran->karyawan)
                <a href="{{ route($rute.'.karyawan.show', $lamaran->karyawan) }}" class="btn btn-outline w-full">Lihat Data Karyawan &rarr;</a>
            @endif
        </aside>
    </div>
@endsection
