@extends('layouts.panel')
@section('judul', 'Lamaran Masuk')

@section('content')
    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-4">
        <input name="q" value="{{ request('q') }}" placeholder="Cari nama / NIK / kode" class="input">
        <select name="lowongan_id" class="input">
            <option value="">Semua lowongan</option>
            @foreach ($daftarLowongan as $l)
                <option value="{{ $l->id }}" @selected(request('lowongan_id') == $l->id)>{{ $l->judul }}</option>
            @endforeach
        </select>
        <select name="status" class="input">
            <option value="">Semua status</option>
            @foreach (\App\Enums\StatusLamaran::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Pelamar</th><th>Lowongan</th><th>Kode</th><th>Status</th><th>Masuk</th><th></th></tr></thead>
            <tbody>
                @forelse ($lamaran as $l)
                    <tr>
                        <td><p class="font-medium">{{ $l->nama }}</p><p class="text-xs text-gray-500">{{ $l->no_hp }}</p></td>
                        <td>{{ $l->lowongan->judul }}</td>
                        <td class="font-mono text-xs">{{ $l->kode }}</td>
                        <td><x-badge :warna="$l->status->warna()">{{ $l->status->label() }}</x-badge></td>
                        <td class="whitespace-nowrap text-gray-500">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-right"><a href="{{ route('hrd.lamaran.show', $l) }}" class="btn btn-outline btn-sm">Tinjau</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">Tidak ada lamaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $lamaran->links() }}</div>
@endsection
