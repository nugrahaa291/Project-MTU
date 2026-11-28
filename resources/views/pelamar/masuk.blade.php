@extends('layouts.publik')
@section('judul', $daftar ? 'Daftar Pelamar' : 'Masuk untuk melamar')
@section('content')
<div class="mx-auto max-w-md px-4 py-8 sm:py-14">
    <section class="card p-5 sm:p-8">
        <h1 class="mb-6 text-2xl font-bold">{{ $daftar ? 'Daftar akun pelamar' : 'Masuk untuk melamar' }}</h1>
        @php $karyawan = ! $daftar && request()->boolean('karyawan'); @endphp
        @unless ($daftar)
            <nav class="mb-5 flex flex-wrap gap-2">
                <a class="btn {{ $karyawan ? 'btn-outline' : 'btn-primary' }}" href="{{ route('pelamar.login') }}">Email pelamar</a>
                <a class="btn {{ $karyawan ? 'btn-primary' : 'btn-outline' }}" href="{{ route('pelamar.login', ['karyawan' => 1]) }}">NIP karyawan</a>
            </nav>
            @if ($karyawan)
                <p class="mb-3 text-sm">Gunakan password dari <a class="underline" href="{{ route('pwa') }}">halaman Absen</a>. Sudah punya akun pelamar? Masuk dengan email atau Google, lalu hubungkan NIP di Lamaran Saya agar riwayatnya ikut terpakai.</p>
            @endif
        @endunless
        <form class="space-y-4" method="post" action="{{ route($daftar ? 'pelamar.daftar.store' : ($karyawan ? 'pelamar.login.karyawan' : 'pelamar.login.store')) }}">
            @csrf
            @if ($daftar)
                <x-field name="nama" label="Nama lengkap" autocomplete="name" required />
            @endif
            @if ($karyawan)
                <x-field name="nip" label="NIP karyawan" autocomplete="username" required />
            @else
                <x-field name="email" type="email" label="Email" autocomplete="email" required />
            @endif
            <x-field name="password" type="password" label="Password" :autocomplete="$daftar ? 'new-password' : 'current-password'" required />
            @if ($daftar)
                <x-field name="password_confirmation" type="password" label="Ulangi password" autocomplete="new-password" required />
            @endif
            <button class="btn btn-primary w-full">{{ $daftar ? 'Daftar' : 'Masuk' }}</button>
        </form>
        <a class="btn btn-outline mt-3 w-full" href="{{ route('pelamar.google') }}">Lanjutkan dengan Google</a>
        <p class="mt-5 text-center text-sm">
            {{ $daftar ? 'Sudah punya akun?' : 'Belum punya akun?' }}
            <a class="font-semibold text-brand-dark" href="{{ route($daftar ? 'pelamar.login' : 'pelamar.daftar') }}">{{ $daftar ? 'Masuk' : 'Daftar' }}</a>
        </p>
    </section>
</div>
@endsection
