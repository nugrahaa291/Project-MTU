<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusAbsensi;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\JadwalDriver;
use App\Models\Karyawan;
use App\Services\AbsensiService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsensiController extends Controller
{
    public function index(Request $request, AbsensiService $service): View
    {
        $hariIni = $service->sekarang()->toDateString();
        $dari = $request->input('dari', $hariIni);
        $sampai = $request->input('sampai', $hariIni);

        $absensi = Absensi::with('karyawan.jabatan')
            ->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)
            ->when($request->filled('karyawan_id'), fn ($q) => $q->where('karyawan_id', $request->karyawan_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('tanggal')
            ->orderBy('jam_masuk')
            ->paginate(25)
            ->withQueryString();

        return view('hrd.absensi.index', [
            'absensi' => $absensi,
            'karyawan' => Karyawan::aktif()->orderBy('nama')->get(['id', 'nama']),
            'dari' => $dari,
            'sampai' => $sampai,
            'hariIni' => $hariIni,
        ]);
    }

    /** Rekap absensi fleksibel berdasarkan rentang tanggal & filter driver. */
    public function rekap(Request $request, AbsensiService $service): View|StreamedResponse
    {
        $sekarang = $service->sekarang();
        $hariIni = $sekarang->toDateString();

        // 1. Tangani Quick Range filter
        $quick = $request->input('quick');
        if ($quick === 'hari_ini') {
            $dari = $hariIni;
            $sampai = $hariIni;
        } elseif ($quick === '7_hari') {
            $dari = $sekarang->subDays(6)->toDateString();
            $sampai = $hariIni;
        } elseif ($quick === 'bulan_ini') {
            $dari = $sekarang->startOfMonth()->toDateString();
            $sampai = $sekarang->endOfMonth()->toDateString();
        } elseif ($quick === 'bulan_lalu') {
            $lalu = $sekarang->subMonth();
            $dari = $lalu->startOfMonth()->toDateString();
            $sampai = $lalu->endOfMonth()->toDateString();
        } else {
            // Cek backward compatibility dengan parameter 'bulan' (YYYY-MM)
            if ($request->filled('bulan') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('bulan')) && ! $request->filled('dari')) {
                $bulanCarbon = CarbonImmutable::createFromFormat('Y-m-d', $request->input('bulan').'-01');
                $dari = $bulanCarbon->startOfMonth()->toDateString();
                $sampai = $bulanCarbon->endOfMonth()->toDateString();
            } else {
                $dari = $request->input('dari', $request->input('tanggal_mulai', $sekarang->startOfMonth()->toDateString()));
                $sampai = $request->input('sampai', $request->input('tanggal_akhir', $hariIni));
            }
        }

        // Pastikan format tanggal valid
        try {
            $cDari = CarbonImmutable::parse($dari);
            $cSampai = CarbonImmutable::parse($sampai);
        } catch (\Throwable) {
            $cDari = $sekarang->startOfMonth();
            $cSampai = $sekarang->endOfMonth();
            $dari = $cDari->toDateString();
            $sampai = $cSampai->toDateString();
        }

        if ($cSampai->lt($cDari)) {
            $cSampai = $cDari;
            $sampai = $dari;
        }

        // 2. Query absensi dalam rentang tanggal
        $hitung = Absensi::selectRaw('karyawan_id, status, count(*) as jumlah, sum(menit_terlambat) as menit')
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->groupBy('karyawan_id', 'status')
            ->get()
            ->groupBy('karyawan_id');

        // Query libur terjadwal driver dari tabel jadwal_driver
        $liburTerjadwalMap = JadwalDriver::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->where('status_jadwal', 'libur')
            ->selectRaw('karyawan_id, count(*) as jumlah')
            ->groupBy('karyawan_id')
            ->pluck('jumlah', 'karyawan_id');

        // Jadwal kerja eksplisit driver
        $jadwalKerjaMap = JadwalDriver::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->where('status_jadwal', 'kerja')
            ->selectRaw('karyawan_id, count(*) as jumlah')
            ->groupBy('karyawan_id')
            ->pluck('jumlah', 'karyawan_id');

        // Hitung total hari kerja kalender standar dalam rentang tanggal
        $hariKalenderStandar = 0;
        foreach (CarbonPeriod::create($cDari, $cSampai) as $d) {
            if ($service->hariKerja($d)) {
                $hariKalenderStandar++;
            }
        }

        // 3. Query Karyawan dengan search Nama/NIK/NIP dan filter Proyek
        $karyawanQuery = Karyawan::with('jabatan')->orderBy('nama');

        if ($request->filled('cari')) {
            $cari = trim((string) $request->input('cari'));
            $karyawanQuery->where(function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nip', 'like', "%{$cari}%")
                    ->orWhere('nik', 'like', "%{$cari}%");
            });
        }

        if ($request->filled('proyek')) {
            $karyawanQuery->where('proyek', $request->input('proyek'));
        }

        $semuaKaryawan = $karyawanQuery->get();

        // Ambil daftar proyek unik untuk opsi dropdown filter
        $daftarProyek = Karyawan::whereNotNull('proyek')
            ->distinct()
            ->pluck('proyek')
            ->filter()
            ->values()
            ->all();
        if (! in_array('Operasional MTU', $daftarProyek, true)) {
            $daftarProyek[] = 'Operasional MTU';
        }

        $statusFilter = $request->input('status');

        $baris = $semuaKaryawan->map(function (Karyawan $k) use ($hitung, $liburTerjadwalMap, $jadwalKerjaMap, $hariKalenderStandar) {
            $data = $hitung->get($k->id, collect())->keyBy(fn ($r) => $r->status->value);

            $hadir = (int) ($data->get('hadir')->jumlah ?? 0);
            $terlambat = (int) ($data->get('terlambat')->jumlah ?? 0);
            $izin = (int) ($data->get('izin')->jumlah ?? 0);
            $sakit = (int) ($data->get('sakit')->jumlah ?? 0);
            $cuti = (int) ($data->get('cuti')->jumlah ?? 0);
            $alpa = (int) ($data->get('alpa')->jumlah ?? 0);
            $menitTerlambat = (int) ($data->get('terlambat')->menit ?? 0);
            $liburTerjadwal = (int) ($liburTerjadwalMap->get($k->id) ?? 0);

            // Jika karyawan memiliki jadwal kerja eksplisit dari import matriks pusat
            $jadwalKerjaEksplisit = $jadwalKerjaMap->get($k->id);
            $hariKerjaTerjadwal = $jadwalKerjaEksplisit !== null
                ? (int) $jadwalKerjaEksplisit
                : max(0, $hariKalenderStandar - $liburTerjadwal);

            return [
                'karyawan' => $k,
                'proyek' => $k->proyek ?? 'Operasional MTU',
                'hari_kerja_terjadwal' => $hariKerjaTerjadwal,
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'izin' => $izin,
                'sakit' => $sakit,
                'cuti' => $cuti,
                'libur_terjadwal' => $liburTerjadwal,
                'alpa' => $alpa,
                'menit_terlambat' => $menitTerlambat,
                'jumlah' => collect(StatusAbsensi::cases())->mapWithKeys(
                    fn ($s) => [$s->value => (int) ($data->get($s->value)->jumlah ?? 0)]
                ),
            ];
        })->filter(function ($b) use ($statusFilter) {
            if (! empty($statusFilter)) {
                return ($b[$statusFilter] ?? 0) > 0;
            }
            return $b['karyawan']->aktif || ($b['hadir'] + $b['terlambat'] + $b['izin'] + $b['sakit'] + $b['cuti'] + $b['alpa'] + $b['libur_terjadwal']) > 0;
        })->values();

        // 4. Fitur Ekspor Excel / CSV
        $exportFormat = strtolower((string) $request->input('export'));
        if (in_array($exportFormat, ['excel', 'csv', 'xlsx'], true)) {
            return $this->eksporRekap($baris, $dari, $sampai, $exportFormat === 'csv' ? 'csv' : 'xlsx');
        }

        $bulan = $cDari->format('Y-m');
        $awal = $cDari;

        return view('hrd.absensi.rekap', compact(
            'baris',
            'dari',
            'sampai',
            'quick',
            'daftarProyek',
            'bulan',
            'awal',
            'hariIni'
        ));
    }

    /**
     * Menghasilkan file Excel (.xlsx) atau CSV dari hasil rekap absensi.
     */
    private function eksporRekap(Collection $baris, string $dari, string $sampai, string $format): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Absensi MTU');

        // Header Dokumen
        $sheet->setCellValue('A1', 'REKAPITULASI ABSENSI KARYAWAN & DRIVER');
        $sheet->setCellValue('A2', 'PT Multi Talent Universal (MTU)');
        $sheet->setCellValue('A3', "Periode: {$dari} s/d {$sampai}");
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);

        // Header Kolom Tabel
        $headers = [
            'No', 'Nama Karyawan', 'NIP', 'NIK', 'Proyek',
            'Hari Kerja Terjadwal', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Cuti',
            'Libur Terjadwal', 'Alpa', 'Total Menit Telat',
        ];
        $sheet->fromArray($headers, null, 'A5');

        $sheet->getStyle('A5:N5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowIdx = 6;
        foreach ($baris as $idx => $b) {
            $sheet->fromArray([
                $idx + 1,
                $b['karyawan']->nama,
                $b['karyawan']->nip ?? '-',
                $b['karyawan']->nik ?? '-',
                $b['proyek'],
                $b['hari_kerja_terjadwal'],
                $b['hadir'],
                $b['terlambat'],
                $b['izin'],
                $b['sakit'],
                $b['cuti'],
                $b['libur_terjadwal'],
                $b['alpa'],
                $b['menit_terlambat'],
            ], null, 'A' . $rowIdx);
            $rowIdx++;
        }

        // Auto width kolom
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $namaFile = "Rekap_Absensi_MTU_{$dari}_sd_{$sampai}." . ($format === 'csv' ? 'csv' : 'xlsx');

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            if ($format === 'csv') {
                $writer = new Csv($spreadsheet);
                $writer->setDelimiter(';');
            } else {
                $writer = new Xlsx($spreadsheet);
            }
            $writer->save('php://output');
        }, $namaFile, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Admin mencatat izin/sakit/cuti/alpa secara manual. */
    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengelola input izin/libur absensi.');
        }

        $data = $request->validate([
            'karyawan_id' => ['required', Rule::exists('karyawan', 'id')->where('aktif', true)],
            'tanggal' => ['required', 'date'],
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, StatusAbsensi::manual()))],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        if ($data['tanggal'] < $karyawan->tanggal_masuk->toDateString()) {
            return back()->withInput()->with('error', 'Tanggal tidak boleh sebelum tanggal masuk karyawan.');
        }

        $ada = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $data['tanggal'])->first();
        if ($ada?->jam_masuk) {
            return back()->withInput()->with('error', 'Karyawan sudah absen masuk pada tanggal itu; catatan tidak bisa ditimpa.');
        }

        // Boleh menimpa catatan ALPA otomatis (misal surat sakit baru diserahkan)
        Absensi::updateOrCreate(
            ['karyawan_id' => $karyawan->id, 'tanggal' => $data['tanggal']],
            [
                'status' => $data['status'],
                'keterangan' => $data['keterangan'] ?? null,
                'menit_terlambat' => 0,
                'dicatat_oleh' => $request->user()->id,
            ],
        );

        return back()->with('success', 'Catatan absensi disimpan.');
    }

    /** Foto selfie hanya bisa dilihat HRD yang login (disimpan di disk privat). */
    public function foto(Absensi $absensi, string $jenis): StreamedResponse
    {
        abort_unless(in_array($jenis, ['masuk', 'pulang'], true), 404);

        $path = $absensi->{'foto_'.$jenis};
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
