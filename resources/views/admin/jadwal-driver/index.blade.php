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
                    <th class="w-12 text-center">No</th>
                    <th>Nama Driver</th>
                    <th>NIP</th>
                    <th>NIK MTU</th>
                    <th>Proyek</th>
                    <th>Tanggal Libur</th>
                    <th class="text-center">Status</th>
                    <th>Driver Pengganti</th>
                    <th>Sumber Jadwal</th>
                    <th>Keterangan</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftarJadwalTabel as $idx => $j)
                    <tr class="hover:bg-gray-50/60 transition">
                        <td class="text-center text-xs text-gray-400 font-mono">
                            {{ $daftarJadwalTabel->firstItem() + $idx }}
                        </td>
                        <td>
                            <p class="font-semibold text-gray-900">{{ $j->karyawan?->nama }}</p>
                            <p class="text-[11px] text-gray-400">{{ $j->karyawan?->jabatan?->nama ?? 'Driver' }}</p>
                        </td>
                        <td>
                            <span class="font-mono text-xs text-gray-700 font-medium">{{ $j->karyawan?->nip ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="font-mono text-xs text-gray-600">{{ $j->karyawan?->nik ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                {{ $j->proyek ?? 'Operasional MTU' }}
                            </span>
                        </td>
                        <td class="font-medium text-gray-900 text-xs whitespace-nowrap">
                            {{ $j->tanggal->translatedFormat('d M Y') }}
                        </td>
                        <td class="text-center whitespace-nowrap">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $j->status_jadwal === 'libur' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                {{ ucfirst($j->status_jadwal) }}
                            </span>
                        </td>
                        <td>
                            @if ($j->driverPengganti)
                                <p class="text-xs font-semibold text-gray-900">{{ $j->driverPengganti->nama }}</p>
                                <p class="font-mono text-[10px] text-gray-400">NIP: {{ $j->driverPengganti->nip ?? $j->driverPengganti->nik }}</p>
                            @else
                                <span class="text-xs text-gray-400 italic">Tidak ada pengganti</span>
                            @endif
                        </td>
                        <td>
                            <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ $j->sumber === 'import_pusat' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                                {{ $j->sumber === 'import_pusat' ? 'Import Pusat' : 'Manual Cabang' }}
                            </span>
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

    {{-- MODAL BESAR: KALENDER BULANAN KONVENSIONAL --}}
    <dialog id="modalKalender" class="modal rounded-2xl p-0 backdrop:bg-black/60 shadow-2xl max-w-4xl w-full mx-auto my-auto overflow-hidden">
        <div class="flex flex-col max-h-[90vh]">
            {{-- Header Modal & Navigasi Bulan --}}
            @php
                $currDate = \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1);
                $prevDate = $currDate->subMonth();
                $nextDate = $currDate->addMonth();
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-white p-5">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <span>📅 Kalender Libur Driver</span>
                    </h3>
                    <p class="text-xs text-gray-500">Kalender bulanan 7 kolom konvensional (Senin - Minggu). Titik merah menandakan driver libur.</p>
                </div>

                {{-- Navigasi Bulan & Tahun --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'kalender']), ['tahun' => $prevDate->year, 'bulan' => $prevDate->month, 'kalender' => 1])) }}" class="btn btn-outline btn-sm px-2.5" title="Bulan Sebelumnya">
                        &larr;
                    </a>
                    <span class="text-sm font-bold text-gray-800 min-w-36 text-center px-2 py-1 bg-gray-50 rounded border border-gray-200">
                        {{ $currDate->translatedFormat('F Y') }}
                    </span>
                    <a href="{{ route('admin.jadwal-driver.index', array_merge(request()->except(['tahun', 'bulan', 'kalender']), ['tahun' => $nextDate->year, 'bulan' => $nextDate->month, 'kalender' => 1])) }}" class="btn btn-outline btn-sm px-2.5" title="Bulan Berikutnya">
                        &rarr;
                    </a>
                    <button type="button" onclick="document.getElementById('modalKalender').close()" class="btn btn-outline btn-sm ml-2">✕ Tutup</button>
                </div>
            </div>

            {{-- Legend & Info --}}
            <div class="bg-gray-50/80 px-5 py-2.5 border-b border-gray-100 flex flex-wrap items-center justify-between text-xs text-gray-600 gap-2">
                <div class="flex items-center gap-4">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span> <strong>Titik Merah:</strong> Ada driver libur
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3.5 h-3.5 rounded border-2 border-brand bg-brand/10"></span> Hari Ini
                    </span>
                </div>
                <div class="text-xs text-gray-500">
                    Total driver libur bulan ini: <strong class="text-gray-900">{{ $kalender['total_driver_libur_bulan_ini'] }} hari kerja</strong>
                </div>
            </div>

            {{-- Body Modal: Grid Kalender 7 Kolom Horizontal --}}
            <div class="p-5 overflow-y-auto space-y-4">
                <div class="w-full border border-gray-200 rounded-lg overflow-hidden bg-white shadow-xs">
                    {{-- Nama Hari Horizontal (Urutan: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu, Minggu) --}}
                    <div class="grid grid-cols-7 bg-gray-100 text-center text-xs font-bold text-gray-700 border-b border-gray-200">
                        <div class="py-2.5 px-1 truncate">Senin</div>
                        <div class="py-2.5 px-1 truncate">Selasa</div>
                        <div class="py-2.5 px-1 truncate">Rabu</div>
                        <div class="py-2.5 px-1 truncate">Kamis</div>
                        <div class="py-2.5 px-1 truncate">Jumat</div>
                        <div class="py-2.5 px-1 truncate text-amber-600">Sabtu</div>
                        <div class="py-2.5 px-1 truncate text-rose-600">Minggu</div>
                    </div>

                    {{-- Grid Tanggal Bulanan (7 Kolom x 5/6 Baris) --}}
                    <div class="grid grid-cols-7 divide-x divide-y divide-gray-200 bg-gray-50">
                        @foreach ($kalender['daftar_hari'] as $hari)
                            @if (! $hari['apakah_bulan_ini'])
                                <div class="min-h-[72px] sm:min-h-[85px] bg-gray-100/50 p-2"></div>
                            @else
                                @php
                                    $isToday = $hari['apakah_hari_ini'];
                                    $hasOff = $hari['ada_driver_libur'];
                                    $offCount = $hari['jumlah_driver_libur'];
                                    $tglStr = $hari['tanggal'];
                                @endphp
                                <div onclick="pilihTanggalKalender('{{ $tglStr }}', {{ json_encode($hari['daftar_driver_libur']) }})"
                                     class="cell-tanggal min-h-[72px] sm:min-h-[85px] p-2 transition cursor-pointer hover:bg-orange-50/60 relative flex flex-col justify-between {{ $isToday ? 'bg-orange-50/40 ring-2 ring-inset ring-brand' : 'bg-white' }}"
                                     data-tanggal="{{ $tglStr }}"
                                     title="Klik untuk melihat rincian driver libur tanggal {{ $tglStr }}">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold {{ $isToday ? 'rounded-full bg-brand text-white w-5 h-5 flex items-center justify-center text-[11px]' : 'text-gray-800' }}">
                                            {{ $hari['nomor_hari'] }}
                                        </span>
                                        @if ($isToday)
                                            <span class="text-[9px] uppercase font-bold text-brand tracking-tighter hidden sm:inline">Hari Ini</span>
                                        @endif
                                    </div>

                                    {{-- Indikator Titik Merah & Ringkasan Driver Libur --}}
                                    @if ($hasOff)
                                        <div class="mt-1">
                                            <div class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 border border-rose-200 shadow-xs">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                <span>{{ $offCount }} Libur</span>
                                            </div>
                                            <p class="text-[10px] text-gray-500 mt-0.5 truncate hidden sm:block">
                                                {{ $hari['daftar_driver_libur'][0]['nama'] }}
                                                @if ($offCount > 1) +{{ $offCount - 1 }} lainnya @endif
                                            </p>
                                        </div>
                                    @else
                                        <div class="mt-1 text-[10px] text-gray-300 hidden sm:block">Normal</div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Panel Detail Tanggal yang Diklik (Di Bawah Kalender, Terintegrasi & Scrollable) --}}
                <div id="panelDetailTanggalKalender" class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 transition">
                    <div class="flex flex-wrap items-center justify-between mb-3 border-b border-gray-200 pb-2 gap-2">
                        <div>
                            <h4 class="text-sm font-bold text-gray-900" id="kalenderDetailJudul">Rincian Driver Libur</h4>
                            <p class="text-xs text-gray-500" id="kalenderDetailSubjudul">Klik kotak tanggal pada kalender untuk melihat rincian driver yang libur.</p>
                        </div>
                        <span id="kalenderDetailBadge" class="hidden rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 border border-rose-200"></span>
                    </div>
                    <div id="kalenderDetailKonten" class="max-h-56 overflow-y-auto space-y-2">
                        <p class="text-xs text-gray-400 italic text-center py-4">Silakan klik salah satu tanggal bertitik merah di atas untuk melihat detail lengkap driver yang libur.</p>
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
            }
        }

        function pilihTanggalKalender(tanggal, daftarDriver) {
            // Highlight cell terpilih
            document.querySelectorAll('.cell-tanggal').forEach(c => {
                c.classList.remove('ring-2', 'ring-indigo-500');
            });
            const cell = document.querySelector(`.cell-tanggal[data-tanggal="${tanggal}"]`);
            if (cell) {
                cell.classList.add('ring-2', 'ring-indigo-500');
            }

            const judul = document.getElementById('kalenderDetailJudul');
            const subjudul = document.getElementById('kalenderDetailSubjudul');
            const badge = document.getElementById('kalenderDetailBadge');
            const konten = document.getElementById('kalenderDetailKonten');

            judul.textContent = `Daftar Driver Libur — ${tanggal}`;
            subjudul.textContent = `Tanggal terpilih pada kalender operasional cabang.`;

            if (!daftarDriver || daftarDriver.length === 0) {
                badge.classList.add('hidden');
                konten.innerHTML = `<div class="p-4 text-center text-xs text-gray-500 bg-white rounded-lg border border-gray-100">Tidak ada driver yang dijadwalkan libur pada tanggal ${tanggal}. Semua armada beroperasi normal.</div>`;
            } else {
                badge.textContent = `${daftarDriver.length} Driver Libur`;
                badge.classList.remove('hidden');

                let html = '';
                daftarDriver.forEach((d, idx) => {
                    const badgeVerif = d.status_verifikasi === 'disetujui'
                        ? '<span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200">Disetujui</span>'
                        : '<span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200">Perlu Verifikasi</span>';

                    html += `
                        <div class="border border-gray-200 rounded-lg p-3 bg-white space-y-1.5 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900 text-xs">${idx + 1}. ${d.nama}</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 border border-rose-200">Status: ${d.status_jadwal}</span>
                                    ${badgeVerif}
                                </div>
                            </div>
                            <div class="text-[11px] text-gray-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span>NIP: <strong class="font-mono text-gray-800">${d.nip}</strong></span>
                                <span>NIK MTU: <strong class="font-mono text-gray-800">${d.nik}</strong></span>
                                <span>Proyek: <strong class="text-brand">${d.proyek}</strong></span>
                            </div>
                            <div class="text-[11px] text-gray-500 flex flex-wrap gap-x-4 pt-0.5">
                                <span>Driver Pengganti: <strong class="text-gray-800">${d.pengganti || '<em class="text-gray-400 font-normal">Tidak ada</em>'}</strong></span>
                                <span>Sumber Jadwal: <strong class="text-gray-800">${d.sumber}</strong></span>
                            </div>
                            ${d.keterangan ? `<p class="text-[11px] text-gray-600 italic bg-gray-50 p-1.5 rounded border border-gray-100">&ldquo;${d.keterangan}&rdquo;</p>` : ''}
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

        // Buka modal kalender otomatis jika diarahkan dari query kalender=1 atau view=kalender
        document.addEventListener('DOMContentLoaded', function() {
            @if ($bukaKalender)
                bukaModalKalender();
            @endif
        });
    </script>
@endsection
