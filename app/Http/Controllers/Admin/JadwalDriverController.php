<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\JadwalDriver;
use App\Models\JadwalImportBatch;
use App\Models\JadwalRiwayatPerubahan;
use App\Models\Karyawan;
use App\Models\ProyekKebutuhanDriver;
use App\Services\JadwalDriverService;
use App\Services\JadwalImportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JadwalDriverController extends Controller
{
    /**
     * Halaman utama Jadwal Libur Driver: Langsung menampilkan tabel jadwal dengan filter lengkap.
     */
    public function index(Request $request, JadwalDriverService $service): View
    {
        $sekarang = CarbonImmutable::now(config('absensi.timezone', 'Asia/Jakarta'));
        $tanggalPilihan = $request->input('tanggal');

        if (! empty($tanggalPilihan) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPilihan)) {
            $carbonTgl = CarbonImmutable::parse($tanggalPilihan);
            $tahun = (int) $request->input('tahun', $carbonTgl->year);
            $bulan = (int) $request->input('bulan', $carbonTgl->month);
        } else {
            $tanggalPilihan = null;
            $tahun = (int) $request->input('tahun', $sekarang->year);
            $bulan = (int) $request->input('bulan', $sekarang->month);
        }

        $tanggalMulai = $request->input('tanggal_mulai', $request->input('start_date'));
        $tanggalAkhir = $request->input('tanggal_akhir', $request->input('end_date'));

        $filter = [
            'cari' => trim((string) $request->input('cari', $request->input('q', ''))),
            'proyek' => $request->input('proyek'),
            'tanggal' => $tanggalPilihan,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_akhir' => $tanggalAkhir,
        ];

        // 1. Data Kalender Bulanan (untuk kalender inline)
        $kalender = $service->ambilKalenderBulanan($tahun, $bulan, [
            'cari' => $filter['cari'],
            'proyek' => $filter['proyek'],
        ]);

        // 2. Data Tabel Jadwal Driver
        $tabelQuery = JadwalDriver::with(['karyawan.jabatan', 'driverPengganti']);

        if (! empty($tanggalPilihan)) {
            // Ketika tanggal diklik pada kalender, langsung filter jadwal tanggal tersebut
            $tabelQuery->whereDate('tanggal', $tanggalPilihan);
        } elseif (! empty($tanggalMulai) && ! empty($tanggalAkhir)) {
            $tabelQuery->whereDate('tanggal', '>=', $tanggalMulai)
                ->whereDate('tanggal', '<=', $tanggalAkhir);
        } elseif (! empty($tanggalMulai)) {
            $tabelQuery->whereDate('tanggal', '>=', $tanggalMulai);
        } elseif (! empty($tanggalAkhir)) {
            $tabelQuery->whereDate('tanggal', '<=', $tanggalAkhir);
        } else {
            // Default filter sesuai bulan dan tahun kalender aktif
            $tabelQuery->whereYear('tanggal', $tahun)
                ->whereMonth('tanggal', $bulan);
        }

        if (! empty($filter['cari'])) {
            $cari = $filter['cari'];
            $tabelQuery->whereHas('karyawan', function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nip', 'like', "%{$cari}%")
                    ->orWhere('nik', 'like', "%{$cari}%");
            });
        }

        if (! empty($filter['proyek'])) {
            $tabelQuery->where('proyek', $filter['proyek']);
        }

        if ($request->filled('status')) {
            $tabelQuery->where('status_jadwal', $request->input('status'));
        }

        $daftarJadwalTabel = $tabelQuery->orderBy('tanggal')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        // Ambil daftar proyek unik dan seluruh driver aktif untuk form manual
        $daftarProyek = Karyawan::whereNotNull('proyek')->distinct()->pluck('proyek')->filter()->values()->all();
        if (! in_array('Operasional MTU', $daftarProyek, true)) {
            $daftarProyek[] = 'Operasional MTU';
        }

        $semuaDriver = Karyawan::aktif()->orderBy('nama')->get(['id', 'nama', 'nip', 'nik', 'proyek']);
        $batchTerbaru = JadwalImportBatch::with('uploader')->latest()->limit(5)->get();

        return view('admin.jadwal-driver.index', compact(
            'kalender',
            'daftarJadwalTabel',
            'tahun',
            'bulan',
            'filter',
            'tanggalPilihan',
            'daftarProyek',
            'semuaDriver',
            'batchTerbaru'
        ));
    }

    /**
     * Mengunduh template file Excel standar Format 1 (Matriks) atau Format 2 (Tabel Standar).
     */
    public function exportTemplate(Request $request, JadwalDriverService $service): StreamedResponse
    {
        $format = $request->input('format', 'matriks') === 'tabel_standar' ? 'tabel_standar' : 'matriks';
        $tahun = (int) $request->input('tahun', date('Y'));
        $bulan = (int) $request->input('bulan', date('n'));

        $spreadsheet = $service->buatTemplateSpreadsheet($format, $tahun, $bulan);
        $namaFile = $format === 'matriks'
            ? "Template_Jadwal_Driver_Matriks_MTU_{$tahun}_{$bulan}.xlsx"
            : "Template_Jadwal_Driver_Standar_MTU_{$tahun}_{$bulan}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $namaFile, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Menampilkan form unggah file import jadwal driver.
     */
    public function importForm(): View
    {
        $batchList = JadwalImportBatch::with('uploader')->latest()->limit(10)->get();

        return view('admin.jadwal-driver.import', compact('batchList'));
    }

    /**
     * Memproses file upload dan menampilkan preview sebelum eksekusi import.
     */
    public function previewImport(Request $request, JadwalImportService $service): View|RedirectResponse
    {
        $request->validate([
            'file_jadwal' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'], // maks 10MB
            'tahun' => ['nullable', 'integer', 'min:2020', 'max:2035'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
        ], [
            'file_jadwal.required' => 'Pilih file jadwal driver (.xlsx, .xls, .csv) terlebih dahulu.',
            'file_jadwal.mimes' => 'Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).',
            'file_jadwal.max' => 'Ukuran file tidak boleh melebihi 10MB.',
        ]);

        $file = $request->file('file_jadwal');
        $tahunDefault = (int) $request->input('tahun', date('Y'));
        $bulanDefault = (int) $request->input('bulan', date('n'));

        try {
            $barisData = $service->bacaFile($file);
            $preview = $service->validasiDanSiapkanPreview($barisData, $tahunDefault, $bulanDefault);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal membaca berkas Excel/CSV: ' . $e->getMessage());
        }

        // Simpan data preview ke Cache selama 60 menit untuk langkah konfirmasi
        $token = 'import_' . md5(uniqid((string) mt_rand(), true));
        Cache::put($token, [
            'nama_file' => $file->getClientOriginalName(),
            'preview' => $preview,
        ], 3600);

        return view('admin.jadwal-driver.preview', [
            'token' => $token,
            'namaFile' => $file->getClientOriginalName(),
            'preview' => $preview,
            'tahunDefault' => $tahunDefault,
            'bulanDefault' => $bulanDefault,
        ]);
    }

    /**
     * Menyimpan hasil import setelah disetujui Admin pada halaman preview.
     */
    public function storeImport(Request $request, JadwalImportService $service): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'konfirmasi' => ['required', 'in:1'],
            'timpa_jadwal_lama' => ['nullable', 'boolean'],
        ], [
            'konfirmasi.required' => 'Anda harus mencentang konfirmasi persetujuan sebelum menyimpan jadwal.',
        ]);

        $token = $request->input('token');
        $cached = Cache::get($token);

        if (! $cached) {
            return redirect()->route('admin.jadwal-driver.import')
                ->with('error', 'Sesi preview import telah kedaluwarsa. Silakan unggah kembali berkas Anda.');
        }

        $preview = $cached['preview'];
        $namaFile = $cached['nama_file'];
        $timpa = (bool) $request->input('timpa_jadwal_lama', true);

        if (empty($preview['jadwal_valid'])) {
            return back()->with('error', 'Tidak ada data jadwal valid yang dapat disimpan.');
        }

        try {
            $batch = $service->eksekusiImport(
                $preview['jadwal_valid'],
                $namaFile,
                $preview['format'],
                $request->user()->id,
                $timpa
            );

            Cache::forget($token);

            ActivityLog::catat(
                $request->user(),
                'import_jadwal_driver',
                "Mengimpor jadwal driver batch {$batch->nomor_batch} ({$batch->total_baris} baris, {$batch->total_driver} driver)"
            );

            return redirect()->route('admin.jadwal-driver.index', [
                'tahun' => $batch->periode_tahun,
                'bulan' => (int) substr($batch->periode_bulan, 5, 2),
            ])->with('success', "Import berhasil! Batch {$batch->nomor_batch} tersimpan ({$batch->total_libur} jadwal libur, {$batch->total_kerja} jadwal kerja).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses import ke basis data: ' . $e->getMessage());
        }
    }

    /**
     * Mengunduh berkas laporan baris tidak valid / gagal dari sesi preview.
     */
    public function downloadInvalidReport(Request $request): StreamedResponse|RedirectResponse
    {
        $token = $request->input('token');
        $cached = Cache::get($token);

        if (! $cached || empty($cached['preview']['jadwal_bermasalah'])) {
            return back()->with('error', 'Tidak ada data baris bermasalah untuk diunduh.');
        }

        $bermasalah = $cached['preview']['jadwal_bermasalah'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Baris Bermasalah');

        $headers = ['Baris File', 'Nama Driver', 'NIK', 'NIP', 'Proyek', 'Tanggal', 'Penyebab Masalah'];
        $sheet->fromArray($headers, null, 'A1');

        $rowIdx = 2;
        foreach ($bermasalah as $b) {
            $sheet->fromArray([
                $b['baris'] ?? '-',
                $b['nama'] ?? '-',
                $b['nik'] ?? '-',
                $b['nip'] ?? '-',
                $b['proyek'] ?? '-',
                $b['tanggal'] ?? '-',
                $b['alasan'] ?? 'Data tidak valid',
            ], null, 'A' . $rowIdx);
            $rowIdx++;
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(';');
            $writer->save('php://output');
        }, 'Laporan_Baris_Gagal_Import_MTU.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Menyimpan jadwal libur manual untuk kebutuhan mendadak dari cabang.
     */
    public function storeManual(Request $request, JadwalDriverService $service): RedirectResponse
    {
        $data = $request->validate([
            'karyawan_id' => ['required', 'exists:karyawan,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'proyek' => ['nullable', 'string', 'max:50'],
            'driver_pengganti_id' => ['nullable', 'exists:karyawan,id', 'different:karyawan_id'],
            'alasan_pengecualian' => ['nullable', 'string', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'driver_pengganti_id.different' => 'Driver tidak bisa menjadi pengganti untuk dirinya sendiri.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ]);

        try {
            $hasil = $service->tambahLiburManual($data, $request->user());

            ActivityLog::catat(
                $request->user(),
                'tambah_libur_manual',
                "Menambahkan libur driver mendadak (ID Karyawan: {$data['karyawan_id']}, {$hasil['total_hari']} hari)"
            );

            $pesan = "Jadwal libur driver berhasil ditambahkan ({$hasil['total_hari']} hari).";
            if (! empty($hasil['peringatan'])) {
                $pesan .= ' Catatan: ' . implode(' ', $hasil['peringatan']);
            }

            return back()->with('success', $pesan);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Menentukan atau mengubah driver pengganti pada jadwal libur tertentu.
     */
    public function tentukanPengganti(Request $request, JadwalDriver $jadwal, JadwalDriverService $service): RedirectResponse
    {
        $request->validate([
            'driver_pengganti_id' => ['nullable', 'exists:karyawan,id', 'different:' . $jadwal->karyawan_id],
            'alasan' => ['required', 'string', 'max:300'],
        ], [
            'driver_pengganti_id.different' => 'Driver pengganti tidak boleh sama dengan driver yang libur.',
            'alasan.required' => 'Wajib mengisi alasan penugasan / perubahan driver pengganti.',
        ]);

        try {
            $service->tentukanPengganti(
                $jadwal,
                $request->filled('driver_pengganti_id') ? (int) $request->input('driver_pengganti_id') : null,
                $request->input('alasan'),
                $request->user()
            );

            ActivityLog::catat(
                $request->user(),
                'tentukan_driver_pengganti',
                "Menetapkan pengganti untuk jadwal libur driver ID {$jadwal->karyawan_id} pada {$jadwal->tanggal->toDateString()}"
            );

            return back()->with('success', 'Driver pengganti berhasil diperbarui dan diverifikasi.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Menghapus jadwal libur tertentu (mengembalikan ke hari kerja).
     */
    public function destroy(Request $request, JadwalDriver $jadwal): RedirectResponse
    {
        $tanggal = $jadwal->tanggal->toDateString();
        $namaDriver = $jadwal->karyawan?->nama ?? 'Driver #'.$jadwal->karyawan_id;

        // Catat audit sebelum hapus
        JadwalRiwayatPerubahan::create([
            'jadwal_id' => $jadwal->id,
            'karyawan_id' => $jadwal->karyawan_id,
            'tanggal' => $tanggal,
            'status_sebelumnya' => $jadwal->status_jadwal,
            'status_baru' => 'dihapus_menjadi_kerja',
            'driver_pengganti_sebelumnya' => $jadwal->driver_pengganti_id,
            'driver_pengganti_baru' => null,
            'diubah_oleh' => $request->user()->id,
            'alasan' => 'Dibatalkan oleh Admin melalui dashboard',
        ]);

        $jadwal->delete();

        ActivityLog::catat(
            $request->user(),
            'hapus_jadwal_driver',
            "Membatalkan jadwal libur {$namaDriver} pada {$tanggal}"
        );

        return back()->with('success', "Jadwal libur {$namaDriver} pada {$tanggal} berhasil dibatalkan.");
    }
}
