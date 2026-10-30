@extends('layouts.panel')
@section('judul', 'Rekap Absensi')

@section('content')
    <form method="GET" class="mb-4 flex items-center gap-2">
        <input type="month" name="bulan" value="{{ $bulan }}" class="input w-48">
        <button class="btn btn-dark">Tampilkan</button>
        <span class="text-sm text-gray-500">{{ $awal->translatedFormat('F Y') }}</span>
    </form>

    <div class="table-wrap">
        <table class="table-base">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    @foreach (\App\Enums\StatusAbsensi::cases() as $s)<th class="text-center">{{ $s->label() }}</th>@endforeach
                    <th class="text-center">Total Telat (mnt)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td><p class="font-medium">{{ $b['karyawan']->nama }}</p><p class="font-mono text-xs text-gray-500">{{ $b['karyawan']->nip }}</p></td>
                        @foreach (\App\Enums\StatusAbsensi::cases() as $s)
                            <td class="text-center">{{ $b['jumlah'][$s->value] ?: '-' }}</td>
                        @endforeach
                        <td class="text-center">{{ $b['menit_terlambat'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-gray-500">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-gray-500">Kehadiran efektif = Hadir + Terlambat. Alpa ditandai otomatis tiap malam untuk hari kerja tanpa absensi.</p>
@endsection
