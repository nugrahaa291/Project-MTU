@props(['warna' => 'slate'])
@php
    $kelas = [
        'slate' => 'bg-slate-100 text-slate-700',
        'green' => 'bg-green-100 text-green-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'red' => 'bg-red-100 text-red-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'brand' => 'bg-brand-soft text-brand-dark',
    ][$warna] ?? 'bg-slate-100 text-slate-700';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold $kelas"]) }}>{{ $slot }}</span>
