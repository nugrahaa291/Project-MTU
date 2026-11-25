@extends('layouts.panel')
@section('judul', 'Hari Libur')

@section('content')
    @php $rute = request()->routeIs('admin.*') ? 'admin' : 'hrd'; @endphp
    <div class="grid gap-6 {{ auth()->user()?->isAdmin() ? 'lg:grid-cols-3' : '' }}">
        <div class="{{ auth()->user()?->isAdmin() ? 'lg:col-span-2' : '' }}">
            <form method="GET" data-ajax-filter data-ajax-target="#ajax-results" class="mb-3 flex items-center gap-2">
                <input type="number" name="tahun" value="{{ $tahun }}" class="input w-28">
            </form>
            <div id="ajax-results">
            <div class="table-wrap">
                <table class="table-base">
                    <thead><tr><th>Tanggal</th><th>Keterangan</th>@if (auth()->user()?->isAdmin())<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($libur as $l)
                            <tr>
                                <td class="whitespace-nowrap">{{ $l->tanggal->translatedFormat('l, d F Y') }}</td>
                                <td>{{ $l->nama }}</td>
                                @if (auth()->user()?->isAdmin())
                                    <td class="text-right">
                                        <form method="POST" action="{{ route($rute.'.kalender.destroy', $l) }}" data-konfirmasi="Hapus hari libur ini?">
                                            @csrf @method('DELETE')<button class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()?->isAdmin() ? 3 : 2 }}" class="py-8 text-center text-gray-500">Belum ada hari libur untuk {{ $tahun }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>
        </div>

        @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('admin.kalender.store') }}" class="card h-fit space-y-4 p-5">
                @csrf
                <h2 class="font-bold">Tambah Hari Libur</h2>
                <x-field name="tanggal" label="Tanggal" type="date" required />
                <x-field name="nama" label="Keterangan" placeholder="Contoh: Hari Kemerdekaan" required />
                <button class="btn btn-primary w-full">Tambah</button>
                <p class="text-xs text-gray-500">Akhir pekan sudah diatur otomatis lewat <code>config/absensi.php</code>. Di sini hanya libur nasional / cuti bersama. Pada hari libur karyawan tidak bisa absen dan tidak ditandai alpa.</p>
            </form>
        @endif
    </div>
@endsection
