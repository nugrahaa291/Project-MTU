@extends('layouts.panel')
@section('judul', 'Jadwal Libur Driver')

@section('content')
    {{-- 1. JUDUL, SUBJUDUL & 4 TOMBOL AKSI UTAMA --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Jadwal Libur Driver</h2>
            <p class="text-sm text-gray-500">Kelola jadwal kerja dan libur driver PT Multi Talent Universal berdasarkan data kantor pusat maupun perubahan operasional cabang.</p>
        </div>

        {{-- EMPAT TOMBOL AKSI UTAMA --}}
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Tombol 1: + Tambah Libur Driver --}}
            <button type="button" onclick="document.getElementById('modalTambahManual').showModal()" class="btn btn-primary btn-sm whitespace-nowrap">
                + Tambah Libur Driver
            </button>

            {{-- Tombol 2: Import Jadwal Libur (Langsung Buka File Explorer & Eksekusi Import) --}}
            <form id="formImportLangsung" action="{{ route('admin.jadwal-driver.direct-import') }}" method="POST" enctype="multipart/form-data" class="hidden">
                @csrf
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="file" name="file_jadwal" id="inputImportLangsung" accept=".xlsx,.xls,.csv" onchange="submitImportLangsung(this)">
            </form>
            <button type="button"
                    id="btnImportJadwalLangsung"
                    onclick="document.getElementById('inputImportLangsung').click()"
                    class="btn btn-dark btn-sm whitespace-nowrap cursor-pointer"
                    title="Pilih file Excel (.xlsx, .xls) atau CSV (.csv) untuk langsung diimpor">
                &uarr; Import Jadwal Libur
            </button>

            {{-- Tombol 3: Template Excel --}}
            <a href="{{ route('admin.jadwal-driver.template') }}" class="btn btn-outline btn-sm whitespace-nowrap" title="Unduh Template Excel Standar (.xlsx)">
                &darr; Template Excel
            </a>

            {{-- Tombol 4: Kalender Dropdown (Membuka Kalender Compact Nimpa Area di Bawahnya) --}}
            <div class="relative inline-block text-left" id="kalenderDropdownContainer">
                <button type="button"
                        onclick="toggleKalenderDropdown(event)"
                        id="btnToggleKalender"
                        class="btn btn-outline btn-sm whitespace-nowrap {{ !empty($tanggalPilihan) ? 'border-brand text-brand bg-orange-50 font-semibold' : '' }}"
                        title="Buka kalender compact bulanan">
                    📅 Kalender
                </button>

                {{-- KALENDER COMPACT NIMPA TABEL (POPUP DATEPICKER ~310px) --}}
                @php
                    $currDate = \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1);
                    $prevDate = $currDate->subMonth();
                    $nextDate = $currDate->addMonth();
                @endphp
                <div id="sectionKalender"
                     class="hidden absolute right-0 sm:left-0 sm:right-auto mt-2 w-[310px] bg-white rounded-xl shadow-2xl border border-gray-200 p-3 z-30">
                    {{-- Header Navigasi Bulan (Nama Bulan & Panah Navigasi) --}}
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-100 px-1">
                        <span class="text-xs sm:text-sm font-bold text-gray-800">
                            {{ $currDate->translatedFormat('F Y') }}
                        </span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'tanggal', 'page']), ['tahun' => $prevDate->year, 'bulan' => $prevDate->month, 'kalender' => 1])) }}"
                               class="p-1 rounded hover:bg-gray-100 text-gray-600 font-bold leading-none text-xs"
                               title="Bulan Sebelumnya">
                                &larr;
                            </a>
                            <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'tanggal', 'page']), ['tahun' => $nextDate->year, 'bulan' => $nextDate->month, 'kalender' => 1])) }}"
                               class="p-1 rounded hover:bg-gray-100 text-gray-600 font-bold leading-none text-xs"
                               title="Bulan Berikutnya">
                                &rarr;
                            </a>
                            <button type="button" onclick="tutupKalenderDropdown()" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-700 text-xs ml-1" title="Tutup">
                                ✕
                            </button>
                        </div>
                    </div>

                    {{-- Header Nama Hari (Sen s/d Min) --}}
                    <div class="grid grid-cols-7 gap-1 text-center mb-1">
                        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $hariNama)
                            <div class="py-1 text-[11px] font-bold text-gray-500 uppercase {{ $hariNama === 'Sab' ? 'text-amber-600' : ($hariNama === 'Min' ? 'text-rose-600' : '') }}" title="{{ $hariNama }}">
                                {{ $hariNama }}
                            </div>
                        @endforeach
                    </div>

                    {{-- Grid Tanggal Compact: Kotak Kecil dengan Titik Merah --}}
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach ($kalender['daftar_hari'] as $hari)
                            @if (! $hari['apakah_bulan_ini'])
                                <div class="h-8 w-8 sm:h-8.5 sm:w-8.5 mx-auto"></div>
                            @else
                                @php
                                    $isToday = $hari['apakah_hari_ini'];
                                    $isSelected = ($tanggalPilihan === $hari['tanggal']);
                                    $hasOff = $hari['ada_driver_libur'];
                                    $tglStr = $hari['tanggal'];
                                @endphp
                                <button type="button"
                                        onclick="pilihTanggal('{{ $tglStr }}')"
                                        class="cell-tanggal h-8 w-8 sm:h-8.5 sm:w-8.5 mx-auto flex flex-col items-center justify-center rounded transition text-xs font-semibold relative
                                        {{ $isSelected
                                            ? 'bg-brand text-white shadow-xs font-bold'
                                            : ($isToday
                                                ? 'border border-blue-500 text-blue-700 bg-blue-50/70 font-bold hover:bg-orange-50'
                                                : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900') }}"
                                        data-tanggal="{{ $tglStr }}"
                                        title="Klik tanggal {{ $tglStr }} untuk filter tabel">
                                    <span class="leading-none">{{ $hari['nomor_hari'] }}</span>
                                    @if ($hasOff)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $isSelected ? 'bg-white' : 'bg-rose-500' }} mt-0.5"></span>
                                    @else
                                        <span class="w-1.5 h-1.5 mt-0.5 opacity-0"></span>
                                    @endif
                                </button>
                            @endif
                        @endforeach
                    </div>

                    {{-- Footer Datepicker (Clear, Legend Titik Merah, Today) --}}
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 mt-2 px-1 text-xs">
                        <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tanggal', 'page']), ['tahun' => $tahun, 'bulan' => $bulan])) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                            Clear
                        </a>
                        <span class="inline-flex items-center gap-1 text-[11px] text-gray-500">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Titik Merah: Libur
                        </span>
                        <button type="button" onclick="pilihTanggal('{{ \Carbon\CarbonImmutable::now(config('absensi.timezone', 'Asia/Jakarta'))->toDateString() }}')" class="text-blue-600 hover:text-blue-800 font-medium">
                            Today
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. PANEL FILTER PENCARIAN SEDERHANA (HANYA CARI & PROYEK) --}}
    <div class="card mb-6 p-4">
        <form method="GET" action="{{ route('admin.jadwal-driver.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tahun" value="{{ $tahun }}">
            <input type="hidden" name="bulan" value="{{ $bulan }}">
            @if (!empty($tanggalPilihan))
                <input type="hidden" name="tanggal" value="{{ $tanggalPilihan }}">
            @endif

            {{-- 1. Cari Driver (Nama / NIK MTU / NIP) --}}
            <div class="flex-1 min-w-[240px]">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Cari Driver (Nama / NIK MTU / NIP)</label>
                <input type="text" name="cari" value="{{ $filter['cari'] ?? '' }}" placeholder="Ketik nama, NIP, atau NIK driver..." class="input w-full text-xs">
            </div>

            {{-- 2. Filter Unit / Proyek --}}
            <div class="w-56 min-w-[180px]">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Unit / Proyek</label>
                <select name="proyek" class="input w-full text-xs">
                    <option value="">Semua Unit</option>
                    @foreach ($daftarProyek as $p)
                        <option value="{{ $p }}" {{ ($filter['proyek'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Tombol Cari / Filter & Reset --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-dark btn-sm">Cari / Filter</button>
                <a href="{{ route('admin.jadwal-driver.index', ['tahun' => $tahun, 'bulan' => $bulan]) }}" class="btn btn-outline btn-sm">Reset</a>
            </div>

            {{-- Keterangan Status Filter --}}
            <div class="w-full text-xs text-gray-500 pt-2 border-t border-gray-100 mt-1 flex flex-wrap items-center justify-between gap-2" id="statusFilterBar">
                <div id="statusFilterText">
                    @if (!empty($tanggalPilihan))
                        Menampilkan jadwal libur tanggal: <strong class="text-brand">{{ \Carbon\CarbonImmutable::parse($tanggalPilihan)->translatedFormat('l, d F Y') }}</strong>
                    @else
                        Menampilkan jadwal periode: <strong>{{ \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}</strong>
                    @endif
                    @if (!empty($filter['cari']) || !empty($filter['proyek']))
                        <span class="text-gray-400">&bull; Filter aktif:
                            @if (!empty($filter['cari'])) &ldquo;{{ $filter['cari'] }}&rdquo; @endif
                            @if (!empty($filter['proyek'])) [Proyek: {{ $filter['proyek'] }}] @endif
                        </span>
                    @endif
                </div>
                @if (!empty($tanggalPilihan) || !empty($filter['cari']) || !empty($filter['proyek']))
                    <a href="{{ route('admin.jadwal-driver.index', ['tahun' => $tahun, 'bulan' => $bulan]) }}" class="text-brand hover:underline font-medium">
                        &circlearrowleft; Tampilkan Semua Driver Bulan Ini
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- 3. TABEL UTAMA JADWAL LIBUR DRIVER (LANGSUNG DI BAWAH FILTER) --}}
    <div id="containerTabel">
        <div class="table-wrap">
            <table class="table-base">
                <thead>
                    <tr>
                        <th class="w-12 text-center whitespace-nowrap">No</th>
                        <th class="whitespace-nowrap">Nama Driver</th>
                        <th class="whitespace-nowrap">NIP</th>
                        <th class="whitespace-nowrap">NIK MTU</th>
                        <th class="whitespace-nowrap">Unit Armada</th>
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
                                    @if (!empty($tanggalPilihan))
                                        <p class="text-base font-semibold text-gray-800">Tidak ada driver libur pada tanggal ini.</p>
                                        <p class="text-xs text-gray-400 mt-1">Seluruh armada beroperasi normal pada {{ \Carbon\CarbonImmutable::parse($tanggalPilihan)->translatedFormat('d F Y') }}.</p>
                                        <div class="mt-4 flex items-center justify-center gap-2">
                                            <a href="{{ route('admin.jadwal-driver.index', ['tahun' => $tahun, 'bulan' => $bulan]) }}" class="btn btn-outline btn-sm">Lihat Seluruh Bulan Ini</a>
                                            <button type="button" onclick="document.getElementById('modalTambahManual').showModal()" class="btn btn-primary btn-sm">+ Tambah Libur</button>
                                        </div>
                                    @else
                                        <p class="text-base font-semibold text-gray-800">Tidak ada jadwal driver ditemukan</p>
                                        <p class="text-xs text-gray-400 mt-1">Belum ada data jadwal yang sesuai dengan filter atau periode yang Anda pilih.</p>
                                        <div class="mt-4 flex items-center justify-center gap-2">
                                            <a href="{{ route('admin.jadwal-driver.index', ['tahun' => $tahun, 'bulan' => $bulan]) }}" class="btn btn-outline btn-sm">Reset Filter</a>
                                            <button type="button" onclick="document.getElementById('modalTambahManual').showModal()" class="btn btn-primary btn-sm">+ Tambah Libur</button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION DAN JUMLAH DATA --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <p class="text-xs text-gray-500">
                Menampilkan <strong class="text-gray-900">{{ $daftarJadwalTabel->firstItem() ?? 0 }}</strong> sampai <strong class="text-gray-900">{{ $daftarJadwalTabel->lastItem() ?? 0 }}</strong> dari <strong class="text-gray-900">{{ $daftarJadwalTabel->total() }}</strong> jadwal driver.
            </p>
            <div>
                {{ $daftarJadwalTabel->links() }}
            </div>
        </div>
    </div>

    {{-- MODAL TAMBAH LIBUR DRIVER MANUAL (CABANG) --}}
    <dialog id="modalTambahManual" class="modal rounded-xl p-0 backdrop:bg-black/60 shadow-2xl max-w-lg w-full">
        <form method="POST" action="{{ route('admin.jadwal-driver.manual') }}" class="p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">+ Tambah Libur / Izin Dadakan Driver</h3>
                    <p class="text-xs text-gray-500">Jatah libur default {{ $defaultJatahLibur }} hari/bulan. Izin dadakan dapat menukar hari libur tetap yang sudah ditentukan.</p>
                </div>
                <button type="button" onclick="document.getElementById('modalTambahManual').close()" class="btn btn-outline btn-sm">✕</button>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Pilih Driver <span class="text-rose-500">*</span></label>
                <select name="karyawan_id" id="manual_karyawan_id" required class="input w-full text-xs" onchange="updateDriverInfo(this)">
                    <option value="">-- Pilih Driver --</option>
                    @foreach ($semuaDriver as $d)
                        <option value="{{ $d->id }}" data-nip="{{ $d->nip }}" data-nik="{{ $d->nik }}" data-proyek="{{ $d->proyek }}" data-unit="{{ $d->jabatan?->nama ?? $d->proyek }}">
                            {{ $d->nama }} ({{ $d->nip ?: $d->nik }}) &bull; Unit {{ $d->jabatan?->nama ?? $d->proyek }}
                        </option>
                    @endforeach
                </select>
                <div id="infoDriverTerpilih" class="mt-2 text-[11px] text-gray-600 hidden">
                    <p>NIP: <span id="infoNip" class="font-mono font-semibold">-</span> &bull; NIK: <span id="infoNik" class="font-mono font-semibold">-</span> &bull; Unit: <span id="infoProyek" class="font-semibold">-</span></p>
                    <div id="badgeJatahLibur" class="mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        Jatah Libur: - / {{ $defaultJatahLibur }} hari
                    </div>
                </div>
            </div>

            {{-- OPSI TUKAR HARI LIBUR TETAP (ALUR REVISI IZIN DADAKAN & JATAH 7 HARI) --}}
            <div id="containerTukarLibur" class="p-3 bg-amber-50/80 rounded-lg border border-amber-200 hidden">
                <div class="flex items-start gap-2 mb-2">
                    <span class="text-amber-600 font-bold text-base leading-none">🔄</span>
                    <div>
                        <label class="block text-xs font-bold text-amber-900">
                            Hari Libur Tetap yang Diganti (Tukar Menjadi Hari Kerja)
                            <span id="tukarRequiredStar" class="text-rose-500 hidden">*</span>
                        </label>
                        <p id="tukarKeterangan" class="text-[11px] text-amber-800 leading-tight mt-0.5">
                            Pilih salah satu tanggal libur tetap driver yang sudah ditentukan untuk diganti menjadi hari kerja agar jatah libur bulanan tetap sesuai kuota ({{ $defaultJatahLibur }} hari).
                        </p>
                    </div>
                </div>
                <select name="tanggal_tukar" id="selectTanggalTukar" class="input w-full text-xs bg-white">
                    <option value="">-- Tanpa Tukar (Jatah Masih Tersedia) --</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Izin / Libur Dadakan <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal_mulai" required value="{{ date('Y-m-d') }}" class="input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Selesai (Opsional)</label>
                    <input type="date" name="tanggal_selesai" class="input w-full text-xs" placeholder="Kosongkan jika 1 hari">
                    <p class="text-[10px] text-gray-400 mt-0.5">Isi jika izin rentang beberapa hari.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Unit Armada / Proyek Penugasan</label>
                <input type="text" name="proyek" id="manual_proyek" placeholder="Contoh: Wingbox, Trailer, atau Logistik" class="input w-full text-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Driver Pengganti (Jika Diperlukan)</label>
                <select name="driver_pengganti_id" class="input w-full text-xs">
                    <option value="">-- Tanpa Driver Pengganti --</option>
                    @foreach ($semuaDriver as $d)
                        <option value="{{ $d->id }}">{{ $d->nama }} ({{ $d->nip ?: $d->nik }}) &bull; Unit {{ $d->jabatan?->nama ?? $d->proyek }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-0.5">Wajib dipilih bila jumlah driver aktif unit di bawah batas minimum.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan / Keterangan Izin Dadakan</label>
                <textarea name="keterangan" rows="2" placeholder="Keperluan keluarga mendadak, urusan darurat, izin sakit, dll." class="input w-full text-xs"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('modalTambahManual').close()" class="btn btn-outline btn-sm">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Izin &amp; Tukar Libur</button>
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
        function toggleKalenderDropdown(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('sectionKalender');
            if (!dropdown) return;
            dropdown.classList.toggle('hidden');
        }

        function tutupKalenderDropdown() {
            const dropdown = document.getElementById('sectionKalender');
            if (dropdown) dropdown.classList.add('hidden');
        }

        // Klik di luar dropdown untuk menutup kalender
        document.addEventListener('click', function(event) {
            const container = document.getElementById('kalenderDropdownContainer');
            const dropdown = document.getElementById('sectionKalender');
            if (container && dropdown && !container.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Klik Tanggal di Kalender langsung memfilter tabel di bawah
        function pilihTanggal(tgl) {
            const url = new URL(window.location.href);
            if (url.searchParams.get('tanggal') === tgl) {
                url.searchParams.delete('tanggal');
            } else {
                url.searchParams.set('tanggal', tgl);
                const parts = tgl.split('-');
                if (parts.length === 3) {
                    url.searchParams.set('tahun', parts[0]);
                    url.searchParams.set('bulan', parseInt(parts[1], 10));
                }
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        const dataLiburDriver = @json($jadwalLiburPerDriver);
        const defaultJatahLibur = {{ $defaultJatahLibur }};

        function updateDriverInfo(select) {
            const opt = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('infoDriverTerpilih');
            const proyekInput = document.getElementById('manual_proyek');
            const containerTukar = document.getElementById('containerTukarLibur');
            const selectTukar = document.getElementById('selectTanggalTukar');
            const badgeJatah = document.getElementById('badgeJatahLibur');
            const starRequired = document.getElementById('tukarRequiredStar');
            const keteranganTukar = document.getElementById('tukarKeterangan');

            if (!opt.value) {
                if (infoDiv) infoDiv.classList.add('hidden');
                if (containerTukar) containerTukar.classList.add('hidden');
                return;
            }

            const driverId = opt.value;
            const nip = opt.dataset.nip || '-';
            const nik = opt.dataset.nik || '-';
            const unit = opt.dataset.unit || opt.dataset.proyek || 'Operasional MTU';

            document.getElementById('infoNip').textContent = nip;
            document.getElementById('infoNik').textContent = nik;
            document.getElementById('infoProyek').textContent = unit;
            if (proyekInput) proyekInput.value = opt.dataset.proyek || unit;
            if (infoDiv) infoDiv.classList.remove('hidden');

            // Cek daftar libur terjadwal driver di bulan aktif
            const daftarLibur = dataLiburDriver[driverId] || [];
            const totalLibur = daftarLibur.length;

            if (badgeJatah) {
                badgeJatah.textContent = `Jatah Libur Bulan Ini: ${totalLibur} / ${defaultJatahLibur} hari`;
                if (totalLibur >= defaultJatahLibur) {
                    badgeJatah.className = 'mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200';
                } else {
                    badgeJatah.className = 'mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200';
                }
            }

            if (containerTukar && selectTukar) {
                selectTukar.innerHTML = '';

                if (totalLibur >= defaultJatahLibur) {
                    // Wajib tukar karena kuota 7 hari sudah penuh
                    if (starRequired) starRequired.classList.remove('hidden');
                    selectTukar.required = true;
                    if (keteranganTukar) {
                        keteranganTukar.innerHTML = `<span class="font-bold text-rose-700">Jatah libur driver sudah penuh (${totalLibur}/${defaultJatahLibur} hari).</span> Wajib memilih 1 hari libur tetap untuk ditukar menjadi hari kerja agar kuota tetap ${defaultJatahLibur} hari.`;
                    }
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = '-- Wajib Pilih Hari Libur Tetap yang Diganti --';
                    selectTukar.appendChild(defaultOpt);
                } else {
                    // Opsional tukar bila kuota belum penuh
                    if (starRequired) starRequired.classList.add('hidden');
                    selectTukar.required = false;
                    if (keteranganTukar) {
                        keteranganTukar.innerHTML = `Jatah libur terpakai: ${totalLibur}/${defaultJatahLibur} hari. Admin bebas menentukan hari libur tetap mana yang ingin ditukar menjadi hari kerja, atau dibiarkan tanpa tukar.`;
                    }
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = `-- Tanpa Tukar (Jatah Masih Tersedia: ${totalLibur}/${defaultJatahLibur}) --`;
                    selectTukar.appendChild(defaultOpt);
                }

                daftarLibur.forEach(item => {
                    const optItem = document.createElement('option');
                    optItem.value = item.tanggal;
                    optItem.textContent = `${item.tanggal_formatted} (Libur Tetap)`;
                    selectTukar.appendChild(optItem);
                });

                containerTukar.classList.remove('hidden');
            }
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

        // Buka dropdown kalender otomatis jika ada query kalender=1 di URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('kalender') === '1') {
                const dropdown = document.getElementById('sectionKalender');
                if (dropdown) dropdown.classList.remove('hidden');
            }
        });

        // Submit form otomatis saat file dipilih di File Explorer
        function submitImportLangsung(input) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            if (file.size > 10 * 1024 * 1024) {
                alert('Ukuran berkas maksimal 10MB.');
                input.value = '';
                return;
            }
            const btn = document.getElementById('btnImportJadwalLangsung');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                btn.innerHTML = `
                    <svg class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Mengimpor...
                `;
            }
            document.getElementById('formImportLangsung').submit();
        }
    </script>
@endsection
