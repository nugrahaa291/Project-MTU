@extends('layouts.publik')
@section('judul', 'Tentang Kami')

@section('content')
    {{-- HERO --}}
    <section class="bg-gradient-to-br from-ink via-ink-soft to-brand/70 text-white">
        <div class="mx-auto max-w-6xl px-4 py-16 text-center">
            <h1 class="text-4xl font-bold md:text-5xl">Tentang Kami</h1>
            <div class="mx-auto my-5 h-1 w-16 rounded bg-brand"></div>
            <p class="mx-auto max-w-2xl text-lg text-gray-100">{{ config('perusahaan.ringkasan') }}</p>
        </div>
    </section>

    {{-- PROFIL --}}
    <section class="mx-auto max-w-6xl px-4 py-14">
        <div class="grid gap-10 lg:grid-cols-3">
            <div class="space-y-4 leading-relaxed text-gray-600 lg:col-span-2">
                <h2 class="text-2xl font-bold text-ink">Profil Perusahaan</h2>
                @foreach (config('perusahaan.tentang') as $paragraf)
                    <p>{{ $paragraf }}</p>
                @endforeach
            </div>

            <aside class="card self-start p-6">
                <p class="text-sm text-gray-500">Berdiri sejak</p>
                <p class="text-4xl font-bold text-brand">{{ config('perusahaan.tahun_berdiri') }}</p>
                <div class="my-4 h-px bg-line"></div>
                <dl class="space-y-3 text-sm">
                    @foreach (config('perusahaan.statistik') as $stat)
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">{{ $stat['label'] }}</dt>
                            <dd class="font-bold text-ink">{{ $stat['angka'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </aside>
        </div>
    </section>

    {{-- VISI & MISI --}}
    <section class="bg-white py-14">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 md:grid-cols-2">
            <div class="card p-8">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-soft text-brand"><x-ikon nama="eye" /></span>
                <h2 class="mt-4 text-xl font-bold">Visi</h2>
                <p class="mt-3 leading-relaxed text-gray-600">{{ config('perusahaan.visi') }}</p>
            </div>
            <div class="card p-8">
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-soft text-brand"><x-ikon nama="target" /></span>
                <h2 class="mt-4 text-xl font-bold">Misi</h2>
                <ul class="mt-3 space-y-3 text-gray-600">
                    @foreach (config('perusahaan.misi') as $misi)
                        <li class="flex gap-3"><x-ikon nama="check" class="mt-0.5 h-5 w-5 shrink-0 text-brand" />{{ $misi }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- NILAI --}}
    <section class="mx-auto max-w-6xl px-4 py-14">
        <div class="text-center">
            <p class="text-sm font-semibold tracking-wide text-brand uppercase">Budaya Kerja</p>
            <h2 class="mt-2 text-3xl font-bold">Nilai yang Kami Pegang</h2>
        </div>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (config('perusahaan.nilai') as $nilai)
                <div class="card p-6 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-soft text-brand"><x-ikon :nama="$nilai['ikon']" /></span>
                    <h3 class="mt-4 font-bold">{{ $nilai['judul'] }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $nilai['isi'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- KONTAK --}}
    <section class="mx-auto max-w-6xl px-4">
        <div class="rounded-2xl bg-ink px-6 py-10 text-white md:px-12">
            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-bold">Hubungi Kami</h2>
                    <p class="mt-2 text-gray-300">Ada pertanyaan seputar rekrutmen? Tim HRD siap membantu.</p>
                    <a href="{{ route('lowongan.index') }}" class="btn btn-primary mt-6">Lihat Lowongan</a>
                </div>
                <ul class="space-y-4 text-sm">
                    <li class="flex gap-3"><x-ikon nama="pin" class="h-5 w-5 shrink-0 text-brand" />{{ config('perusahaan.kontak.alamat') }}</li>
                    <li class="flex gap-3"><x-ikon nama="phone" class="h-5 w-5 shrink-0 text-brand" />{{ config('perusahaan.kontak.telepon') }}</li>
                    <li class="flex gap-3"><x-ikon nama="mail" class="h-5 w-5 shrink-0 text-brand" />{{ config('perusahaan.kontak.email') }}</li>
                    <li class="flex gap-3"><x-ikon nama="clock" class="h-5 w-5 shrink-0 text-brand" />{{ config('perusahaan.kontak.jam') }}</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
