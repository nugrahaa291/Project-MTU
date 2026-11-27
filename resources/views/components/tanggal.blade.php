@props(['name', 'label', 'value' => null, 'required' => false, 'max' => null, 'tahunMin' => null, 'placeholder' => 'Pilih tanggal', 'hint' => null])
@php
    $nilai = old($name, $value);
    $adaError = $errors->has($name);
@endphp

<div data-kustom data-tanggal data-label="{{ $label }}" data-max="{{ $max }}" data-tahun-min="{{ $tahunMin }}"
     data-placeholder="{{ $placeholder }}" @if ($required) data-wajib @endif class="relative">
    <label for="tanggal-{{ $name }}" class="label">{{ $label }}@if ($required) <span class="text-red-600">*</span>@endif</label>

    <input type="hidden" name="{{ $name }}" value="{{ $nilai }}" data-kustom-nilai>

    <button type="button" id="tanggal-{{ $name }}" data-kustom-tombol aria-haspopup="dialog" aria-expanded="false"
            class="input flex items-center gap-3 text-left {{ $adaError ? 'border-red-500' : '' }}">
        <x-ikon nama="kalender" class="h-5 w-5 shrink-0 text-gray-500" />
        <span data-tanggal-teks class="flex-1 truncate text-gray-400">{{ $placeholder }}</span>
    </button>

    <div data-kustom-panel hidden role="dialog" aria-label="Pilih {{ $label }}"
        class="absolute left-0 z-30 mt-1 w-72 max-w-full scroll-my-4 rounded-xl border border-line bg-white p-3 shadow-xl">
        <div data-tanggal-isi></div>
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    <p data-error-for="{{ $name }}" class="mt-1 text-sm text-red-600" @unless ($adaError) hidden @endunless>{{ $errors->first($name) }}</p>
</div>