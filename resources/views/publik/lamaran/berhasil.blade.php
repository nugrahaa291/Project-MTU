@extends('layouts.publik')
@section('judul', 'Lamaran Terkirim')

@section('content')
    <div class="mx-auto max-w-xl px-4 py-14 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-600">&#10003;</div>
        <h1 class="text-2xl font-bold">Lamaran berhasil dikirim</h1>
        <p class="mt-2 text-gray-600">
            Terima kasih, <strong>{{ $lamaran->nama }}</strong>. Lamaran Anda untuk
            <strong>{{ $lamaran->lowongan->judul }}</strong> akan ditinjau oleh HRD.
        </p>

        <div class="card mt-6 p-6">
            <p class="text-sm text-gray-500">Kode lamaran Anda</p>
            <p class="mt-1 font-mono text-3xl font-bold tracking-widest text-brand">{{ $lamaran->kode }}</p>
            <p class="mt-3 text-sm text-gray-600">
                <strong>Simpan kode ini.</strong> Bersama nomor HP yang Anda daftarkan, kode ini dipakai untuk mengecek status lamaran.
            </p>
        </div>

        <div class="mt-6 flex justify-center gap-3">
            <a href="{{ route('status.form') }}" class="btn btn-primary">Cek Status</a>
            <a href="{{ route('lowongan.index') }}" class="btn btn-outline">Lowongan Lain</a>
        </div>
    </div>
@endsection
