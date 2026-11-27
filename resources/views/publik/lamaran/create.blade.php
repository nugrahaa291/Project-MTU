@extends('layouts.publik')
@section('judul', 'Lamar: '.$lowongan->judul)

@section('content')
    @php
        $dokumen = $lowongan->daftarDokumen();

        // Bila halaman dimuat ulang dengan error, buka tahap yang bermasalah
        $namaBerkas = collect($dokumen)
            ->flatMap(fn ($d) => $d['kunci'] === 'pendukung' ? ['jenis_pendukung', 'dokumen_pendukung'] : [$d['kunci']])
            ->all();
        $adaErrorDataDiri = collect($errors->keys())->diff($namaBerkas)->isNotEmpty();
        $langkahAwal = $errors->any() && ! $adaErrorDataDiri ? 2 : 1;
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-8">
        <div class="mb-5">
            <p class="text-sm font-semibold tracking-wide text-brand uppercase">Form Lamaran</p>
            <h1 class="mt-1 text-2xl font-bold">
                Daftar sebagai {{ $lowongan->judul }}
                <span class="text-gray-400">(<span data-step-hitung>{{ $langkahAwal }}/2</span>)</span>
            </h1>
            <p class="text-gray-600">{{ $lowongan->jabatan->nama }} &middot; {{ $lowongan->lokasi }}</p>
        </div>

        {{-- Penanda tahap --}}
        <div data-step-penanda class="card mb-5 scroll-mt-24 overflow-hidden">
            <div class="flex items-start px-6 pt-6 pb-5">
                @foreach ([1 => ['Data Diri', 'user'], 2 => ['Dokumen', 'document']] as $no => [$nama, $ikon])
                    @if ($no === 2)
                        <div data-step-garis class="mt-6 flex-1 border-t-2 border-dashed {{ $langkahAwal === 2 ? 'border-brand' : 'border-gray-300' }}"></div>
                    @endif
                    <button type="button" data-step-ke="{{ $no }}" data-step-indikator="{{ $no }}"
                            data-state="{{ $no < $langkahAwal ? 'selesai' : ($no === $langkahAwal ? 'aktif' : 'belum') }}"
                            class="group flex w-24 flex-col items-center gap-2 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-gray-200 bg-gray-50 text-gray-400 transition group-data-[state=aktif]:border-brand group-data-[state=aktif]:bg-brand group-data-[state=aktif]:text-white group-data-[state=selesai]:border-green-600 group-data-[state=selesai]:bg-green-600 group-data-[state=selesai]:text-white">
                            <x-ikon :nama="$ikon" class="h-6 w-6 group-data-[state=selesai]:hidden" />
                            <x-ikon nama="centang" class="hidden h-6 w-6 group-data-[state=selesai]:block" />
                        </span>
                        <span class="text-sm font-semibold text-gray-400 group-data-[state=aktif]:text-ink group-data-[state=selesai]:text-green-700">{{ $nama }}</span>
                    </button>
                @endforeach
            </div>
            <div class="h-1.5 bg-gray-100">
                <div data-step-progres class="h-full rounded-r-full bg-brand transition-all duration-300" style="width: {{ $langkahAwal * 50 }}%"></div>
            </div>
        </div>

        <div data-form-pesan hidden class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"></div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Mohon periksa kembali isian Anda. Ada {{ $errors->count() }} kesalahan (ditandai merah di bawah).
            </div>
        @endif

        <form method="POST" action="{{ route('lamaran.store', $lowongan) }}" enctype="multipart/form-data"
              data-stepper data-step-awal="{{ $langkahAwal }}">
            @csrf

            {{-- TAHAP 1: DATA DIRI --}}
            <section data-step="1" @if ($langkahAwal !== 1) hidden @endif class="card space-y-6 p-6">
                <div class="space-y-4">
                    <h2 class="border-b border-line pb-2 text-base font-bold">Data pribadi</h2>
                    <x-field name="nama" :value="auth('pelamar')->user()->nama" label="Nama Lengkap" required />
                    <x-field name="nik" label="NIK (16 digit)" inputmode="numeric" maxlength="16" required />

                    <div>

                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $nilai => $teks)           
                            @endforeach
                        </div>
                         <x-pilihan name="jenis_kelamin" label="Jenis Kelamin" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" required />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="tempat_lahir" label="Tempat Lahir" required />
                        <x-tanggal name="tanggal_lahir" label="Tanggal Lahir" :max="now()->subYears(17)->toDateString()" :tahun-min="now()->year - 70" required />
                    </div>
                </div>

                <div class="space-y-4">
                    <h2 class="border-b border-line pb-2 text-base font-bold">Kontak</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="email" :value="auth('pelamar')->user()->email" label="Email" type="email" hint="Dipakai untuk akun karyawan bila Anda diterima." required />
                        <x-field name="no_hp" label="Nomor HP / WhatsApp" placeholder="081234567890" inputmode="tel" required />
                    </div>
                </div>

                <div class="space-y-4">
                    <h2 class="border-b border-line pb-2 text-base font-bold">Pendidikan &amp; alamat</h2>
                    <x-pilihan name="pendidikan" label="Pendidikan Terakhir" :options="collect(\App\Models\Lamaran::PENDIDIKAN)->mapWithKeys(fn ($p) => [$p => $p])->all()" required />
                    <x-field name="alamat" label="Alamat Lengkap" as="textarea" rows="3" required resize />
                </div>

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row">
                    <a href="{{ route('lowongan.show', $lowongan) }}" class="btn btn-outline gap-2 py-3 text-base sm:w-40">
                        <x-ikon nama="arrow" class="h-4 w-4 rotate-180" /> Kembali
                    </a>
                    <button type="button" data-step-lanjut class="btn btn-primary flex-1 gap-2 py-3 text-base">
                        Lanjut ke Dokumen <x-ikon nama="arrow" class="h-4 w-4" />
                    </button>
                </div>
            </section>

            {{-- TAHAP 2: DOKUMEN --}}
            <section data-step="2" @if ($langkahAwal !== 2) hidden @endif class="card space-y-5 p-6">
                <div>
                    <h2 class="border-b border-line pb-2 text-base font-bold">Dokumen</h2>
                    <p class="mt-3 text-sm text-gray-500">
                        Siapkan dokumen berikut untuk posisi <span class="font-semibold text-ink">{{ $lowongan->judul }}</span>.
                        Tanda <span class="font-bold text-red-600">*</span> berarti wajib diisi. Ketuk nama berkas setelah dipilih untuk memeriksa isinya.
                        Berkas disimpan aman dan hanya dapat dilihat oleh HRD.
                    </p>
                </div>

                @foreach ($dokumen as $d)
                    @php
                        $ekstensi = str_replace('.', '', $d['accept']);
                        $kamera = str_contains($d['accept'], '.jpg') ? ($d['kunci'] === 'pas_foto' ? 'user' : 'environment') : null;
                    @endphp

                    @if ($d['kunci'] === 'pendukung')
                        <div class="space-y-4 rounded-xl border border-line bg-gray-50 p-4">
                            <p class="text-sm font-bold">{{ $d['label'] }}@unless ($d['wajib']) <span class="font-normal text-gray-500">(opsional)</span>@endunless</p>
                            <x-pilihan name="jenis_pendukung" label="Dokumen yang Anda unggah" :options="$d['pilihan']" :required="$d['wajib']" />
                            <x-unggah-berkas name="dokumen_pendukung" label="Dokumen pendukung" :hint="$d['hint']"
                                             :accept="$d['accept']" :ekstensi="$ekstensi" :max="$d['max']"
                                             :required="$d['wajib']" :kamera="$kamera" />
                        </div>
                    @else
                        <x-unggah-berkas :name="$d['kunci']" :label="$d['wajib'] ? $d['label'] : $d['label'].' (opsional)'" :hint="$d['hint']"
                                         :accept="$d['accept']" :ekstensi="$ekstensi" :max="$d['max']"
                                         :required="$d['wajib']" :kamera="$kamera" />
                    @endif
                @endforeach

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row">
                    <button type="button" data-step-kembali class="btn btn-outline gap-2 py-3 text-base sm:w-40">
                        <x-ikon nama="arrow" class="h-4 w-4 rotate-180" /> Kembali
                    </button>
                    <button type="submit" data-kirim class="btn btn-primary flex-1 py-3 text-base">Kirim Lamaran</button>
                </div>
            </section>
        </form>
    </div>

    {{-- Pratinjau berkas --}}
    <dialog id="dialog-pratinjau" class="m-auto w-[min(92vw,720px)] overflow-hidden rounded-2xl bg-white p-0 text-ink shadow-2xl backdrop:bg-black/60">
        <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div class="min-w-0">
                <h3 data-pratinjau-judul class="font-bold"></h3>
                <p data-pratinjau-info class="truncate text-xs text-gray-500"></p>
            </div>
            <button type="button" data-pratinjau-tutup aria-label="Tutup" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                <x-ikon nama="close" class="h-5 w-5" />
            </button>
        </div>
        <div data-pratinjau-isi class="max-h-[70vh] overflow-auto p-4"></div>
        <div class="flex flex-col gap-2 border-t border-line p-4 sm:flex-row sm:justify-end">
            <button type="button" data-pratinjau-ganti class="btn btn-outline">Ganti berkas</button>
            <button type="button" data-pratinjau-tutup class="btn btn-primary">Sudah sesuai</button>
        </div>
    </dialog>
@endsection