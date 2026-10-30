@extends('layouts.panel')
@section('judul', 'Absensi')

@section('content')
    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-5">
        <input type="date" name="dari" value="{{ $dari }}" class="input">
        <input type="date" name="sampai" value="{{ $sampai }}" class="input">
        <select name="karyawan_id" class="input">
            <option value="">Semua karyawan</option>
            @foreach ($karyawan as $k)
                <option value="{{ $k->id }}" @selected(request('karyawan_id') == $k->id)>{{ $k->nama }}</option>
            @endforeach
        </select>
        <select name="status" class="input">
            <option value="">Semua status</option>
            @foreach (\App\Enums\StatusAbsensi::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark">Tampilkan</button>
    </form>

    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Jarak</th><th>Foto</th></tr></thead>
            <tbody>
                @forelse ($absensi as $a)
                    <tr>
                        <td class="whitespace-nowrap">{{ $a->tanggal->format('d/m/Y') }}</td>
                        <td><p class="font-medium">{{ $a->karyawan->nama }}</p><p class="text-xs text-gray-500">{{ $a->karyawan->jabatan->nama }}</p></td>
                        <td>{{ $a->jam_masuk ? substr($a->jam_masuk, 0, 5) : '-' }}
                            @if ($a->menit_terlambat) <span class="text-xs text-amber-600">(+{{ $a->menit_terlambat }} mnt)</span> @endif</td>
                        <td>{{ $a->jam_pulang ? substr($a->jam_pulang, 0, 5) : '-' }}</td>
                        <td><x-badge :warna="$a->status->warna()">{{ $a->status->label() }}</x-badge>
                            @if ($a->keterangan)<p class="mt-1 max-w-xs text-xs text-gray-500">{{ $a->keterangan }}</p>@endif</td>
                        <td class="text-xs text-gray-600">
                            @if ($a->jarak_masuk !== null) M: {{ $a->jarak_masuk }} m @endif
                            @if ($a->jarak_pulang !== null)<br>P: {{ $a->jarak_pulang }} m @endif
                        </td>
                        <td class="space-x-2 whitespace-nowrap text-xs font-semibold">
                            @if ($a->foto_masuk)<a class="text-brand" target="_blank" href="{{ route('hrd.absensi.foto', [$a, 'masuk']) }}">Masuk</a>@endif
                            @if ($a->foto_pulang)<a class="text-brand" target="_blank" href="{{ route('hrd.absensi.foto', [$a, 'pulang']) }}">Pulang</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-gray-500">Tidak ada data pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $absensi->links() }}</div>

    <h2 class="mt-8 mb-3 text-lg font-bold">Catat Izin / Sakit / Cuti / Alpa</h2>
    <form method="POST" action="{{ route('hrd.absensi.store') }}" class="card grid max-w-4xl gap-4 p-5 sm:grid-cols-4">
        @csrf
        <x-field name="karyawan_id" label="Karyawan" as="select" required>
            <option value="">Pilih...</option>
            @foreach ($karyawan as $k)<option value="{{ $k->id }}" @selected(old('karyawan_id') == $k->id)>{{ $k->nama }}</option>@endforeach
        </x-field>
        <x-field name="tanggal" label="Tanggal" type="date" :value="$hariIni" required />
        <x-field name="status" label="Status" as="select" required>
            @foreach (\App\Enums\StatusAbsensi::manual() as $s)<option value="{{ $s->value }}" @selected(old('status') === $s->value)>{{ $s->label() }}</option>@endforeach
        </x-field>
        <x-field name="keterangan" label="Keterangan" />
        <div class="sm:col-span-4"><button class="btn btn-primary">Simpan Catatan</button></div>
    </form>
@endsection
