@extends('layouts.panel')
@section('judul', 'Lowongan')

@section('content')
    <div class="mb-4 flex justify-end"><a href="{{ route('hrd.lowongan.create') }}" class="btn btn-primary">+ Buat Lowongan</a></div>
    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Lowongan</th><th>Periode</th><th>Kuota</th><th>Lamaran</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($lowongan as $l)
                    <tr>
                        <td><p class="font-medium">{{ $l->judul }}</p><p class="text-xs text-gray-500">{{ $l->jabatan->nama }} &middot; {{ $l->lokasi }}</p></td>
                        <td class="whitespace-nowrap text-gray-600">{{ $l->tanggal_buka->format('d/m/Y') }} - {{ $l->tanggal_tutup->format('d/m/Y') }}</td>
                        <td>{{ $l->diterima_count }} / {{ $l->kuota }}</td>
                        <td>{{ $l->lamaran_count }}</td>
                        <td>@php $label = $l->labelStatus(); @endphp
                            <x-badge :warna="$label === 'Dibuka' ? 'green' : ($label === 'Belum dibuka' ? 'blue' : 'slate')">{{ $label }}</x-badge></td>
                        <td class="space-x-2 text-right whitespace-nowrap">
                            <a href="{{ route('hrd.lowongan.show', $l) }}" class="btn btn-outline btn-sm">Detail</a>
                            <a href="{{ route('hrd.lowongan.edit', $l) }}" class="btn btn-outline btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">Belum ada lowongan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $lowongan->links() }}</div>
@endsection
