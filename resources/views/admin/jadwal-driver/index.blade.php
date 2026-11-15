@extends('layouts.panel')
@section('judul', 'Jadwal Libur Driver')

@section('content')
    {{-- A. JUDUL DAN SUBJUDUL & B. EMPAT TOMBOL AKSI --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Jadwal Libur Driver</h2>
            <p class="text-sm text-gray-500">Kelola jadwal kerja dan libur driver PT Multi Talent Universal berdasarkan data kantor pusat maupun perubahan operasional cabang.</p>
        </div>

        {{-- B. EMPAT TOMBOL AKSI UTAMA (1 BARIS DI DESKTOP, RAPI MEMBUNGKUS DI MOBILE) --}}
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Tombol 1: Tambah Libur Driver --}}
            <button type="button" onclick="document.getElementById('modalTambahManual').showModal()" class="btn btn-primary btn-sm whitespace-nowrap">
                + Tambah Libur Driver
            </button>

            {{-- Tombol 2: Import Jadwal Libur --}}
            <a href="{{ route('admin.jadwal-driver.import') }}" class="btn btn-dark btn-sm whitespace-nowrap">
                &uarr; Import Jadwal Libur
            </a>

            {{-- Tombol 3: Template Excel --}}
            <a href="{{ route('admin.jadwal-driver.template') }}" class="btn btn-outline btn-sm whitespace-nowrap" title="Unduh Template Excel Standar (.xlsx)">
                &darr; Template Excel
            </a>

            {{-- Tombol 4: Kalender --}}
            <button type="button" onclick="bukaModalKalender()" class="btn btn-outline btn-sm whitespace-nowrap" title="Buka Kalender Bulanan Interaktif">
                📅 Kalender
            </button>
        </div>
    </div>

    {{-- C. PANEL PENCARIAN DAN FILTER --}}
    <div class="card mb-6 p-4">
        <form method="GET" action="{{ route('admin.jadwal-driver.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 items-end">
            {{-- Search Bar: Nama Driver / NIK MTU / NIP --}}
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Cari Driver (Nama / NIK MTU / NIP)</label>
                <input type="text" name="cari" value="{{ $filter['cari'] }}" placeholder="Ketik nama, NIP, atau NIK..." class="input w-full text-xs">
            </div>

            {{-- Filter Proyek --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Proyek</label>
                <select name="proyek" class="input w-full text-xs">
                    <option value="">Semua Proyek</option>
                    @foreach ($daftarProyek as $p)
                        <option value="{{ $p }}" {{ $filter['proyek'] === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tanggal Mulai --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ $filter['tanggal_mulai'] ?? '' }}" class="input w-full text-xs">
            </div>

            {{-- Tanggal Akhir --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" value="{{ $filter['tanggal_akhir'] ?? '' }}" class="input w-full text-xs">
            </div>

            {{-- Filter Status Libur --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Status</label>
                <select name="status" class="input w-full text-xs">
                    <option value="">Semua Status</option>
                    <option value="libur" {{ $filter['status'] === 'libur' ? 'selected' : '' }}>Libur</option>
                    <option value="kerja" {{ $filter['status'] === 'kerja' ? 'selected' : '' }}>Kerja</option>
                </select>
            </div>

            {{-- Tombol Filter & Reset --}}
            <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-gray-100 mt-1">
                <div class="text-xs text-gray-500">
                    @if (!empty($filter['cari']) || !empty($filter['proyek']) || !empty($filter['tanggal_mulai']) || !empty($filter['tanggal_akhir']) || !empty($filter['status']))
                        <span class="inline-flex items-center gap-1 text-brand font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-brand"></span> Filter kustom sedang aktif
                        </span>
                    @else
                        <span>Menampilkan jadwal periode: <strong>{{ \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}</strong></span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.jadwal-driver.index') }}" class="btn btn-outline btn-sm">Reset</a>
                    <button type="submit" class="btn btn-dark btn-sm">Cari / Filter</button>
                </div>
            </div>
        </form>
    </div>

    {{-- D. TABEL JADWAL LIBUR DRIVER --}}
    <div class="table-wrap">
        <table class="table-base">
            <thead>
                <tr>
                    <th class="w-12 text-center whitespace-nowrap">No</th>
                    <th class="whitespace-nowrap">Nama Driver</th>
                    <th class="whitespace-nowrap">NIP</th>
                    <th class="whitespace-nowrap">NIK MTU</th>
                    <th class="whitespace-nowrap">Proyek</th>
                    <th class="whitespace-nowrap">Tanggal Libur</th>
                    <th class="text-center whitespace-nowrap">Status</th>
                    <th class="whitespace-nowrap">Driver Pengganti</th>
                    <th class="whitespace-nowrap">Sumber Jadwal</th>
                    <th class="whitespace-nowrap">Keterangan</th>
                    <th class="text-right whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarJadwalTabel as $idx => $j)
                    <tr class="hover:bg-gray-50/60 transition">
                        <td class="text-center text-xs text-gray-400 font-mono">
                            {{ $daftarJadwalTabel->firstItem() + $idx }}
                        </td>
                        <td class="whitespace-nowrap">
                            <p class="font-semibold text-gray-900">{{ $j->karyawan?->nama }}</p>
                            <p class="text-[11px] text-gray-400">{{ $j->karyawan?->jabatan?->nama ?? 'Driver' }}</p>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="font-mono text-xs text-gray-700 font-medium">{{ $j->karyawan?->nip ?? '-' }}</span>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="font-mono text-xs text-gray-600">{{ $j->karyawan?->nik ?? '-' }}</span>
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                {{ $j->proyek ?? 'Operasional MTU' }}
                            </span>
                        </td>
                        <td class="font-medium text-gray-900 text-xs whitespace-nowrap">
                            {{ $j->tanggal->translatedFormat('d M Y') }}
                        </td>
                        <td class="text-center whitespace-nowrap">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $j->status_jadwal === 'libur' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ ucfirst($j->status_jadwal) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($j->driverPengganti)
                                <p class="text-xs font-semibold text-gray-900">{{ $j->driverPengganti->nama }}</p>
                                <p class="font-mono text-[10px] text-gray-400">NIP: {{ $j->driverPengganti->nip ?? $j->driverPengganti->nik }}</p>
                            @else
                                <span class="text-xs text-gray-400 italic">Tidak ada pengganti</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($j->sumber === 'import_pusat')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 whitespace-nowrap">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                    Import Kantor Pusat
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 border border-amber-200 whitespace-nowrap">
                                    <span class="h-1.5 w-1.5 rounded-full bg-brand"></span>
                                    Manual Cabang
                                </span>
                            @endif
                        </td>
                        <td class="text-xs text-gray-600 max-w-xs truncate" title="{{ $j->keterangan }}">
                            {{ $j->keterangan ?? '-' }}
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" onclick="bukaModalPengganti({{ $j->id }}, '{{ addslashes($j->karyawan?->nama ?? '') }}', '{{ $j->tanggal->format('Y-m-d') }}', '{{ $j->driver_pengganti_id }}')" class="btn btn-outline btn-sm text-[11px] py-1 px-2.5">
                                    Pengganti
                                </button>
                                <form method="POST" action="{{ route('admin.jadwal-driver.destroy', $j) }}" onsubmit="return confirm('Batalkan jadwal libur untuk {{ addslashes($j->karyawan?->nama ?? '') }} pada tanggal {{ $j->tanggal->format('d/m/Y') }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm text-[11px] py-1 px-2.5">Batal</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="py-12 text-center text-gray-500">
                            <div class="mx-auto max-w-sm text-center">
                                <p class="text-base font-semibold text-gray-800">Tidak ada jadwal driver ditemukan</p>
                                <p class="text-xs text-gray-400 mt-1">Belum ada data jadwal yang sesuai dengan filter atau periode yang Anda pilih.</p>
                                <div class="mt-4 flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.jadwal-driver.index') }}" class="btn btn-outline btn-sm">Reset Filter</a>
                                    <button type="button" onclick="document.getElementById('modalTambahManual').showModal()" class="btn btn-primary btn-sm">+ Tambah Libur</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- E. PAGINATION DAN JUMLAH DATA --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <p class="text-xs text-gray-500">
            Menampilkan <strong class="text-gray-900">{{ $daftarJadwalTabel->firstItem() ?? 0 }}</strong> sampai <strong class="text-gray-900">{{ $daftarJadwalTabel->lastItem() ?? 0 }}</strong> dari <strong class="text-gray-900">{{ $daftarJadwalTabel->total() }}</strong> jadwal driver.
        </p>
        <div>
            {{ $daftarJadwalTabel->links() }}
        </div>
    </div>

    {{-- MODAL KALENDER BERSIH (2-KOLOM SEJAJAR TERINSPIRASI DARI HALAMAN HARI LIBUR) --}}
    <dialog id="modalKalender" class="modal rounded-2xl p-0 backdrop:bg-black/60 shadow-2xl max-w-5xl w-full mx-auto my-auto overflow-hidden">
        <div class="flex flex-col">
            {{-- Header Modal --}}
            @php
                $currDate = \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1);
                $prevDate = $currDate->subMonth();
                $nextDate = $currDate->addMonth();
            @endphp
            <div class="flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-bold text-gray-900">Kalender Libur Driver</h3>
                    <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                        {{ $currDate->translatedFormat('F Y') }}
                    </span>
                </div>

                {{-- Navigasi Bulan & Tutup --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'kalender']), ['tahun' => $prevDate->year, 'bulan' => $prevDate->month, 'kalender' => 1])) }}" class="btn btn-outline btn-sm px-2.5" title="Bulan Sebelumnya">
                        &larr;
                    </a>
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'kalender']), ['tahun' => $nextDate->year, 'bulan' => $nextDate->month, 'kalender' => 1])) }}" class="btn btn-outline btn-sm px-2.5" title="Bulan Berikutnya">
                        &rarr;
                    </a>
                    <button type="button" onclick="tutupModalKalender()" class="btn btn-outline btn-sm ml-2">✕ Tutup</button>
                </div>
            </div>

            {{-- Body Modal: 2 Kolom Sejajar (Kiri Kalender 2 Spans, Kanan Rincian 1 Span) --}}
            <div class="p-6 bg-canvas">
                <div class="grid gap-6 lg:grid-cols-3 items-start">
                    {{-- Kolom Kiri: Kalender Grid Bulanan (Senin - Minggu) --}}
                    <div class="lg:col-span-2 card p-4">
                        {{-- Legend Bar Ringkas --}}
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100 text-xs text-gray-600">
                            <div class="flex items-center gap-4">
                                <span class="inline-flex items-center gap-1.5 font-medium">
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span> Titik Merah: Ada driver libur
                                </span>
                                <span class="inline-flex items-center gap-1.5 font-medium">
                                    <span class="h-2.5 w-2.5 rounded border border-brand bg-brand/20"></span> Hari Ini
                                </span>
                            </div>
                            <span class="text-gray-500">Total: <strong class="text-gray-800">{{ $kalender['total_driver_libur_bulan_ini'] }} hari libur</strong></span>
                        </div>

                        {{-- Tabel Grid Kalender 7 Kolom Proporsional --}}
                        <div class="w-full border border-gray-200 rounded-lg overflow-hidden bg-white">
                            {{-- Header Hari Horizontal --}}
                            <div class="grid grid-cols-7 bg-gray-50 text-center text-xs font-semibold text-gray-600 border-b border-gray-200">
                                <div class="py-2">Senin</div>
                                <div class="py-2">Selasa</div>
                                <div class="py-2">Rabu</div>
                                <div class="py-2">Kamis</div>
                                <div class="py-2">Jumat</div>
                                <div class="py-2 text-amber-600 font-bold">Sabtu</div>
                                <div class="py-2 text-rose-600 font-bold">Minggu</div>
                            </div>

                            {{-- Tanggal Bulanan (Pas di Layar, Tanpa Scroll Vertikal) --}}
                            <div class="grid grid-cols-7 divide-x divide-y divide-gray-200 bg-gray-50/40">
                                @foreach ($kalender['daftar_hari'] as $hari)
                                    @if (! $hari['apakah_bulan_ini'])
                                        <div class="h-14 sm:h-16 bg-gray-100/40 p-1.5"></div>
                                    @else
                                        @php
                                            $isToday = $hari['apakah_hari_ini'];
                                            $hasOff = $hari['ada_driver_libur'];
                                            $offCount = $hari['jumlah_driver_libur'];
                                            $tglStr = $hari['tanggal'];
                                        @endphp
                                        <div onclick="pilihTanggalKalender('{{ $tglStr }}', {{ json_encode($hari['daftar_driver_libur']) }})"
                                             class="cell-tanggal h-14 sm:h-16 p-1.5 transition cursor-pointer hover:bg-orange-50/60 relative flex flex-col justify-between {{ $isToday ? 'bg-orange-50/50 ring-2 ring-inset ring-brand' : 'bg-white' }}"
                                             data-tanggal="{{ $tglStr }}"
                                             title="Klik tanggal {{ $tglStr }}">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-semibold {{ $isToday ? 'rounded-full bg-brand text-white w-5 h-5 flex items-center justify-center text-[10px]' : 'text-gray-800' }}">
                                                    {{ $hari['nomor_hari'] }}
                                                </span>
                                                @if ($isToday)
                                                    <span class="text-[8px] font-bold text-brand uppercase">Hari Ini</span>
                                                @endif
                                            </div>

                                            @if ($hasOff)
                                                <div class="flex items-center gap-1 mt-auto">
                                                    <span class="h-2 w-2 rounded-full bg-rose-500 shrink-0"></span>
                                                    <span class="text-[10px] font-semibold text-rose-700 truncate">{{ $offCount }} Libur</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Kolom Kanan: Card Rincian Driver Libur (Gaya Form Card Bersih Seperti Gambar 3) --}}
                    <div class="card p-5 h-fit">
                        <div class="border-b border-gray-100 pb-3 mb-3">
                            <h4 class="font-bold text-gray-900 text-sm" id="kalenderDetailJudul">Rincian Driver Libur</h4>
                            <p class="text-xs text-gray-400 mt-0.5" id="kalenderDetailSubjudul">Pilih tanggal pada kalender</p>
                        </div>

                        <div id="kalenderDetailKonten" class="space-y-3 max-h-80 overflow-y-auto pr-1">
                            <p class="text-xs text-gray-400 italic py-8 text-center">Klik salah satu tanggal bertitik merah pada kalender untuk melihat rincian driver yang libur.</p>
                        </div>

                        <div class="pt-4 border-t border-gray-100 mt-4 text-xs text-gray-400">
                            Data jadwal libur operasional cabang PT Multi Talent Universal (MTU).
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </dialog>

    {{-- MODAL TAMBAH LIBUR DRIVER MANUAL (CABANG) --}}
    <dialog id="modalTambahManual" class="modal rounded-xl p-0 backdrop:bg-black/60 shadow-2xl max-w-lg w-full">
        <form method="POST" action="{{ route('admin.jadwal-driver.manual') }}" class="p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900">+ Tambah Libur Driver</h3>
                <button type="button" onclick="document.getElementById('modalTambahManual').close()" class="btn btn-outline btn-sm">✕</button>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Pilih Driver <span class="text-rose-500">*</span></label>
                <select name="karyawan_id" id="manual_karyawan_id" required class="input w-full text-xs" onchange="updateDriverInfo(this)">
                    <option value="">-- Pilih Driver --</option>
                    @foreach ($semuaDriver as $d)
                        <option value="{{ $d->id }}" data-nip="{{ $d->nip }}" data-nik="{{ $d->nik }}" data-proyek="{{ $d->proyek }}">
                            {{ $d->nama }} ({{ $d->nip ?: $d->nik }})
                        </option>
                    @endforeach
                </select>
                <div id="infoDriverTerpilih" class="mt-1 text-[11px] text-gray-500 hidden">
                    NIP: <span id="infoNip">-</span> &bull; NIK: <span id="infoNik">-</span> &bull; Proyek: <span id="infoProyek">-</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Mulai Libur <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_mulai" required value="{{ date('Y-m-d') }}" class="input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Selesai (Opsional)</label>
                    <input type="date" name="tanggal_selesai" class="input w-full text-xs" placeholder="Kosongkan jika 1 hari">
                    <p class="text-[10px] text-gray-400 mt-0.5">Isi jika libur rentang beberapa hari.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Proyek Penugasan</label>
                <input type="text" name="proyek" id="manual_proyek" placeholder="Contoh: Wingbox, Trailer, atau Operasional MTU" class="input w-full text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Driver Pengganti (Jika Diperlukan)</label>
                <select name="driver_pengganti_id" class="input w-full text-xs">
                    <option value="">-- Tanpa Driver Pengganti --</option>
                    @foreach ($semuaDriver as $d)
                        <option value="{{ $d->id }}">{{ $d->nama }} ({{ $d->nip ?: $d->nik }})</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-0.5">Wajib dipilih bila jumlah driver aktif proyek di bawah batas minimum.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan / Keterangan Libur</label>
                <textarea name="keterangan" rows="2" placeholder="Keperluan mendadak keluarga, urusan dinas, dll." class="input w-full text-xs"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modalTambahManual').close()" class="btn btn-outline btn-sm">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Jadwal Libur</button>
            </div>
        </form>
    </dialog>

    {{-- MODAL TENTUKAN DRIVER PENGGANTI --}}
    <dialog id="modalTentukanPengganti" class="modal rounded-xl p-0 backdrop:bg-black/60 shadow-2xl max-w-md w-full">
        <form method="POST" id="formPengganti" class="p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900">Tentukan Driver Pengganti</h3>
                <button type="button" onclick="document.getElementById('modalTentukanPengganti').close()" class="btn btn-outline btn-sm">✕</button>
            </div>

            <div>
                <p class="text-xs text-gray-600 mb-2">Driver Libur: <strong id="namaDriverLibur">-</strong> pada tanggal <strong id="tglDriverLibur">-</strong></p>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Pilih Driver Pengganti</label>
                <select name="driver_pengganti_id" id="selectDriverPengganti" class="input w-full text-xs">
                    <option value="">-- Tanpa Pengganti / Hapus Penugasan --</option>
                    @foreach ($semuaDriver as $d)
                        <option value="{{ $d->id }}">{{ $d->nama }} ({{ $d->nip ?: $d->nik }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Penugasan / Perubahan <span class="text-rose-500">*</span></label>
                <input type="text" name="alasan" required placeholder="Contoh: Menjaga kebutuhan operasional proyek Wingbox" class="input w-full text-xs">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modalTentukanPengganti').close()" class="btn btn-outline btn-sm">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Driver Pengganti</button>
            </div>
        </form>
    </dialog>

    {{-- JAVASCRIPT LOGIC --}}
    <script>
        function bukaModalKalender() {
            const modal = document.getElementById('modalKalender');
            if (modal) {
                modal.showModal();
                // Buka otomatis tanggal 15 atau tanggal pertama dengan driver libur
                const offCells = document.querySelectorAll('.cell-tanggal:has(.bg-rose-500)');
                if (offCells.length > 0) {
                    offCells[0].click();
                }
            }
        }

        function tutupModalKalender() {
            const modal = document.getElementById('modalKalender');
            if (modal) {
                modal.close();
            }
            // Bersihkan parameter kalender dari URL tanpa refresh halaman
            if (window.location.search.includes('kalender=')) {
                const url = new URL(window.location);
                url.searchParams.delete('kalender');
                window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ''));
            }
        }

        function pilihTanggalKalender(tanggal, daftarDriver) {
            // Highlight cell terpilih
            document.querySelectorAll('.cell-tanggal').forEach(c => {
                c.classList.remove('ring-2', 'ring-brand', 'bg-orange-50');
            });
            const cell = document.querySelector(`.cell-tanggal[data-tanggal="${tanggal}"]`);
            if (cell) {
                cell.classList.add('ring-2', 'ring-brand', 'bg-orange-50');
            }

            const judul = document.getElementById('kalenderDetailJudul');
            const subjudul = document.getElementById('kalenderDetailSubjudul');
            const konten = document.getElementById('kalenderDetailKonten');

            judul.textContent = `Driver Libur — ${tanggal}`;
            subjudul.textContent = daftarDriver && daftarDriver.length > 0
                ? `${daftarDriver.length} driver dijadwalkan libur`
                : 'Tidak ada driver libur';

            if (!daftarDriver || daftarDriver.length === 0) {
                konten.innerHTML = `<div class="p-4 text-center text-xs text-gray-400 bg-gray-50 rounded-lg">Tidak ada driver yang dijadwalkan libur pada tanggal ini. Armada beroperasi normal.</div>`;
            } else {
                let html = '';
                daftarDriver.forEach((d, idx) => {
                    html += `
                        <div class="border border-gray-200 rounded-lg p-3 bg-white space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900 text-xs">${idx + 1}. ${d.nama}</span>
                                <span class="rounded bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700">Libur</span>
                            </div>
                            <div class="text-[11px] text-gray-600 font-mono">
                                NIP: ${d.nip} &bull; Proyek: <strong class="font-sans text-gray-900">${d.proyek}</strong>
                            </div>
                            <div class="text-[11px] text-gray-500 pt-0.5">
                                Pengganti: <strong class="text-gray-800">${d.pengganti || 'Tidak ada'}</strong>
                            </div>
                            ${d.keterangan ? `<p class="text-[11px] text-gray-500 italic pt-0.5">&ldquo;${d.keterangan}&rdquo;</p>` : ''}
                        </div>
                    `;
                });
                konten.innerHTML = html;
            }
        }

        function updateDriverInfo(select) {
            const opt = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('infoDriverTerpilih');
            const proyekInput = document.getElementById('manual_proyek');

            if (!opt.value) {
                infoDiv.classList.add('hidden');
                return;
            }

            document.getElementById('infoNip').textContent = opt.dataset.nip || '-';
            document.getElementById('infoNik').textContent = opt.dataset.nik || '-';
            document.getElementById('infoProyek').textContent = opt.dataset.proyek || 'Operasional MTU';
            proyekInput.value = opt.dataset.proyek || 'Operasional MTU';
            infoDiv.classList.remove('hidden');
        }

        function bukaModalPengganti(jadwalId, namaDriver, tanggal, penggantiId) {
            const modal = document.getElementById('modalTentukanPengganti');
            const form = document.getElementById('formPengganti');
            document.getElementById('namaDriverLibur').textContent = namaDriver;
            document.getElementById('tglDriverLibur').textContent = tanggal;
            document.getElementById('selectDriverPengganti').value = penggantiId || '';

            form.action = `/admin/jadwal-driver/${jadwalId}/pengganti`;
            modal.showModal();
        }

        // Buka modal kalender jika ada query kalender=1
        document.addEventListener('DOMContentLoaded', function() {
            @if ($bukaKalender)
                bukaModalKalender();
            @endif
        });
    </script>
@endsection
