@extends('layouts.panel')
@section('judul', 'Detail Lamaran')

@section('content')
    @php $status = $lamaran->status; @endphp
    <a href="{{ route('hrd.lamaran.index') }}" class="mb-3 inline-block text-sm text-gray-500 hover:text-brand">&larr; Daftar lamaran</a>

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

            <div class="card p-6">
                <h3 class="mb-3 font-bold">Berkas</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Lamaran::DOKUMEN as $kolom => $label)
                        @if ($lamaran->{$kolom})
                            <a href="{{ route('hrd.lamaran.dokumen', [$lamaran, $kolom]) }}" target="_blank" class="btn btn-outline btn-sm">{{ $label }} &nearr;</a>
                        @else
                            <span class="rounded-md border border-dashed border-gray-300 px-3 py-1.5 text-xs text-gray-400">{{ $label }} (tidak ada)</span>
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($lamaran->catatan || $lamaran->diputuskan_pada)
                <div class="card p-6 text-sm">
                    <h3 class="mb-1 font-bold">Keputusan</h3>
                    <p class="text-gray-500">{{ $lamaran->diputuskan_pada?->translatedFormat('d F Y, H:i') }}</p>
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

            @if ($status === \App\Enums\StatusLamaran::Diajukan)
                <form method="POST" action="{{ route('hrd.lamaran.proses', $lamaran) }}" class="card p-5">
                    @csrf
                    <p class="mb-3 text-sm text-gray-600">Berkas sudah ditinjau dan layak lanjut ke tahap seleksi?</p>
                    <button class="btn btn-primary w-full">Proses ke Seleksi</button>
                </form>
            @endif

            @if ($status === \App\Enums\StatusLamaran::Seleksi)
                <form method="POST" action="{{ route('hrd.lamaran.terima', $lamaran) }}" class="card space-y-3 border-green-200 p-5" data-konfirmasi="Terima pelamar ini dan buat akun karyawan?">
                    @csrf
                    <h3 class="font-bold text-green-700">Terima (Hired)</h3>
                    <p class="text-xs text-gray-500">Sistem otomatis membuat akun karyawan dengan password sementara.</p>
                    <x-field name="email" label="Email akun" type="email" :value="$lamaran->email" required />
                    <x-field name="tanggal_masuk" label="Tanggal masuk kerja" type="date" :value="now()->toDateString()" required />
                    <x-field name="catatan" label="Catatan (opsional)" as="textarea" rows="2" />
                    <button class="btn w-full bg-green-600 text-white hover:bg-green-700">Terima &amp; Buat Akun</button>
                </form>
            @endif

            @if (in_array($status, [\App\Enums\StatusLamaran::Diajukan, \App\Enums\StatusLamaran::Seleksi]))
                <form method="POST" action="{{ route('hrd.lamaran.tolak', $lamaran) }}" class="card space-y-3 p-5" data-konfirmasi="Tolak lamaran ini? Keputusan tidak dapat dibatalkan.">
                    @csrf
                    <h3 class="font-bold text-red-700">Tolak</h3>
                    <x-field name="catatan" label="Alasan (internal)" as="textarea" rows="2" />
                    <button class="btn btn-danger w-full">Tolak Lamaran</button>
                </form>
            @endif

            @if ($lamaran->karyawan)
                <a href="{{ route('hrd.karyawan.show', $lamaran->karyawan) }}" class="btn btn-outline w-full">Lihat Data Karyawan &rarr;</a>
            @endif
        </aside>
    </div>
@endsection
