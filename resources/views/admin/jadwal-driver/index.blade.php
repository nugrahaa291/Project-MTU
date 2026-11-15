@extends('layouts.panel')
@section('judul', 'Jadwal Libur Driver')

@section('content')
    {{-- 1. JUDUL & SUBJUDUL & TOMBOL AKSI UTAMA --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Jadwal Libur Driver</h2>
            <p class="text-sm text-gray-500">Kelola jadwal kerja dan libur driver PT Multi Talent Universal berdasarkan data kantor pusat maupun perubahan operasional cabang.</p>
        </div>

        {{-- 4 TOMBOL AKSI UTAMA --}}
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Tombol 1: + Tambah Libur Driver --}}
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

            {{-- Tombol 4: Kalender (Toggle Tampilkan / Sembunyikan Kalender) --}}
            <button type="button" onclick="toggleKalender()" id="btnToggleKalender" class="btn btn-outline btn-sm whitespace-nowrap" title="Tampilkan atau sembunyikan kalender">
                📅 Kalender
            </button>
        </div>
    </div>

    {{-- 2. BAGIAN BERIKUTNYA: KALENDER COMPACT DI SEBELAH KIRI & KETERANGAN RINGKAS DI KANAN --}}
    <div id="sectionKalender" class="mb-6 flex flex-wrap lg:flex-nowrap items-start gap-4">
        {{-- KARTU KALENDER COMPACT (UKURAN ~300-320px SEPERTI DATE PICKER BAWAAN PADA REFERENSI USER) --}}
        @php
            $currDate = \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1);
            $prevDate = $currDate->subMonth();
            $nextDate = $currDate->addMonth();
        @endphp
        <div class="card p-3 w-full sm:w-[310px] shrink-0 bg-white shadow-xs border border-gray-200">
            {{-- Header Navigasi Bulan Mirip Datepicker (Nama Bulan & Tombol Panah) --}}
            <div class="flex items-center justify-between pb-2 mb-2 border-b border-gray-100 px-1">
                <span class="text-xs sm:text-sm font-bold text-gray-800">
                    {{ $currDate->translatedFormat('F Y') }}
                </span>
                <div class="flex items-center gap-1">
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'tanggal', 'page']), ['tahun' => $prevDate->year, 'bulan' => $prevDate->month])) }}" class="p-1 rounded hover:bg-gray-100 text-gray-600 font-bold leading-none text-xs" title="Bulan Sebelumnya">
                        &larr;
                    </a>
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'tanggal', 'page']), ['tahun' => $nextDate->year, 'bulan' => $nextDate->month])) }}" class="p-1 rounded hover:bg-gray-100 text-gray-600 font-bold leading-none text-xs" title="Bulan Berikutnya">
                        &rarr;
                    </a>
                </div>
            </div>

            {{-- Grid 7 Kolom Hari (Sen, Sel, Rab, Kam, Jum, Sab, Min) --}}
            <div class="grid grid-cols-7 gap-1 text-center mb-1">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $hariNama)
                    <div class="py-1 text-[11px] font-bold text-gray-500 uppercase {{ $hariNama === 'Sab' ? 'text-amber-600' : ($hariNama === 'Min' ? 'text-rose-600' : '') }}" title="{{ $hariNama }}">
                        {{ $hariNama }}
                    </div>
                @endforeach
            </div>

            {{-- Grid Tanggal Compact: Kotak Kecil Proporsional dengan Angka dan Titik Merah Kecil --}}
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
                                title="Klik tanggal {{ $tglStr }} untuk filter">
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

            {{-- Footer Datepicker Sesuai Gambar Referensi (Clear di Kiri, Today di Kanan) --}}
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

        {{-- KARTU KETERANGAN RINGKAS DI SEBELAH KANAN KALENDER --}}
        <div class="card p-4 flex-1 min-w-[280px] bg-white border border-gray-200 self-stretch flex flex-col justify-between shadow-xs">
            <div>
                <div class="flex items-center justify-between border-b border-gray-100 pb-2 mb-3">
                    <h4 class="text-sm font-bold text-gray-900">
                        @if (!empty($tanggalPilihan))
                            Jadwal Driver &mdash; {{ \Carbon\CarbonImmutable::parse($tanggalPilihan)->translatedFormat('l, d F Y') }}
                        @else
                            Informasi Jadwal Driver MTU &mdash; {{ $currDate->translatedFormat('F Y') }}
                        @endif
                    </h4>
                    @if (!empty($tanggalPilihan))
                        <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-[11px] font-semibold text-brand">
                            Tanggal Dipilih
                        </span>
                    @endif
                </div>

                @if (!empty($tanggalPilihan))
                    <p class="text-xs text-gray-600 mb-2">
                        Tabel di bawah saat ini hanya menampilkan driver yang dijadwalkan libur pada tanggal <strong>{{ \Carbon\CarbonImmutable::parse($tanggalPilihan)->translatedFormat('d F Y') }}</strong>.
                    </p>
                    <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 space-y-1.5 border border-gray-100">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Jumlah driver libur pada tanggal ini:</span>
                            <strong class="text-gray-900 font-bold">{{ $daftarJadwalTabel->total() }} Driver</strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Status Operasional Armada:</span>
                            <span class="font-semibold {{ $daftarJadwalTabel->total() > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                {{ $daftarJadwalTabel->total() > 0 ? 'Ada Libur Terjadwal' : 'Beroperasi Penuh / Normal' }}
                            </span>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-600 mb-3">
                        Klik salah satu tanggal pada kalender compact di sebelah kiri untuk langsung memfilter daftar driver libur pada tanggal tersebut.
                    </p>
                    <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 space-y-1.5 border border-gray-100">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Total hari dengan driver libur bulan ini:</span>
                            <strong class="text-gray-900 font-bold">{{ $kalender['total_driver_libur_bulan_ini'] }} hari</strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Jadwal libur driver tercatat:</span>
                            <strong class="text-gray-900 font-bold">{{ $daftarJadwalTabel->total() }} jadwal</strong>
                        </div>
                    </div>
                @endif
            </div>

            @if (!empty($tanggalPilihan))
                <div class="pt-3 border-t border-gray-100 mt-3 flex items-center justify-between">
                    <span class="text-[11px] text-gray-400">Klik tanggal lagi di kalender atau tombol Clear untuk reset.</span>
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tanggal', 'page']), ['tahun' => $tahun, 'bulan' => $bulan])) }}" class="btn btn-outline btn-sm text-xs text-rose-600 border-rose-200 hover:bg-rose-50">
                        ✕ Hapus Filter Tanggal
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- 3. DI BAWAHNYA: PANEL PENCARIAN & FILTER SEDERHANA (HANYA CARI & PROYEK) --}}
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

            {{-- 2. Filter Proyek --}}
            <div class="w-56 min-w-[180px]">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Proyek</label>
                <select name="proyek" class="input w-full text-xs">
                    <option value="">Semua Proyek</option>
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
            <div class="w-full text-xs text-gray-500 pt-2 border-t border-gray-100 mt-1 flex flex-wrap items-center justify-between gap-2">
                <div>
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

    {{-- 4. TERAKHIR: TABEL UTAMA JADWAL LIBUR DRIVER --}}
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
        // Toggle Show/Hide Kalender di Halaman yang Sama
        function toggleKalender() {
            const el = document.getElementById('sectionKalender');
            if (!el) return;
            el.classList.toggle('hidden');
        }

        // Klik Tanggal di Kalender langsung memfilter tabel di bawah
        function pilihTanggal(tgl) {
            const url = new URL(window.location.href);
            if (url.searchParams.get('tanggal') === tgl) {
                // Klik tanggal yang sama membatalkan filter tanggal (kembali ke bulan penuh)
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
    </script>
@endsection
