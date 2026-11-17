@extends('layouts.panel')
@section('judul', 'Preview & Validasi Import Jadwal Driver')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.jadwal-driver.import') }}" class="text-xs font-semibold text-brand hover:underline">&larr; Kembali ke Form Unggah</a>
            <h2 class="text-xl font-bold text-gray-900 mt-2">Preview & Hasil Validasi Berkas Jadwal</h2>
            <p class="text-sm text-gray-500">Nama Berkas: <strong class="text-gray-800 font-mono">{{ $namaFile }}</strong></p>
        </div>
        <div class="flex gap-2">
            @if (! empty($preview['jadwal_bermasalah']))
                <a href="{{ route('admin.jadwal-driver.download-invalid', ['token' => $token]) }}" class="btn btn-outline btn-sm text-rose-700 border-rose-300">
                    &darr; Unduh Baris Bermasalah (.csv)
                </a>
            @endif
        </div>
    </div>

    {{-- KARTU RINGKASAN HASIL BACA STRUKTUR & VALIDASI --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Format Berkas</p>
            <p class="mt-1 text-lg font-bold text-gray-900">
                {{ $preview['format'] === 'matriks' ? 'Matriks Kantor Pusat' : 'Tabel Standar' }}
            </p>
            <p class="mt-1 text-xs text-gray-400">Periode: {{ $preview['periode_mulai'] }} s/d {{ $preview['periode_selesai'] }}</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Driver Teridentifikasi</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $preview['total_driver'] }} Orang</p>
            <p class="mt-1 text-xs text-gray-400">{{ count($preview['jadwal_valid']) }} catatan tanggal valid</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Jadwal Kerja vs Libur</p>
            <p class="mt-1 text-xl font-bold text-gray-900">
                <span class="text-emerald-600">{{ $preview['total_kerja'] }} Kerja</span> &bull;
                <span class="text-rose-600">{{ $preview['total_libur'] }} Libur</span>
            </p>
            <p class="mt-1 text-xs text-gray-400">Kode harian terbaca: '1' &amp; 'L'</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-gray-500 uppercase font-semibold">Status Basis Data</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">
                <span class="text-emerald-700">{{ $preview['analisis']['data_baru'] }} Baru</span> &bull;
                <span class="text-amber-700">{{ $preview['analisis']['data_menimpa'] }} Berubah</span> &bull;
                <span class="text-gray-500">{{ $preview['analisis']['data_sama'] }} Sama</span>
            </p>
            @if ($preview['analisis']['konflik_kebutuhan_proyek'] > 0)
                <p class="mt-1 text-[11px] text-rose-600 font-bold">&bull; {{ $preview['analisis']['konflik_kebutuhan_proyek'] }} peringatan kebutuhan minimum</p>
            @else
                <p class="mt-1 text-[11px] text-emerald-600">&bull; Kebutuhan minimum driver aman</p>
            @endif
        </div>
    </div>

    {{-- ALERT BARIS BERMASALAH (JIKA ADA) --}}
    @if (! empty($preview['jadwal_bermasalah']))
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-amber-900">Perhatian: Ditemukan {{ count($preview['jadwal_bermasalah']) }} Baris Bermasalah</h3>
                    <p class="text-xs text-amber-700 mt-0.5">Baris di bawah ini dilewati secara aman karena identitas driver (NIK/NIP) tidak cocok atau kode harian tidak valid. Baris valid tetap dapat diimpor.</p>
                </div>
                <a href="{{ route('admin.jadwal-driver.download-invalid', ['token' => $token]) }}" class="btn btn-outline btn-sm text-xs shrink-0">
                    Unduh CSV Baris Gagal
                </a>
            </div>

            <div class="mt-3 max-h-48 overflow-y-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-amber-200 text-amber-800 font-bold">
                            <th class="py-1">Baris</th>
                            <th class="py-1">Nama Driver</th>
                            <th class="py-1">NIK MTU</th>
                            <th class="py-1">NIP</th>
                            <th class="py-1">Alasan Penolakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-200/50">
                        @foreach (array_slice($preview['jadwal_bermasalah'], 0, 10) as $err)
                            <tr>
                                <td class="py-1 font-mono text-amber-900">{{ $err['baris'] ?? '-' }}</td>
                                <td class="py-1 font-medium text-amber-950">{{ $err['nama'] }}</td>
                                <td class="py-1 font-mono text-amber-800">{{ $err['nik'] }}</td>
                                <td class="py-1 font-mono text-amber-800">{{ $err['nip'] }}</td>
                                <td class="py-1 text-rose-700 font-semibold">{{ $err['alasan'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (count($preview['jadwal_bermasalah']) > 10)
                    <p class="text-[11px] text-amber-800 mt-2 italic">Menampilkan 10 dari total {{ count($preview['jadwal_bermasalah']) }} baris bermasalah. Unduh CSV untuk daftar lengkap.</p>
                @endif
            </div>
        </div>
    @endif

    {{-- ALERT PERINGATAN KEBUTUHAN MINIMUM PROYEK --}}
    @if (! empty($preview['analisis']['peringatan_kebutuhan']))
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4">
            <h3 class="text-sm font-bold text-rose-900">Peringatan Kebutuhan Minimum Driver per Proyek:</h3>
            <ul class="mt-2 list-disc pl-5 text-xs text-rose-800 space-y-1">
                @foreach (array_slice($preview['analisis']['peringatan_kebutuhan'], 0, 5) as $pr)
                    <li>{{ $pr['pesan'] }}</li>
                @endforeach
            </ul>
            <p class="text-[11px] text-rose-700 mt-2">Jadwal pada tanggal di atas tetap dapat diimpor dan akan otomatis berstatus <strong>Perlu Verifikasi Admin</strong> agar Anda dapat menentukan driver pengganti di menu jadwal.</p>
        </div>
    @endif

    {{-- TABEL PREVIEW DATA VALID (SAMPLE) --}}
    <div class="card mb-6 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900">Cuplikan Data Jadwal Siap Diimpor ({{ count($preview['jadwal_valid']) }} Catatan)</h3>
            <span class="text-xs text-gray-500">Menampilkan hingga 20 baris pertama</span>
        </div>
        <div class="table-wrap">
            <table class="table-base text-xs">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Nama Driver</th>
                        <th>NIP & NIK MTU</th>
                        <th>Proyek</th>
                        <th>Tanggal</th>
                        <th class="text-center">Status Jadwal</th>
                        <th>Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_slice($preview['jadwal_valid'], 0, 20) as $idx => $v)
                        <tr>
                            <td class="text-center text-gray-400">{{ $idx + 1 }}</td>
                            <td class="font-medium text-gray-900">{{ $v['karyawan_nama'] }}</td>
                            <td class="font-mono text-gray-600">NIP: {{ $v['karyawan_nip'] }} &bull; NIK: {{ $v['karyawan_nik'] }}</td>
                            <td><span class="rounded bg-gray-100 px-1.5 py-0.5 font-semibold text-gray-700">{{ $v['proyek'] }}</span></td>
                            <td class="font-medium text-gray-800">{{ $v['tanggal'] }}</td>
                            <td class="text-center">
                                <span class="rounded px-2 py-0.5 font-semibold {{ $v['status_jadwal'] === 'libur' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ ucfirst($v['status_jadwal']) }}
                                </span>
                            </td>
                            <td class="text-gray-500">{{ $v['keterangan'] ?? 'Import' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- FORM KONFIRMASI DAN EKSEKUSI DATABASE --}}
    <form method="POST" action="{{ route('admin.jadwal-driver.store-import') }}" class="card p-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <h3 class="text-sm font-bold text-gray-900">Konfirmasi Penyimpanan Jadwal ke Basis Data</h3>

        <div class="space-y-2">
            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="konfirmasi" value="1" required class="rounded border-gray-300 text-brand mt-0.5">
                <span class="text-xs text-gray-700 leading-relaxed">
                    Saya menyatakan telah memeriksa hasil validasi berkas di atas dan menyetujui penyimpanan jadwal driver ke dalam basis data <code>portal_pt</code> operasional cabang MTU.
                </span>
            </label>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="timpa_jadwal_lama" value="1" checked class="rounded border-gray-300 text-brand mt-0.5">
                <span class="text-xs text-gray-700 leading-relaxed">
                    Perbarui data jika jadwal pada tanggal tersebut sudah ada sebelumnya (idempotent &amp; mencegah duplikasi data ganda).
                </span>
            </label>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
            <a href="{{ route('admin.jadwal-driver.import') }}" class="btn btn-outline btn-sm">&larr; Batal / Unggah Ulang</a>
            <button type="submit" class="btn btn-primary">
                ✓ Konfirmasi &amp; Simpan Jadwal Driver
            </button>
        </div>
    </form>
@endsection
