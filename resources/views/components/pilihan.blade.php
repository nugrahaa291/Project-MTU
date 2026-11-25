@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => 'Pilih...', 'hint' => null])
@php
    $nilai = old($name, $value);
    $teks = $options[$nilai] ?? null;
    $adaError = $errors->has($name);
@endphp

<div data-kustom data-pilihan data-label="{{ $label }}" @if ($required) data-wajib @endif class="relative">
    <label for="pilihan-{{ $name }}" class="label">{{ $label }}@if ($required) <span class="text-red-600">*</span>@endif</label>

    <input type="hidden" name="{{ $name }}" value="{{ $nilai }}" data-kustom-nilai>

    <button type="button" id="pilihan-{{ $name }}" data-kustom-tombol aria-haspopup="listbox" aria-expanded="false"
            class="input flex items-center justify-between gap-2 text-left {{ $adaError ? 'border-red-500' : '' }}">
        <span data-pilihan-teks class="truncate {{ $teks ? '' : 'text-gray-400' }}">{{ $teks ?? $placeholder }}</span>
        <x-ikon nama="chevron" class="h-4 w-4 shrink-0 text-gray-500" />
    </button>

    <ul role="listbox" data-kustom-panel hidden
        class="absolute right-0 left-0 z-30 mt-1 max-h-64 scroll-my-4 overflow-auto rounded-lg border border-line bg-white py-1 shadow-xl">
        @foreach ($options as $v => $t)
            <li role="option" tabindex="-1" data-nilai="{{ $v }}" data-teks="{{ $t }}"
                aria-selected="{{ (string) $nilai === (string) $v ? 'true' : 'false' }}"
                class="group flex cursor-pointer items-center justify-between px-4 py-2.5 text-sm hover:bg-brand-soft focus:bg-brand-soft focus:outline-none aria-selected:bg-brand-soft aria-selected:font-semibold aria-selected:text-brand-dark">
                {{ $t }}
                <x-ikon nama="centang" class="hidden h-4 w-4 text-brand group-aria-selected:block" />
            </li>
        @endforeach
    </ul>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    <p data-error-for="{{ $name }}" class="mt-1 text-sm text-red-600" @unless ($adaError) hidden @endunless>{{ $errors->first($name) }}</p>
</div>
