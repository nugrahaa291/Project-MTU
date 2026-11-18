@extends('layouts.panel')
@section('judul', 'Pengajuan Izin')

@section('content')
    <p class="mb-4 text-sm text-gray-600">
        Pengajuan izin <strong>Lain-lain</strong> hanya dapat disetujui setelah kamu memilih <strong>karyawan pengganti</strong>.
        Bila tidak ada pengganti, tolak pengajuan beserta alasannya.
    </p>

    <h2 class="mb-3 text-lg font-bold">Menunggu keputusan ({{ $menunggu->count() }})</h2>
    <div class="space-y-4">
        @forelse ($menunggu as $g)
            @php $tgl = $g->tanggal->toDateString(); $w = $g->waktuWib(); @endphp
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-lg font-bold">{{ $g->karyawan->nama }}</p>
                        <p class="text-sm text-gray-500">{{ $g->karyawan->nip }} &middot; {{ $g->karyawan->jabatan->nama }}</p>
                    </div>
                    <div class="text-right text-sm text-gray-500">
                        <p>Izin tanggal <strong class="text-ink">{{ $g->tanggal->translatedFormat('d F Y') }}</strong></p>
                        <p>Diajukan {{ $w->format('d/m/Y H:i') }} WIB</p>
                    </div>
                </div>
                <p class="mt-3 rounded-md bg-gray-50 p-3 text-sm whitespace-pre-line">{{ $g->alasan }}</p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('hrd.izin.setujui', $g) }}" class="space-y-2" data-konfirmasi="Setujui izin ini dengan pengganti yang dipilih?">
                        @csrf
                        <label class="label" for="pengganti-{{ $g->id }}">Karyawan pengganti (wajib)</label>
                        <select id="pengganti-{{ $g->id }}" name="pengganti_id" class="input" required>
                            <option value="">Pilih pengganti...</option>
                            @foreach ($kandidat as $k)
                                @continue($k->id === $g->karyawan_id)
                                @php $izin = in_array($k->id, $sedangIzin[$tgl] ?? [], true); @endphp
                                <option value="{{ $k->id }}" @disabled($izin)>{{ $k->nama }} ({{ $k->nip }}){{ $izin ? ' - sedang izin' : '' }}</option>
                            @endforeach
                        </select>
                        <button class="btn w-full bg-green-600 text-white hover:bg-green-700">Setujui</button>
                    </form>

                    <form method="POST" action="{{ route('hrd.izin.tolak', $g) }}" class="space-y-2" data-konfirmasi="Tolak pengajuan izin ini?">
                        @csrf
                        <label class="label" for="alasan-{{ $g->id }}">Alasan penolakan (wajib bila ditolak)</label>
                        <input id="alasan-{{ $g->id }}" name="alasan_penolakan" class="input" placeholder="Contoh: belum ada karyawan pengganti" maxlength="500">
                        <button class="btn btn-danger w-full">Tolak</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card p-8 text-center text-gray-500">Tidak ada pengajuan yang menunggu.</div>
        @endforelse
    </div>

    <h2 class="mt-8 mb-3 text-lg font-bold">Keputusan terakhir</h2>
    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Karyawan</th><th>Tanggal izin</th><th>Keputusan</th><th>Keterangan</th><th>Oleh</th></tr></thead>
            <tbody>
                @forelse ($diputuskan as $g)
                    <tr>
                        <td class="font-medium">{{ $g->karyawan->nama }}</td>
                        <td class="whitespace-nowrap">{{ $g->tanggal->format('d/m/Y') }}</td>
                        <td><x-badge :warna="$g->status->warna()">{{ $g->status->label() }}</x-badge></td>
                        <td class="text-sm">
                            @if ($g->pengganti) Pengganti: <strong>{{ $g->pengganti->nama }}</strong> @endif
                            @if ($g->alasan_penolakan) {{ $g->alasan_penolakan }} @endif
                        </td>
                        <td class="text-xs text-gray-500">{{ $g->pemutus?->name }}<br>{{ $g->diputuskan_pada?->copy()->timezone(config('absensi.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-gray-500">Belum ada keputusan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
