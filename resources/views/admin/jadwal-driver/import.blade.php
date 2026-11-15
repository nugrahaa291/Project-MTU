@extends('layouts.panel')
@section('judul', 'Import Jadwal Libur Driver')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.jadwal-driver.index') }}" class="text-xs font-semibold text-brand hover:underline">&larr; Kembali ke Kalender Jadwal</a>
        <h2 class="text-xl font-bold text-gray-900 mt-2">Import Jadwal Driver dari Kantor Pusat</h2>
        <p class="text-sm text-gray-500">Unggah file jadwal kerja dan libur driver dalam format Excel (.xlsx, .xls) atau CSV (.csv) untuk diproses oleh sistem operasional cabang.</p>
    </div>

    {{-- ALUR PROSES IMPORT --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-4 text-center">
        <div class="rounded-lg border-2 border-brand bg-brand/5 p-3">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand text-xs font-bold text-white mb-1">1</span>
            <p class="text-xs font-bold text-gray-900">Unggah Berkas</p>
            <p class="text-[11px] text-gray-500">Pilih file Excel/CSV</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 text-gray-400">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-600 mb-1">2</span>
            <p class="text-xs font-bold text-gray-700">Validasi & Preview</p>
            <p class="text-[11px] text-gray-400">Pencocokan NIK/NIP & bentrok</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 text-gray-400">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-600 mb-1">3</span>
            <p class="text-xs font-bold text-gray-700">Konfirmasi Import</p>
            <p class="text-[11px] text-gray-400">Persetujuan & deteksi timpa</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 text-gray-400">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-600 mb-1">4</span>
            <p class="text-xs font-bold text-gray-700">Tersimpan</p>
            <p class="text-[11px] text-gray-400">Sinkron ke kalender & absensi</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- FORM UNGGAH FILE --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.jadwal-driver.preview') }}" enctype="multipart/form-data" class="card space-y-5 p-6">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1">
                        Pilih File Jadwal Driver (.xlsx / .xls / .csv) <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" name="file_jadwal" required accept=".xlsx,.xls,.csv" class="input w-full text-sm">
                    <p class="text-xs text-gray-500 mt-1">Maksimal ukuran berkas 10MB. Sistem membaca sel secara aman tanpa mengeksekusi formula/macro.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tahun Periode Jadwal</label>
                        <input type="number" name="tahun" value="{{ date('Y') }}" min="2020" max="2035" class="input w-full text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Bulan Default (Bila Judul Kolom Tanggal Tanpa Bulan)</label>
                        <select name="bulan" class="input w-full text-xs">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ (int) date('n') === $m ? 'selected' : '' }}>
                                    {{ \Carbon\CarbonImmutable::createFromDate(2026, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-xs text-blue-900 leading-relaxed">
                    <span class="font-bold">Keamanan & Integritas:</span> Berkas yang diunggah tidak langsung disimpan ke basis data. Anda akan melihat ringkasan validasi (driver cocok, driver tidak ditemukan, potensi jadwal bentrok) terlebih dahulu pada halaman preview sebelum menyetujuinya.
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('admin.jadwal-driver.index') }}" class="btn btn-outline">Batal</a>
                    <button type="submit" class="btn btn-primary">Unggah & Lihat Preview &rarr;</button>
                </div>
            </form>
        </div>

        {{-- PANDUAN FORMAT & TEMPLATE UNDUHAN --}}
        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-2">Unduh Template Standar</h3>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">Gunakan format file resmi agar pembacaan kolom NIP, NIK MTU, dan kode harian driver akurat 100%.</p>

                <div class="space-y-2">
                    <a href="{{ route('admin.jadwal-driver.template', ['format' => 'matriks']) }}" class="btn btn-outline btn-sm w-full text-left justify-between flex">
                        <span>Format 1: Matriks Kantor Pusat (.xlsx)</span>
                        <span>&darr;</span>
                    </a>
                    <a href="{{ route('admin.jadwal-driver.template', ['format' => 'tabel_standar']) }}" class="btn btn-outline btn-sm w-full text-left justify-between flex">
                        <span>Format 2: Tabel Standar (.xlsx)</span>
                        <span>&darr;</span>
                    </a>
                </div>
            </div>

            <div class="card p-5 text-xs text-gray-600 space-y-2">
                <h4 class="font-bold text-gray-800">Aturan Pembacaan Format Matriks:</h4>
                <ul class="list-disc pl-4 space-y-1">
                    <li>Kolom identitas: <code>No</code>, <code>Nama Driver</code>, <code>NIP</code>, <code>NIK MTU</code>, <code>PROJECT</code>.</li>
                    <li>Kolom tanggal: <code>01-Sep</code>, <code>02-Sep</code>, ..., atau angka tanggal <code>1</code>..<code>31</code>.</li>
                    <li>Kode harian: <strong>L</strong> = Libur Terjadwal, <strong>1</strong> = Hari Kerja.</li>
                    <li>Pencocokan driver diprioritaskan lewat <strong>NIK MTU</strong> dan <strong>NIP</strong>.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- RIWAYAT BATCH IMPORT TERAKHIR --}}
    @if ($batchList->isNotEmpty())
        <div class="mt-8">
            <h3 class="text-sm font-bold text-gray-900 mb-3">Riwayat Batch Import Jadwal Driver</h3>
            <div class="table-wrap">
                <table class="table-base text-xs">
                    <thead>
                        <tr>
                            <th>Nomor Batch</th>
                            <th>Nama Berkas</th>
                            <th>Periode</th>
                            <th>Format</th>
                            <th class="text-center">Total Driver</th>
                            <th class="text-center">Jadwal Kerja</th>
                            <th class="text-center">Jadwal Libur</th>
                            <th>Diupload Oleh</th>
                            <th>Waktu Import</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batchList as $b)
                            <tr>
                                <td class="font-mono font-bold text-gray-900">{{ $b->nomor_batch }}</td>
                                <td class="text-gray-700 max-w-xs truncate">{{ $b->nama_file }}</td>
                                <td>{{ $b->periode_bulan }}</td>
                                <td><span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">{{ ucfirst($b->format_import) }}</span></td>
                                <td class="text-center font-bold">{{ $b->total_driver }}</td>
                                <td class="text-center text-emerald-700 font-semibold">{{ $b->total_kerja }}</td>
                                <td class="text-center text-rose-700 font-semibold">{{ $b->total_libur }}</td>
                                <td>{{ $b->uploader?->name ?? 'Admin' }}</td>
                                <td class="text-gray-400">{{ $b->created_at->translatedFormat('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
