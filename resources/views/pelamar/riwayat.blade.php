@extends('layouts.publik')
@section('judul', 'Lamaran Saya')
@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0"><h1 class="text-2xl font-bold">Lamaran Saya</h1><p class="break-all text-gray-600">{{ $pelamar->email }}</p></div>
        <div class="flex flex-wrap gap-2">
            @unless ($pelamar->google_id)
                <a class="btn btn-outline" href="{{ route('pelamar.google') }}">Hubungkan Google</a>
            @endunless
            <form method="post" action="{{ route('pelamar.keluar') }}">@csrf<button class="btn btn-outline">Keluar</button></form>
        </div>
    </div>
    @unless ($pelamar->user_id)
        <details class="card mb-5 p-5" @if ($errors->has('nip') || $errors->has('password')) open @endif>
            <summary class="cursor-pointer font-semibold">Hubungkan akun karyawan</summary>
            <p class="my-3 text-sm">Setelah terhubung, gunakan password karyawan untuk kedua portal. Jika masih memakai PIN, ganti dulu di <a class="underline" href="{{ route('pwa') }}">halaman Absen</a>.</p>
            <form class="grid items-end gap-3 sm:grid-cols-3" method="post" action="{{ route('pelamar.hubungkan.karyawan') }}">
                @csrf
                <x-field name="nip" label="NIP karyawan" autocomplete="username" required />
                <x-field name="password" type="password" label="Password karyawan" autocomplete="current-password" required />
                <button class="btn btn-primary">Hubungkan akun</button>
            </form>
        </details>
    @endunless
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($daftar as $lamaran)
            <article class="card min-w-0 p-5">
                <p class="text-sm text-gray-500">{{ $lamaran->kode }} · {{ $lamaran->created_at->translatedFormat('d F Y') }}</p>
                <h2 class="my-2 break-words text-lg font-bold">{{ $lamaran->lowongan->judul }}</h2>
                <x-badge :warna="$lamaran->status->warna()">{{ $lamaran->statusPublik() }}</x-badge>
            </article>
        @empty
            <p class="card p-5">Belum ada lamaran. <a class="font-semibold text-brand-dark" href="{{ route('lowongan.index') }}">Lihat lowongan</a></p>
        @endforelse
    </div>
    <div class="mt-4">{{ $daftar->links() }}</div>
    <details class="card mt-6 p-5" @if($errors->any()) open @endif>
        <summary class="cursor-pointer font-semibold">Tautkan lamaran sebelum pendaftaran akun</summary>
        <form method="post" class="mt-4 grid items-end gap-3 sm:grid-cols-3" action="{{ route('pelamar.tautkan') }}">
            @csrf
            <x-field name="kode" label="Kode lamaran" required />
            <x-field name="no_hp" label="Nomor HP saat melamar" type="tel" required />
            <button class="btn btn-primary">Tautkan lamaran</button>
        </form>
    </details>
</div>
@endsection
