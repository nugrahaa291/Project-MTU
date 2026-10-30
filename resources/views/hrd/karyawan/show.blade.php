@extends('layouts.panel')
@section('judul', 'Detail Karyawan')

@section('content')
    {{-- Password sementara hanya tampil SEKALI (flash), tidak disimpan di mana pun dalam bentuk asli --}}
    @if (session('password_sementara'))
        <div class="mb-6 rounded-xl border-2 border-amber-300 bg-amber-50 p-5">
            <p class="font-bold text-amber-900">Password sementara (tampil sekali ini saja)</p>
            <p class="mt-2 font-mono text-2xl font-bold tracking-wider text-ink select-all">{{ session('password_sementara') }}</p>
            <p class="mt-2 text-sm text-amber-800">
                Sampaikan ke <strong>{{ $karyawan->nama }}</strong> ({{ $karyawan->user->email }}) lewat jalur aman.
                Karyawan wajib menggantinya saat pertama kali login. Bila hilang, gunakan tombol <em>Reset Password</em>.
            </p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card p-6 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold">{{ $karyawan->nama }}</h2>
                    <p class="text-sm text-gray-500">{{ $karyawan->jabatan->nama }} &middot; NIP {{ $karyawan->nip }}</p>
                </div>
                <x-badge :warna="$karyawan->aktif ? 'green' : 'slate'">{{ $karyawan->aktif ? 'Aktif' : 'Nonaktif' }}</x-badge>
            </div>

            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Email login</dt><dd class="font-medium">{{ $karyawan->user->email }}</dd></div>
                <div><dt class="text-gray-500">NIK</dt><dd class="font-medium">{{ $karyawan->nik }}</dd></div>
                <div><dt class="text-gray-500">No. HP</dt><dd class="font-medium">{{ $karyawan->no_hp }}</dd></div>
                <div><dt class="text-gray-500">Tanggal masuk</dt><dd class="font-medium">{{ $karyawan->tanggal_masuk->translatedFormat('d F Y') }}</dd></div>
                @if ($karyawan->tanggal_keluar)
                    <div><dt class="text-gray-500">Tanggal keluar</dt><dd class="font-medium">{{ $karyawan->tanggal_keluar->translatedFormat('d F Y') }}</dd></div>
                @endif
                <div class="sm:col-span-2"><dt class="text-gray-500">Alamat</dt><dd class="font-medium">{{ $karyawan->alamat ?: '-' }}</dd></div>
                @if ($karyawan->lamaran)
                    <div class="sm:col-span-2"><dt class="text-gray-500">Asal lamaran</dt>
                        <dd><a class="font-medium text-brand" href="{{ route('hrd.lamaran.show', $karyawan->lamaran) }}">{{ $karyawan->lamaran->kode }} - {{ $karyawan->lamaran->lowongan->judul }}</a></dd></div>
                @endif
                <div><dt class="text-gray-500">Status password</dt>
                    <dd class="font-medium">{{ $karyawan->user->must_change_password ? 'Masih sementara' : 'Sudah diganti karyawan' }}</dd></div>
            </dl>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-line pt-4">
                <a href="{{ route('hrd.karyawan.edit', $karyawan) }}" class="btn btn-outline">Edit</a>
                <form method="POST" action="{{ route('hrd.karyawan.reset-password', $karyawan) }}"
                      data-konfirmasi="Reset password? Password lama tidak berlaku lagi dan perangkat mobile akan keluar.">
                    @csrf<button class="btn btn-dark">Reset Password</button>
                </form>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="mb-3 font-bold">10 Absensi Terakhir</h3>
            <ul class="divide-y divide-line text-sm">
                @forelse ($absensi as $a)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $a->tanggal->translatedFormat('D, d M') }} <span class="text-gray-500">{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '' }}</span></span>
                        <x-badge :warna="$a->status->warna()">{{ $a->status->label() }}</x-badge>
                    </li>
                @empty
                    <li class="py-4 text-center text-gray-500">Belum ada absensi.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
