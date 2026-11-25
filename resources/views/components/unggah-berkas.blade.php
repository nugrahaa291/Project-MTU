@props(['name', 'label', 'hint' => null, 'accept' => '', 'ekstensi' => '', 'max' => 2048, 'required' => false, 'kamera' => null])
@php $adaError = $errors->has($name); @endphp

<div data-unggah data-label="{{ $label }}">
    <p class="text-sm font-semibold">{{ $label }}@if ($required) <span class="text-red-600">*</span>@endif</p>

    <div class="mt-1.5 flex items-center gap-2">
        {{-- Kotak nama berkas --}}
        <div data-unggah-kotak @if ($adaError) data-error @endif
             class="flex min-h-14 min-w-0 flex-1 items-center gap-1 rounded-lg border border-line bg-white px-3 data-[error]:border-red-500">
            <span data-unggah-kosong class="flex-1 truncate text-sm text-gray-400">Belum ada berkas</span>

            <button type="button" data-unggah-lihat hidden class="min-w-0 flex-1 py-2 text-left" aria-label="Lihat pratinjau {{ $label }}">
                <span class="flex items-center gap-3">
                    <span data-unggah-mini class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-brand-soft text-xs font-bold text-brand-dark"></span>
                    <span class="min-w-0">
                        <span data-unggah-nama class="block truncate text-sm font-medium text-blue-700 hover:underline"></span>
                        <span data-unggah-ukuran class="block text-xs text-gray-500">Ketuk untuk melihat pratinjau</span>
                    </span>
                </span>
            </button>

            <button type="button" data-unggah-hapus hidden aria-label="Hapus berkas"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-red-50 hover:text-red-600">
                <x-ikon nama="close" class="h-5 w-5" />
            </button>
        </div>

        @if ($kamera)
            <button type="button" data-unggah-kamera hidden class="btn btn-outline h-12 shrink-0 gap-1.5 px-3" aria-label="Ambil foto">
                <x-ikon nama="camera" class="h-5 w-5" /> <span class="hidden sm:inline">Foto</span>
            </button>
        @endif

        <button type="button" data-unggah-pilih class="btn h-12 shrink-0 gap-1.5 bg-brand-soft px-4 text-brand-dark hover:bg-brand/20">
            <x-ikon nama="paperclip" class="h-5 w-5" /> Lampiran
        </button>
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    <p data-error-for="{{ $name }}" class="mt-1 text-sm text-red-600" @unless ($adaError) hidden @endunless>{{ $errors->first($name) }}</p>

    <input type="file" name="{{ $name }}" accept="{{ $accept }}" data-unggah-input
           data-max="{{ $max }}" data-ekstensi="{{ $ekstensi }}" @if ($required) data-wajib @endif
           class="sr-only" tabindex="-1">
    @if ($kamera)
        <input type="file" accept="image/*" capture="{{ $kamera }}" data-unggah-kamera-input
               class="sr-only" tabindex="-1" aria-hidden="true">
    @endif
</div>
