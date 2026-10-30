{{--
    Kolom formulir: label + input + pesan error.
    Pemakaian: <x-field name="nama" label="Nama" :value="$x->nama" required />
    as="textarea" atau as="select" (isi <option> lewat slot).
--}}
@props(['name', 'label', 'as' => 'input', 'type' => 'text', 'value' => null, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">
        {{ $label }}@if ($attributes->has('required')) <span class="text-brand">*</span>@endif
    </label>

    @if ($as === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="4"
            {{ $attributes->except('class')->merge(['class' => 'input']) }}>{{ old($name, $value) }}</textarea>
    @elseif ($as === 'select')
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'input']) }}>
            {{ $slot }}
        </select>
    @elseif ($type === 'file')
        <input type="file" id="{{ $name }}" name="{{ $name }}"
            {{ $attributes->except('class')->merge(['class' => 'input file:mr-3 file:rounded file:border-0 file:bg-brand-soft file:px-3 file:py-1 file:text-sm file:font-semibold file:text-brand-dark']) }}>
    @else
        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'input']) }}>
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
