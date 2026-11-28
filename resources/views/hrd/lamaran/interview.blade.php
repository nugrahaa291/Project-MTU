@extends('layouts.panel')
@section('judul', 'Hasil Interview')

@section('content')
    @php $rute = request()->routeIs('admin.*') ? 'admin' : 'hrd'; @endphp
    <nav aria-label="Tahap interview" class="mb-4 flex flex-wrap gap-2">
        @foreach (['menunggu' => 'Menunggu interview', 'dinilai' => 'Sudah dinilai'] as $nilai => $label)
            <a href="{{ route($rute.'.interview.index', ['tahap' => $nilai]) }}"
               class="btn {{ $tahap === $nilai ? 'btn-primary' : 'btn-outline' }}" @if ($tahap === $nilai) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="table-wrap">
        <table class="table-base">
            <thead><tr><th>Pelamar</th><th>Lowongan</th><th>Lolos berkas</th>@if ($tahap === 'dinilai')<th>Interview</th><th>Rekomendasi</th><th>Status</th>@endif<th></th></tr></thead>
            <tbody>
                @forelse ($lamaran as $item)
                    <tr>
                        <td>{{ $item->nama }}<p class="text-xs text-gray-500">{{ $item->kode }}</p></td>
                        <td>{{ $item->lowongan->judul }}</td>
                        <td>{{ $item->lolos_berkas_pada->format('d/m/Y') }}</td>
                        @if ($tahap === 'dinilai')
                            <td>{{ $item->tanggal_interview->format('d/m/Y') }} · {{ $item->pewawancara }}</td>
                            <td>{{ $item->rekomendasi_interview === 'lanjut' ? 'Lanjut' : 'Tidak lanjut' }}</td>
                            <td>{{ $item->statusPublik() }}</td>
                        @endif
                        <td><a class="btn btn-outline btn-sm" href="{{ route($rute.'.lamaran.show', $item) }}#interview">{{ $tahap === 'dinilai' ? 'Lihat hasil' : 'Isi hasil' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $tahap === 'dinilai' ? 7 : 4 }}" class="py-8 text-center text-gray-500">{{ $tahap === 'dinilai' ? 'Belum ada hasil interview.' : 'Tidak ada pelamar yang menunggu interview.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $lamaran->links() }}</div>
@endsection
