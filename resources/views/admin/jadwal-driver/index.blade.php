@extends('layouts.panel')
@section('judul', 'Jadwal Libur Driver')

@section('content')
    {{-- HEADER HALAMAN & AKSI UTAMA --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Jadwal Libur Driver (Operasional MTU)</h2>
            <p class="text-sm text-gray-500">Kelola jadwal kerja dan libur driver cabang berdasarkan import kantor pusat maupun libur mendadak.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="document.getElementById('modalTambahManual').showModal()" class="btn btn-primary btn-sm">
                + Tambah Libur Driver
            </button>
            <a href="{{ route('admin.jadwal-driver.import') }}" class="btn btn-dark btn-sm">
                &uarr; Import Jadwal Libur
            </a>
            <div class="relative inline-block text-left" id="dropdownTemplate">
                <button type="button" onclick="toggleDropdownTemplate()" class="btn btn-outline btn-sm">
                    &darr; Template Excel &or;
                </button>
                <div id="menuTemplate" class="hidden absolute right-0 mt-2 w-48 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 z-20">
                    <div class="py-1">
                        <a href="{{ route('admin.jadwal-driver.template', ['format' => 'matriks', 'tahun' => $tahun, 'bulan' => $bulan]) }}" class="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">
                            Format 1 — Matriks Pusat (.xlsx)
                        </a>
                        <a href="{{ route('admin.jadwal-driver.template', ['format' => 'tabel_standar', 'tahun' => $tahun, 'bulan' => $bulan]) }}" class="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">
                            Format 2 — Tabel Standar (.xlsx)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KARTU NAVIGASI PERIODE & FILTER --}}
    <div class="card mb-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4">
            {{-- TOGGLE TAMPILAN: KALENDER | TABEL | MATRIKS --}}
            <div class="inline-flex rounded-md shadow-sm" role="group">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'kalender']) }}"
                   class="rounded-l-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold {{ $viewMode === 'kalender' ? 'bg-brand text-white border-brand' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    📅 Kalender
                </a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'tabel']) }}"
                   class="border-t border-b border-gray-300 px-3 py-1.5 text-xs font-semibold {{ $viewMode === 'tabel' ? 'bg-brand text-white border-brand' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    📋 Tabel
                </a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'matriks']) }}"
                   class="rounded-r-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold {{ $viewMode === 'matriks' ? 'bg-brand text-white border-brand' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    📊 Matriks
                </a>
            </div>

            {{-- NAVIGASI BULAN & TAHUN DENGAN PREV / NEXT --}}
            @php
                $currDate = \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1);
                $prevDate = $currDate->subMonth();
                $nextDate = $currDate->addMonth();
            @endphp
            <div class="flex items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['tahun' => $prevDate->year, 'bulan' => $prevDate->month]) }}" class="btn btn-outline btn-sm px-2.5" title="Bulan Sebelumnya">
                    &larr;
                </a>
                <span class="text-sm font-bold text-gray-800 min-w-32 text-center">
                    {{ $currDate->translatedFormat('F Y') }}
                </span>
                <a href="{{ request()->fullUrlWithQuery(['tahun' => $nextDate->year, 'bulan' => $nextDate->month]) }}" class="btn btn-outline btn-sm px-2.5" title="Bulan Berikutnya">
                    &rarr;
                </a>
            </div>
        </div>

        {{-- FORM PENCARIAN & FILTER --}}
        <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <input type="hidden" name="tahun" value="{{ $tahun }}">
            <input type="hidden" name="bulan" value="{{ $bulan }}">

            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Cari Driver (Nama / NIK MTU / NIP)</label>
                <input type="text" name="cari" value="{{ $filter['cari'] }}" placeholder="Ketik nama, NIP, atau NIK driver..." class="input w-full text-xs">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Filter Proyek</label>
                <select name="proyek" class="input w-full text-xs">
                    <option value="">Semua Proyek</option>
                    @foreach ($daftarProyek as $p)
                        <option value="{{ $p }}" {{ $filter['proyek'] === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-dark btn-sm flex-1">Filter</button>
                <a href="{{ route('admin.jadwal-driver.index', ['view' => $viewMode, 'tahun' => $tahun, 'bulan' => $bulan]) }}" class="btn btn-outline btn-sm">Reset</a>
            </div>
        </form>
    </div>

    {{-- KONTEN UTAMA BERDASARKAN VIEW MODE --}}
    @if ($viewMode === 'kalender')
        {{-- VIEW KALENDER BULANAN INTERAKTIF --}}
        <div class="card overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50/50 p-4 flex flex-wrap items-center justify-between gap-2">
                <div class="text-xs text-gray-600">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-500 mr-1"></span> <strong>Titik Merah:</strong> Menandakan ada driver yang dijadwalkan libur pada tanggal tersebut.
                </div>
                <div class="text-xs text-gray-500">
                    Total driver libur bulan ini: <strong class="text-gray-900">{{ $kalender['total_driver_libur_bulan_ini'] }} hari</strong>
                </div>
            </div>

            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-100 text-center text-xs font-bold text-gray-700 uppercase">
                <div class="py-2.5">Sen</div>
                <div class="py-2.5">Sel</div>
                <div class="py-2.5">Rab</div>
                <div class="py-2.5">Kam</div>
                <div class="py-2.5">Jum</div>
                <div class="py-2.5 text-amber-600">Sab</div>
                <div class="py-2.5 text-rose-600">Min</div>
            </div>

            <div class="grid grid-cols-7 divide-x divide-y divide-gray-200">
                @foreach ($kalender['daftar_hari'] as $hari)
                    @if (! $hari['apakah_bulan_ini'])
                        <div class="min-h-24 bg-gray-50/60 p-2 text-gray-300"></div>
                    @else
                        @php
                            $isToday = $hari['apakah_hari_ini'];
                            $hasOff = $hari['ada_driver_libur'];
                            $offCount = $hari['jumlah_driver_libur'];
                            $tglStr = $hari['tanggal'];
                        @endphp
                        <div onclick="bukaModalTanggal('{{ $tglStr }}', {{ json_encode($hari['daftar_driver_libur']) }})"
                             class="min-h-24 p-2 transition cursor-pointer hover:bg-blue-50/50 relative flex flex-col justify-between {{ $isToday ? 'bg-blue-50/40 ring-2 ring-inset ring-brand' : 'bg-white' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold {{ $isToday ? 'rounded-full bg-brand text-white w-6 h-6 flex items-center justify-center' : 'text-gray-800' }}">
                                    {{ $hari['nomor_hari'] }}
                                </span>
                                @if ($isToday)
                                    <span class="text-[9px] uppercase font-bold text-brand tracking-tighter">Hari Ini</span>
                                @endif
                            </div>

                            {{-- INDIKATOR TITIK MERAH KECIL DENGAN SUMMARY LIBUR --}}
                            @if ($hasOff)
                                <div class="mt-2">
                                    <div class="inline-flex items-center gap-1 rounded bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 border border-rose-200 shadow-xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                        <span>{{ $offCount }} Libur</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500 mt-1 truncate">
                                        {{ $hari['daftar_driver_libur'][0]['nama'] }}
                                        @if ($offCount > 1) +{{ $offCount - 1 }} lainnya @endif
                                    </p>
                                </div>
                            @else
                                <div class="mt-2 text-[10px] text-gray-300">Normal</div>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

    @elseif ($viewMode === 'tabel')
        {{-- VIEW TABEL JADWAL DRIVER --}}
        <div class="table-wrap">
            <table class="table-base">
                <thead>
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Nama Driver</th>
                        <th>NIP & NIK MTU</th>
                        <th>Proyek</th>
                        <th>Tanggal Libur</th>
                        <th class="text-center">Status</th>
                        <th>Driver Pengganti</th>
                        <th>Sumber</th>
                        <th>Verifikasi</th>
                        <th>Keterangan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftarJadwalTabel as $idx => $j)
                        <tr>
                            <td class="text-center text-xs text-gray-400">{{ $daftarJadwalTabel->firstItem() + $idx }}</td>
                            <td>
                                <p class="font-medium text-gray-900">{{ $j->karyawan?->nama }}</p>
                                <p class="text-xs text-gray-400">{{ $j->karyawan?->jabatan?->nama ?? 'Driver' }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-xs text-gray-700">{{ $j->karyawan?->nip ?? '-' }}</p>
                                <p class="font-mono text-[11px] text-gray-400">{{ $j->karyawan?->nik }}</p>
                            </td>
                            <td>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                    {{ $j->proyek ?? 'Operasional MTU' }}
                                </span>
                            </td>
                            <td class="font-medium text-gray-900 text-xs">
                                {{ $j->tanggal->translatedFormat('d M Y') }}
                            </td>
                            <td class="text-center">
                                <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $j->status_jadwal === 'libur' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ ucfirst($j->status_jadwal) }}
                                </span>
                            </td>
                            <td>
                                @if ($j->driverPengganti)
                                    <p class="text-xs font-medium text-gray-900">{{ $j->driverPengganti->nama }}</p>
                                    <p class="font-mono text-[10px] text-gray-400">{{ $j->driverPengganti->nip ?? $j->driverPengganti->nik }}</p>
                                @else
                                    <span class="text-xs text-gray-400 italic">Tidak ada pengganti</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-xs {{ $j->sumber === 'import_pusat' ? 'text-blue-700' : 'text-purple-700' }}">
                                    {{ $j->sumber === 'import_pusat' ? 'Import Pusat' : 'Manual Cabang' }}
                                </span>
                            </td>
                            <td>
                                @if ($j->status_verifikasi === 'disetujui')
                                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Disetujui</span>
                                @elseif ($j->status_verifikasi === 'konflik')
                                    <span class="rounded bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Konflik</span>
                                @else
                                    <span class="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Perlu Verifikasi</span>
                                @endif
                            </td>
                            <td class="text-xs text-gray-600 max-w-xs truncate">{{ $j->keterangan ?? '-' }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick="bukaModalPengganti({{ $j->id }}, '{{ addslashes($j->karyawan?->nama ?? '') }}', '{{ $j->tanggal->format('Y-m-d') }}', '{{ $j->driver_pengganti_id }}')" class="btn btn-outline btn-sm text-[11px] py-1">
                                        Pengganti
                                    </button>
                                    <form method="POST" action="{{ route('admin.jadwal-driver.destroy', $j) }}" onsubmit="return confirm('Batalkan jadwal libur untuk {{ addslashes($j->karyawan?->nama ?? '') }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm text-[11px] py-1">Batal</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-gray-500">
                                Tidak ada data jadwal driver untuk periode dan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $daftarJadwalTabel->links() }}
        </div>

    @elseif ($viewMode === 'matriks')
        {{-- VIEW MATRIKS SEPERTI FILE KANTOR PUSAT --}}
        @php $daysInMonth = \Carbon\CarbonImmutable::createFromDate($tahun, $bulan, 1)->daysInMonth; @endphp
        <div class="table-wrap">
            <table class="table-base text-xs">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-white z-10">Driver</th>
                        <th class="sticky left-36 bg-white z-10">Proyek</th>
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            <th class="text-center px-2 py-1.5 font-mono text-[11px]">{{ sprintf('%02d', $d) }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matriksData as $row)
                        <tr>
                            <td class="sticky left-0 bg-white z-10 font-medium">
                                <p class="text-gray-900">{{ $row['karyawan']->nama }}</p>
                                <p class="font-mono text-[10px] text-gray-400">{{ $row['karyawan']->nip ?? $row['karyawan']->nik }}</p>
                            </td>
                            <td class="sticky left-36 bg-white z-10">
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-700 font-semibold">{{ $row['proyek'] }}</span>
                            </td>
                            @for ($d = 1; $d <= $daysInMonth; $d++)
                                @php $code = $row['harian'][$d] ?? '-'; @endphp
                                <td class="text-center font-mono font-bold px-1.5 py-1">
                                    @if ($code === 'L')
                                        <span class="rounded bg-rose-500 text-white px-1 py-0.5 text-[10px]" title="Libur">L</span>
                                    @elseif ($code === '1')
                                        <span class="text-emerald-700" title="Kerja">1</span>
                                    @else
                                        <span class="text-gray-300">-</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $daysInMonth + 2 }}" class="py-8 text-center text-gray-500">
                                Belum ada data jadwal matriks driver untuk periode ini. Silakan unggah jadwal melalui menu Import.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- MODAL DETAIL TANGGAL KALENDER --}}
    <dialog id="modalDetailTanggal" class="modal rounded-lg p-0 backdrop:bg-black/50 shadow-2xl max-w-2xl w-full">
        <div class="p-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900" id="modalTanggalJudul">Detail Driver Libur</h3>
                    <p class="text-xs text-gray-500" id="modalTanggalSubjudul">Tanggal terpilih</p>
                </div>
                <button type="button" onclick="document.getElementById('modalDetailTanggal').close()" class="btn btn-outline btn-sm">✕ Tutup</button>
            </div>

            <div id="modalTanggalKonten" class="space-y-3 max-h-96 overflow-y-auto">
                {{-- Diisi secara dinamis oleh JavaScript --}}
            </div>
        </div>
    </dialog>

    {{-- MODAL TAMBAH LIBUR MANUAL DARI CABANG --}}
    <dialog id="modalTambahManual" class="modal rounded-lg p-0 backdrop:bg-black/50 shadow-2xl max-w-lg w-full">
        <form method="POST" action="{{ route('admin.jadwal-driver.manual') }}" class="p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-lg font-bold text-gray-900">+ Tambah Libur Driver (Mendadak)</h3>
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
                    <p class="text-[10px] text-gray-400 mt-0.5">Isi bila libur rentang beberapa hari.</p>
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

    {{-- MODAL TENTUKAN PENGGANTI --}}
    <dialog id="modalTentukanPengganti" class="modal rounded-lg p-0 backdrop:bg-black/50 shadow-2xl max-w-md w-full">
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

    {{-- SCRIPT INTERAKTIF JADWAL DRIVER --}}
    <script>
        function toggleDropdownTemplate() {
            const menu = document.getElementById('menuTemplate');
            menu.classList.toggle('hidden');
        }

        window.onclick = function(event) {
            if (!event.target.closest('#dropdownTemplate')) {
                const menu = document.getElementById('menuTemplate');
                if (menu && !menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            }
        };

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

        function bukaModalTanggal(tanggal, daftarDriver) {
            const modal = document.getElementById('modalDetailTanggal');
            const judul = document.getElementById('modalTanggalJudul');
            const subjudul = document.getElementById('modalTanggalSubjudul');
            const konten = document.getElementById('modalTanggalKonten');

            judul.textContent = `Daftar Driver Libur — ${tanggal}`;
            subjudul.textContent = `Total: ${daftarDriver.length} driver dijadwalkan libur pada tanggal ini`;

            if (daftarDriver.length === 0) {
                konten.innerHTML = `<div class="p-6 text-center text-sm text-gray-500">Tidak ada driver yang dijadwalkan libur pada tanggal ini. Semua operasional normal.</div>`;
            } else {
                let html = '';
                daftarDriver.forEach((d, idx) => {
                    const badgeVerif = d.status_verifikasi === 'disetujui'
                        ? '<span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Disetujui</span>'
                        : '<span class="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Perlu Verifikasi</span>';

                    html += `
                        <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900 text-sm">${idx + 1}. ${d.nama}</span>
                                ${badgeVerif}
                            </div>
                            <div class="text-xs text-gray-600 flex flex-wrap gap-x-4 gap-y-1 font-mono">
                                <span>NIP: ${d.nip}</span>
                                <span>NIK: ${d.nik}</span>
                                <span class="font-sans font-semibold text-indigo-700">Proyek: ${d.proyek}</span>
                            </div>
                            <div class="text-xs text-gray-500 flex flex-wrap gap-x-4 pt-1">
                                <span>Sumber: <strong>${d.sumber}</strong></span>
                                <span>Pengganti: <strong>${d.pengganti || '<em class="text-gray-400">Tidak ada</em>'}</strong></span>
                            </div>
                            ${d.keterangan ? `<p class="text-xs text-gray-600 italic pt-1">&ldquo;${d.keterangan}&rdquo;</p>` : ''}
                        </div>
                    `;
                });
                konten.innerHTML = html;
            }

            modal.showModal();
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
