@extends('layouts.publik')
@section('judul', 'Lamaran Terkirim')

@section('content')
    @php
        $langkahSelanjutnya = [
            ['Lamaran diterima', 'Data dan dokumen Anda sudah masuk ke sistem kami.', true],
            ['Diperiksa oleh HRD', 'Tim HRD memeriksa kelengkapan data dan dokumen Anda.', false],
            ['Kami menghubungi Anda', 'Bila lolos ke tahap berikutnya, Anda dihubungi lewat WhatsApp atau email yang didaftarkan.', false],
        ];
    @endphp

    <div class="mx-auto max-w-xl px-4 py-10">
        <div class="card overflow-hidden text-center">
            <div class="bg-gradient-to-b from-brand-soft to-white px-6 pt-10 pb-6">
                {{-- Ilustrasi truk + centang --}}
                <svg viewBox="0 0 240 140" class="mx-auto h-36 w-auto" role="img" aria-label="Truk dengan tanda centang">
                    <ellipse cx="120" cy="126" rx="100" ry="7" fill="#30323a" opacity=".08"/>
                    <rect x="14" y="38" width="132" height="68" rx="8" fill="#f8694a"/>
                    <rect x="14" y="38" width="132" height="14" rx="8" fill="#e85b3e"/>
                    <rect x="30" y="66" width="100" height="6" rx="3" fill="#fff" opacity=".4"/>
                    <rect x="30" y="80" width="64" height="6" rx="3" fill="#fff" opacity=".3"/>
                    <path d="M150 54h30c6 0 10 3 13 8l12 18c2 3 3 6 3 10v14h-58z" fill="#30323a"/>
                    <path d="M158 62h20c3 0 5 1 7 4l8 12h-35z" fill="#cfeeff"/>
                    <rect x="10" y="104" width="198" height="8" rx="4" fill="#3d414b"/>
                    <g fill="#30323a" stroke="#fff" stroke-width="4">
                        <circle cx="44" cy="114" r="12"/><circle cx="78" cy="114" r="12"/><circle cx="176" cy="114" r="12"/>
                    </g>
                    <g fill="#cbd5e1"><circle cx="44" cy="114" r="4"/><circle cx="78" cy="114" r="4"/><circle cx="176" cy="114" r="4"/></g>
                    <circle cx="198" cy="32" r="22" fill="#16a34a" stroke="#fff" stroke-width="4"/>
                    <path d="M188 32l7 7 14-15" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

                <p class="mt-4 text-xs font-bold tracking-widest text-green-700 uppercase">Lamaran terkirim</p>
                <h1 class="mt-1 text-2xl font-bold">Terima kasih, {{ $lamaran->nama }}!</h1>
                <p class="mt-2 text-gray-600">
                    Anda telah terdaftar sebagai calon <strong>{{ $lamaran->lowongan->judul }}</strong>
                    di {{ config('app.name') }}.
                </p>
            </div>

            <div class="px-6 pb-8">
                <div class="rounded-xl border-2 border-dashed border-brand/40 bg-brand-soft p-5">
                    <p class="text-sm text-gray-500">Kode lamaran Anda</p>
                    <p class="mt-1 font-mono text-3xl font-bold tracking-widest text-brand-dark">{{ $lamaran->kode }}</p>
                    <button type="button" data-salin="{{ $lamaran->kode }}" class="btn btn-outline btn-sm mt-3">Salin kode</button>
                </div>
                <p class="mt-3 text-sm text-gray-600">
                    <strong>Simpan kode ini.</strong> Bersama nomor HP yang Anda daftarkan, kode ini dipakai untuk mengecek status lamaran.
                </p>

                <h2 class="mt-8 text-left text-sm font-bold tracking-wide text-gray-500 uppercase">Langkah selanjutnya</h2>
                <ol class="mt-4 text-left">
                    @foreach ($langkahSelanjutnya as $i => [$judul, $isi, $selesai])
                        <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                            @unless ($loop->last)
                                <span class="absolute top-9 bottom-0 left-[17px] w-px bg-gray-200"></span>
                            @endunless
                            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $selesai ? 'bg-green-600 text-white' : 'border-2 border-gray-200 bg-white text-gray-400' }}">
                                @if ($selesai)
                                    <x-ikon nama="centang" class="h-5 w-5" />
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </span>
                            <div>
                                <p class="font-semibold">{{ $judul }}</p>
                                <p class="text-sm text-gray-500">{{ $isi }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('pelamar.riwayat') }}" class="btn btn-primary flex-1 py-3 text-base">Lamaran Saya</a>
                    <a href="{{ route('beranda') }}" class="btn btn-outline flex-1 py-3 text-base">Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
@endsection