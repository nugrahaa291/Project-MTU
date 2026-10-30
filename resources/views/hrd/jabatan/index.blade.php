@extends('layouts.panel')
@section('judul', 'Jabatan')

@section('content')
    <div class="mb-4 flex justify-end"><a href="{{ route('hrd.jabatan.create') }}" class="btn btn-primary">+ Tambah Jabatan</a></div>
    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Nama</th><th>Lowongan</th><th>Karyawan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($jabatan as $j)
                    <tr>
                        <td><p class="font-medium">{{ $j->nama }}</p><p class="text-xs text-gray-500">{{ $j->deskripsi }}</p></td>
                        <td>{{ $j->lowongan_count }}</td>
                        <td>{{ $j->karyawan_count }}</td>
                        <td><x-badge :warna="$j->aktif ? 'green' : 'slate'">{{ $j->aktif ? 'Aktif' : 'Nonaktif' }}</x-badge></td>
                        <td class="space-x-2 text-right whitespace-nowrap">
                            <a href="{{ route('hrd.jabatan.edit', $j) }}" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" action="{{ route('hrd.jabatan.destroy', $j) }}" class="inline" data-konfirmasi="Hapus jabatan ini?">
                                @csrf @method('DELETE')<button class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-gray-500">Belum ada jabatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
