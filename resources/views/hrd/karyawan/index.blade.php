@extends('layouts.panel')
@section('judul', 'Karyawan')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Cari nama / NIP / NIK" class="input w-64">
            <select name="aktif" class="input w-36">
                <option value="">Semua</option>
                <option value="1" @selected(request('aktif') === '1')>Aktif</option>
                <option value="0" @selected(request('aktif') === '0')>Nonaktif</option>
            </select>
            <button class="btn btn-dark">Cari</button>
        </form>
        <a href="{{ route('hrd.karyawan.create') }}" class="btn btn-primary">+ Tambah Karyawan</a>
    </div>

    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Tgl Masuk</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($karyawan as $k)
                    <tr>
                        <td class="font-mono text-xs">{{ $k->nip }}</td>
                        <td><p class="font-medium">{{ $k->nama }}</p><p class="text-xs text-gray-500">{{ $k->user->email }}</p></td>
                        <td>{{ $k->jabatan->nama }}</td>
                        <td class="whitespace-nowrap text-gray-600">{{ $k->tanggal_masuk->format('d/m/Y') }}</td>
                        <td><x-badge :warna="$k->aktif ? 'green' : 'slate'">{{ $k->aktif ? 'Aktif' : 'Nonaktif' }}</x-badge></td>
                        <td class="text-right"><a href="{{ route('hrd.karyawan.show', $k) }}" class="btn btn-outline btn-sm">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">Belum ada karyawan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $karyawan->links() }}</div>
@endsection
