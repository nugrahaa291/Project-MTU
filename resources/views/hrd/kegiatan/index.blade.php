@extends('layouts.panel')
@section('judul', 'Aktivitas Absensi')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <label class="block text-sm font-semibold">Dari tanggal
            <input type="date" name="dari" value="{{ $dari }}" class="input">
        </label>
        <label class="block text-sm font-semibold">Sampai tanggal
            <input type="date" name="sampai" value="{{ $sampai }}" class="input">
        </label>
        <label class="block text-sm font-semibold">Karyawan
            <select name="karyawan_id" class="input">
                <option value="">Semua karyawan</option>
                @foreach ($karyawan as $k)
                    <option value="{{ $k->id }}" @selected(request('karyawan_id') == $k->id)>{{ $k->nama }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-semibold">Jenis kegiatan
            <select name="jenis" class="input">
                <option value="">Semua jenis</option>
                @foreach (\App\Enums\JenisKegiatan::cases() as $j)
                    <option value="{{ $j->value }}" @selected(request('jenis') === $j->value)>{{ $j->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-semibold">Status kegiatan
            <select name="status" class="input">
                <option value="">Semua status</option>
                @foreach (\App\Enums\StatusKegiatan::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn btn-dark">Tampilkan</button>
    </form>

    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Waktu (WIB)</th><th>Karyawan</th><th>Jenis</th><th>Detail</th><th>Status</th><th>Berkas</th></tr></thead>
            <tbody>
                @forelse ($kegiatan as $g)
                    @php $w = $g->waktuWib(); @endphp
                    <tr>
                        <td class="whitespace-nowrap"><p class="font-medium">{{ $w->format('H:i:s') }}</p><p class="text-xs text-gray-500">{{ $w->format('d/m/Y') }}</p></td>
                        <td><p class="font-medium">{{ $g->karyawan->nama }}</p><p class="text-xs text-gray-500">{{ $g->karyawan->nip }} &middot; {{ $g->karyawan->jabatan->nama }}</p></td>
                        <td>
                            <x-badge :warna="match($g->jenis->value) { 'standby' => 'blue', 'order' => 'brand', default => 'slate' }">{{ $g->jenis->label() }}</x-badge>
                            @if ($g->kategori_izin)<p class="mt-1 text-xs text-gray-500">{{ $g->kategori_izin->label() }}</p>@endif
                        </td>
                        <td class="max-w-sm text-sm">
                            @if ($g->jenis === \App\Enums\JenisKegiatan::Order)
                                <p><span class="text-gray-500">Tujuan:</span> <strong>{{ $g->tujuan }}</strong></p>
                                <p class="text-gray-600"><span class="text-gray-500">Lokasi HP:</span>
                                    {{ $g->nama_lokasi ?: number_format($g->lat_aktual, 6).', '.number_format($g->lng_aktual, 6) }}
                                    @if ($g->akurasi_meter !== null) <span class="text-xs">(±{{ $g->akurasi_meter }} m)</span> @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ number_format($g->lat_aktual, 6) }}, {{ number_format($g->lng_aktual, 6) }}
                                    @if ($g->jarak_kantor_meter !== null) &middot; {{ number_format($g->jarak_kantor_meter, 0, ',', '.') }} m dari kantor @endif
                                    &middot; <a class="font-semibold text-brand" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat={{ $g->lat_aktual }}&mlon={{ $g->lng_aktual }}#map=17/{{ $g->lat_aktual }}/{{ $g->lng_aktual }}">Peta</a>
                                </p>
                            @elseif ($g->jenis === \App\Enums\JenisKegiatan::Izin)
                                @if ($g->alasan)<p>{{ $g->alasan }}</p>@endif
                                @if ($g->pengganti)<p class="text-xs text-gray-500">Pengganti: <strong>{{ $g->pengganti->nama }}</strong></p>@endif
                                @if ($g->alasan_penolakan)<p class="text-xs text-red-600">Ditolak: {{ $g->alasan_penolakan }}</p>@endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td><x-badge :warna="$g->status->warna()">{{ $g->status->label() }}</x-badge></td>
                        <td class="space-x-2 whitespace-nowrap text-xs font-semibold">
                            @if ($g->foto)<a class="text-brand" target="_blank" href="{{ route('hrd.kegiatan.foto', $g) }}">Selfie</a>@endif
                            @if ($g->lampiran)<a class="text-brand" target="_blank" href="{{ route('hrd.kegiatan.lampiran', $g) }}">SKD</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-gray-500">Tidak ada aktivitas pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $kegiatan->links() }}</div>
@endsection
