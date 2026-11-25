@props(['item'])
<article class="card group flex flex-col p-6 transition hover:-translate-y-1 hover:shadow-lg">
    <x-badge warna="brand" class="self-start">{{ $item->jabatan->nama }}</x-badge>
    <h3 class="mt-3 text-lg font-bold group-hover:text-brand">{{ $item->judul }}</h3>

    <dl class="mt-3 space-y-1 text-sm text-gray-600">
        <div>Lokasi: <span class="font-medium text-ink">{{ $item->lokasi }}</span></div>
        <div>Ditutup: <span class="font-medium text-ink">{{ $item->tanggal_tutup->translatedFormat('d F Y') }}</span></div>
        <div>Sisa kuota: <span class="font-medium text-ink">{{ $item->sisaKuota() }} orang</span></div>
    </dl>

    <a href="{{ route('lowongan.show', $item) }}" class="btn btn-primary mt-5 self-start">Lihat Detail</a>
</article>
