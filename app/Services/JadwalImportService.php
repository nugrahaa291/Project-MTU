<?php

namespace App\Services;

use App\Models\JadwalDriver;
use App\Models\JadwalImportBatch;
use App\Models\Karyawan;
use App\Models\ProyekKebutuhanDriver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class JadwalImportService
{
    /**
     * Membaca file Excel (.xlsx, .xls) atau .csv menjadi array baris.
     * Menggunakan readDataOnly = true untuk mencegah eksekusi formula/macro.
     */
    public function bacaFile(string|UploadedFile $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        return $worksheet->toArray(null, true, false, false);
    }

    /**
     * Menganalisis dan membedah struktur file: Matriks Bulanan vs Tabel Standar.
     */
    public function deteksiFormat(array $barisData): string
    {
        if (empty($barisData)) {
            return 'tidak_dikenal';
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $barisData[0] ?? []);

        // Cek kolom tabel standar
        $adaTanggal = in_array('tanggal', $header, true);
        $adaStatus = in_array('status', $header, true) || in_array('status jadwal', $header, true) || in_array('status_jadwal', $header, true);

        if ($adaTanggal && $adaStatus) {
            return 'tabel_standar';
        }

        // Cek apakah ada kolom tanggal berurutan (01-Sep, 02-Sep atau 1, 2, 3...)
        $kolomTanggal = 0;
        foreach ($header as $col) {
            if ($this->apakahHeaderTanggal($col)) {
                $kolomTanggal++;
            }
        }

        if ($kolomTanggal >= 3) {
            return 'matriks';
        }

        return 'matriks'; // Default ke matriks jika format kantor pusat
    }

    /**
     * Memvalidasi dan menyiapkan preview data dari file yang diunggah.
     */
    public function validasiDanSiapkanPreview(array $barisData, int $tahunDefault, int $bulanDefault): array
    {
        $format = $this->deteksiFormat($barisData);

        if ($format === 'matriks') {
            return $this->prosesPreviewMatriks($barisData, $tahunDefault, $bulanDefault);
        }

        return $this->prosesPreviewTabelStandar($barisData);
    }

    private function apakahHeaderTanggal(string $header): bool
    {
        $h = trim($header);
        if (empty($h)) return false;

        // Misal: 01-Sep, 01/09, 1, 01, 2026-09-01
        if (preg_match('/^\d{1,2}[\/\-_][A-Za-z0-9]+/', $h)) return true;
        if (is_numeric($h) && (int) $h >= 1 && (int) $h <= 31) return true;

        return false;
    }

    /**
     * Mengurai tanggal dari header kolom matriks (contoh: "01-Sep", "1", "2026-09-01").
     */
    private function parseTanggalHeader(string $header, int $tahunDefault, int $bulanDefault): ?string
    {
        $h = trim($header);

        // Kasus angka hari: 1, 2, ..., 31
        if (is_numeric($h) && (int) $h >= 1 && (int) $h <= 31) {
            return sprintf('%04d-%02d-%02d', $tahunDefault, $bulanDefault, (int) $h);
        }

        // Kasus format: 01-Sep atau 01-Sep-2026 atau 01/09/2026
        try {
            $parsed = Carbon::parse($h);
            // Jika tidak ada tahun yang eksplisit pada header, gunakan tahun dan bulan default
            if (! str_contains($h, (string) $tahunDefault)) {
                return sprintf('%04d-%02d-%02d', $tahunDefault, $parsed->month, $parsed->day);
            }
            return $parsed->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Memproses file Format 1: Matriks Jadwal Kantor Pusat.
     */
    private function prosesPreviewMatriks(array $barisData, int $tahunDefault, int $bulanDefault): array
    {
        $header = $barisData[0] ?? [];
        $headerLower = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        // Cari indeks kolom identitas
        $idxNik = -1;
        $idxNip = -1;
        $idxNama = -1;
        $idxProyek = -1;
        $mapKolomTanggal = []; // index => YYYY-MM-DD

        foreach ($headerLower as $idx => $colName) {
            if (str_contains($colName, 'nik')) {
                $idxNik = $idx;
            } elseif (str_contains($colName, 'nip')) {
                $idxNip = $idx;
            } elseif (str_contains($colName, 'nama')) {
                $idxNama = $idx;
            } elseif (str_contains($colName, 'project') || str_contains($colName, 'proyek')) {
                $idxProyek = $idx;
            } elseif ($this->apakahHeaderTanggal($colName)) {
                $tgl = $this->parseTanggalHeader($colName, $tahunDefault, $bulanDefault);
                if ($tgl) {
                    $mapKolomTanggal[$idx] = $tgl;
                }
            }
        }

        // Ambil semua karyawan aktif untuk pencocokan cepat
        $karyawanList = Karyawan::with('jabatan')->get();
        $byNik = $karyawanList->keyBy(fn ($k) => trim(strtoupper((string) $k->nik)));
        $byNip = $karyawanList->filter(fn ($k) => ! empty($k->nip))->keyBy(fn ($k) => trim(strtoupper((string) $k->nip)));
        $byNama = $karyawanList->keyBy(fn ($k) => strtolower(trim($k->nama)));

        $hasilValid = [];
        $hasilBermasalah = [];
        $totalKerja = 0;
        $totalLibur = 0;
        $driverUnik = [];

        // Periode terdeteksi
        $tanggalTerdeteksi = array_values($mapKolomTanggal);
        sort($tanggalTerdeteksi);
        $periodeMulai = $tanggalTerdeteksi[0] ?? sprintf('%04d-%02d-01', $tahunDefault, $bulanDefault);
        $periodeSelesai = end($tanggalTerdeteksi) ?: sprintf('%04d-%02d-28', $tahunDefault, $bulanDefault);

        for ($r = 1; $r < count($barisData); $r++) {
            $row = $barisData[$r];
            $nik = $idxNik >= 0 ? trim((string) ($row[$idxNik] ?? '')) : '';
            $nip = $idxNip >= 0 ? trim((string) ($row[$idxNip] ?? '')) : '';
            $nama = $idxNama >= 0 ? trim((string) ($row[$idxNama] ?? '')) : '';
            $proyek = $idxProyek >= 0 ? trim((string) ($row[$idxProyek] ?? '')) : 'Operasional MTU';
            if (empty($proyek)) $proyek = 'Operasional MTU';

            // Lewati baris kosong
            if (empty($nik) && empty($nip) && empty($nama)) {
                continue;
            }

            // Cari karyawan: 1. NIK, 2. NIP, 3. Nama
            $karyawan = null;
            if (! empty($nik) && $byNik->has(strtoupper($nik))) {
                $karyawan = $byNik->get(strtoupper($nik));
            } elseif (! empty($nip) && $byNip->has(strtoupper($nip))) {
                $karyawan = $byNip->get(strtoupper($nip));
            } elseif (! empty($nama) && $byNama->has(strtolower($nama))) {
                $karyawan = $byNama->get(strtolower($nama));
            }

            if (! $karyawan) {
                $hasilBermasalah[] = [
                    'baris' => $r + 1,
                    'nama' => $nama ?: '-',
                    'nik' => $nik ?: '-',
                    'nip' => $nip ?: '-',
                    'proyek' => $proyek,
                    'alasan' => 'Driver tidak ditemukan di database karyawan (NIK/NIP tidak cocok).',
                ];
                continue;
            }

            $driverUnik[$karyawan->id] = true;

            // Baca setiap sel tanggal
            foreach ($mapKolomTanggal as $colIdx => $tanggal) {
                $val = trim((string) ($row[$colIdx] ?? ''));
                if ($val === '') continue;

                $valUpper = strtoupper($val);
                // Aturan kode: 'L' = Libur, '1' = Kerja, kode lain ditandai
                $isLibur = ($valUpper === 'L');
                $isKerja = ($valUpper === '1' || $valUpper === 'K' || $valUpper === 'W');

                if (! $isLibur && ! $isKerja) {
                    $hasilBermasalah[] = [
                        'baris' => $r + 1,
                        'nama' => $karyawan->nama,
                        'nik' => $karyawan->nik,
                        'nip' => $karyawan->nip ?? '-',
                        'proyek' => $proyek,
                        'tanggal' => $tanggal,
                        'alasan' => "Kode harian '{$val}' tidak dikenali (gunakan 'L' untuk libur, '1' untuk kerja).",
                    ];
                    continue;
                }

                $statusJadwal = $isLibur ? 'libur' : 'kerja';
                if ($isLibur) $totalLibur++;
                if ($isKerja) $totalKerja++;

                $hasilValid[] = [
                    'karyawan_id' => $karyawan->id,
                    'karyawan_nama' => $karyawan->nama,
                    'karyawan_nip' => $karyawan->nip ?? '-',
                    'karyawan_nik' => $karyawan->nik,
                    'proyek' => $proyek,
                    'tanggal' => $tanggal,
                    'status_jadwal' => $statusJadwal,
                    'kode_asli' => $val,
                    'keterangan' => 'Import Matriks Kantor Pusat',
                ];
            }
        }

        // Cek potensi konflik dan penimpaan terhadap jadwal database
        $analisisKonflik = $this->analisisBentrokDanKebutuhan($hasilValid);

        return [
            'format' => 'matriks',
            'periode_mulai' => $periodeMulai,
            'periode_selesai' => $periodeSelesai,
            'total_baris_dibaca' => count($barisData) - 1,
            'total_driver' => count($driverUnik),
            'total_kerja' => $totalKerja,
            'total_libur' => $totalLibur,
            'jadwal_valid' => $hasilValid,
            'jadwal_bermasalah' => $hasilBermasalah,
            'analisis' => $analisisKonflik,
        ];
    }

    /**
     * Memproses file Format 2: Tabel Standar.
     */
    private function prosesPreviewTabelStandar(array $barisData): array
    {
        $header = $barisData[0] ?? [];
        $headerLower = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $idxNik = -1;
        $idxNip = -1;
        $idxNama = -1;
        $idxProyek = -1;
        $idxTanggal = -1;
        $idxStatus = -1;
        $idxPengganti = -1;
        $idxKeterangan = -1;

        foreach ($headerLower as $idx => $col) {
            if (str_contains($col, 'nik')) $idxNik = $idx;
            elseif (str_contains($col, 'nip')) $idxNip = $idx;
            elseif (str_contains($col, 'nama')) $idxNama = $idx;
            elseif (str_contains($col, 'proyek') || str_contains($col, 'project')) $idxProyek = $idx;
            elseif (str_contains($col, 'tanggal')) $idxTanggal = $idx;
            elseif (str_contains($col, 'status')) $idxStatus = $idx;
            elseif (str_contains($col, 'pengganti')) $idxPengganti = $idx;
            elseif (str_contains($col, 'keterangan')) $idxKeterangan = $idx;
        }

        $karyawanList = Karyawan::all();
        $byNik = $karyawanList->keyBy(fn ($k) => trim(strtoupper((string) $k->nik)));
        $byNip = $karyawanList->filter(fn ($k) => ! empty($k->nip))->keyBy(fn ($k) => trim(strtoupper((string) $k->nip)));
        $byNama = $karyawanList->keyBy(fn ($k) => strtolower(trim($k->nama)));

        $hasilValid = [];
        $hasilBermasalah = [];
        $totalKerja = 0;
        $totalLibur = 0;
        $driverUnik = [];
        $semuaTanggal = [];

        for ($r = 1; $r < count($barisData); $r++) {
            $row = $barisData[$r];
            $nik = $idxNik >= 0 ? trim((string) ($row[$idxNik] ?? '')) : '';
            $nip = $idxNip >= 0 ? trim((string) ($row[$idxNip] ?? '')) : '';
            $nama = $idxNama >= 0 ? trim((string) ($row[$idxNama] ?? '')) : '';
            $proyek = $idxProyek >= 0 ? trim((string) ($row[$idxProyek] ?? '')) : 'Operasional MTU';
            $tglRaw = $idxTanggal >= 0 ? trim((string) ($row[$idxTanggal] ?? '')) : '';
            $statusRaw = $idxStatus >= 0 ? trim((string) ($row[$idxStatus] ?? '')) : 'kerja';
            $penggantiRaw = $idxPengganti >= 0 ? trim((string) ($row[$idxPengganti] ?? '')) : '';
            $ket = $idxKeterangan >= 0 ? trim((string) ($row[$idxKeterangan] ?? '')) : 'Import Tabel Standar';

            if (empty($nik) && empty($nip) && empty($nama) && empty($tglRaw)) {
                continue;
            }

            // Parse tanggal
            $tanggal = null;
            try {
                if (is_numeric($tglRaw)) {
                    $tanggal = ExcelDate::excelToDateTimeObject((float) $tglRaw)->format('Y-m-d');
                } else {
                    $tanggal = Carbon::parse($tglRaw)->format('Y-m-d');
                }
            } catch (\Throwable) {
                $hasilBermasalah[] = [
                    'baris' => $r + 1,
                    'nama' => $nama ?: '-',
                    'nik' => $nik ?: '-',
                    'nip' => $nip ?: '-',
                    'proyek' => $proyek,
                    'alasan' => "Format tanggal '{$tglRaw}' tidak valid.",
                ];
                continue;
            }

            $karyawan = null;
            if (! empty($nik) && $byNik->has(strtoupper($nik))) {
                $karyawan = $byNik->get(strtoupper($nik));
            } elseif (! empty($nip) && $byNip->has(strtoupper($nip))) {
                $karyawan = $byNip->get(strtoupper($nip));
            } elseif (! empty($nama) && $byNama->has(strtolower($nama))) {
                $karyawan = $byNama->get(strtolower($nama));
            }

            if (! $karyawan) {
                $hasilBermasalah[] = [
                    'baris' => $r + 1,
                    'nama' => $nama ?: '-',
                    'nik' => $nik ?: '-',
                    'nip' => $nip ?: '-',
                    'proyek' => $proyek,
                    'tanggal' => $tanggal,
                    'alasan' => 'Driver tidak ditemukan di database karyawan.',
                ];
                continue;
            }

            $driverUnik[$karyawan->id] = true;
            $semuaTanggal[] = $tanggal;

            $statusLower = strtolower($statusRaw);
            $isLibur = str_contains($statusLower, 'libur') || $statusLower === 'l';
            $statusJadwal = $isLibur ? 'libur' : 'kerja';

            if ($isLibur) $totalLibur++;
            else $totalKerja++;

            // Cari pengganti jika dicantumkan
            $penggantiId = null;
            if (! empty($penggantiRaw)) {
                if ($byNik->has(strtoupper($penggantiRaw))) {
                    $penggantiId = $byNik->get(strtoupper($penggantiRaw))->id;
                } elseif ($byNip->has(strtoupper($penggantiRaw))) {
                    $penggantiId = $byNip->get(strtoupper($penggantiRaw))->id;
                } elseif ($byNama->has(strtolower($penggantiRaw))) {
                    $penggantiId = $byNama->get(strtolower($penggantiRaw))->id;
                }
            }

            $hasilValid[] = [
                'karyawan_id' => $karyawan->id,
                'karyawan_nama' => $karyawan->nama,
                'karyawan_nip' => $karyawan->nip ?? '-',
                'karyawan_nik' => $karyawan->nik,
                'proyek' => $proyek ?: 'Operasional MTU',
                'tanggal' => $tanggal,
                'status_jadwal' => $statusJadwal,
                'driver_pengganti_id' => $penggantiId,
                'keterangan' => $ket ?: 'Import Tabel Standar',
            ];
        }

        sort($semuaTanggal);
        $periodeMulai = $semuaTanggal[0] ?? date('Y-m-01');
        $periodeSelesai = end($semuaTanggal) ?: date('Y-m-t');

        $analisis = $this->analisisBentrokDanKebutuhan($hasilValid);

        return [
            'format' => 'tabel_standar',
            'periode_mulai' => $periodeMulai,
            'periode_selesai' => $periodeSelesai,
            'total_baris_dibaca' => count($barisData) - 1,
            'total_driver' => count($driverUnik),
            'total_kerja' => $totalKerja,
            'total_libur' => $totalLibur,
            'jadwal_valid' => $hasilValid,
            'jadwal_bermasalah' => $hasilBermasalah,
            'analisis' => $analisis,
        ];
    }

    /**
     * Menganalisis potensi bentrok jadwal pribadi dan kebutuhan minimum driver per proyek.
     */
    private function analisisBentrokDanKebutuhan(array $jadwalValid): array
    {
        $konflikPribadi = 0;
        $konflikKebutuhanProyek = 0;
        $dataBaru = 0;
        $dataMenimpa = 0;
        $dataSama = 0;

        // Cek duplikasi di dalam batch itu sendiri (driver sama, tanggal sama)
        $tercatatDalamBatch = [];
        foreach ($jadwalValid as $j) {
            $key = $j['karyawan_id'] . '_' . $j['tanggal'];
            if (isset($tercatatDalamBatch[$key])) {
                $konflikPribadi++;
            }
            $tercatatDalamBatch[$key] = $j['status_jadwal'];
        }

        // Cek perbandingan dengan data existing di database
        if (! empty($jadwalValid)) {
            $tanggalList = array_unique(array_column($jadwalValid, 'tanggal'));
            $karyawanIds = array_unique(array_column($jadwalValid, 'karyawan_id'));

            $existing = JadwalDriver::whereIn('karyawan_id', $karyawanIds)
                ->whereIn('tanggal', $tanggalList)
                ->get()
                ->keyBy(fn ($item) => $item->karyawan_id . '_' . $item->tanggal->toDateString());

            foreach ($jadwalValid as $j) {
                $key = $j['karyawan_id'] . '_' . $j['tanggal'];
                if ($existing->has($key)) {
                    $lama = $existing->get($key);
                    if ($lama->status_jadwal === $j['status_jadwal']) {
                        $dataSama++;
                    } else {
                        $dataMenimpa++;
                    }
                } else {
                    $dataBaru++;
                }
            }
        }

        // Analisis kebutuhan minimum driver per proyek dan tanggal
        // Hitung driver libur vs driver kerja per proyek & tanggal
        $rekapProyekTanggal = [];
        foreach ($jadwalValid as $j) {
            $proyek = $j['proyek'] ?: 'Operasional MTU';
            $tgl = $j['tanggal'];
            $st = $j['status_jadwal'];

            $rekapProyekTanggal[$proyek][$tgl][$st] = ($rekapProyekTanggal[$proyek][$tgl][$st] ?? 0) + 1;
        }

        $daftarPeringatanKebutuhan = [];
        foreach ($rekapProyekTanggal as $proyek => $perTanggal) {
            $minKebutuhan = ProyekKebutuhanDriver::kebutuhanProyek($proyek);

            foreach ($perTanggal as $tgl => $counts) {
                $kerja = $counts['kerja'] ?? 0;
                $libur = $counts['libur'] ?? 0;

                // Jika ada driver yang bekerja tetapi jumlahnya di bawah batas minimum
                if ($kerja > 0 && $kerja < $minKebutuhan) {
                    $konflikKebutuhanProyek++;
                    $daftarPeringatanKebutuhan[] = [
                        'proyek' => $proyek,
                        'tanggal' => $tgl,
                        'driver_aktif' => $kerja,
                        'min_kebutuhan' => $minKebutuhan,
                        'driver_libur' => $libur,
                        'pesan' => "Proyek '{$proyek}' pada {$tgl} hanya memiliki {$kerja} driver aktif (kebutuhan minimum: {$minKebutuhan}). Driver libur: {$libur}.",
                    ];
                }
            }
        }

        return [
            'data_baru' => $dataBaru,
            'data_menimpa' => $dataMenimpa,
            'data_sama' => $dataSama,
            'konflik_pribadi' => $konflikPribadi,
            'konflik_kebutuhan_proyek' => $konflikKebutuhanProyek,
            'peringatan_kebutuhan' => $daftarPeringatanKebutuhan,
        ];
    }

    /**
     * Menyimpan jadwal hasil import ke dalam database secara atomic via DB::transaction.
     */
    public function eksekusiImport(array $jadwalValid, string $namaFile, string $format, ?int $userId, bool $timpaJadwalLama = true): JadwalImportBatch
    {
        return DB::transaction(function () use ($jadwalValid, $namaFile, $format, $userId, $timpaJadwalLama) {
            $tanggalList = array_unique(array_column($jadwalValid, 'tanggal'));
            sort($tanggalList);
            $tglPertama = $tanggalList[0] ?? date('Y-m-d');
            $c = CarbonImmutable::parse($tglPertama);

            $totalKerja = 0;
            $totalLibur = 0;
            $driverIds = [];

            foreach ($jadwalValid as $j) {
                if ($j['status_jadwal'] === 'libur') $totalLibur++;
                else $totalKerja++;
                $driverIds[$j['karyawan_id']] = true;
            }

            // Buat record batch import
            $batch = JadwalImportBatch::create([
                'nomor_batch' => 'BATCH-' . $c->format('Ym') . '-' . strtoupper(substr(uniqid(), -6)),
                'nama_file' => $namaFile,
                'periode_bulan' => $c->format('Y-m'),
                'periode_tahun' => $c->year,
                'format_import' => $format,
                'total_baris' => count($jadwalValid),
                'total_driver' => count($driverIds),
                'total_kerja' => $totalKerja,
                'total_libur' => $totalLibur,
                'created_by' => $userId,
            ]);

            // Simpan setiap item dengan updateOrCreate agar idempotent
            foreach ($jadwalValid as $item) {
                $statusVerifikasi = 'disetujui';

                // Cek kebutuhan proyek
                $minKebutuhan = ProyekKebutuhanDriver::kebutuhanProyek($item['proyek'] ?? 'Operasional MTU');
                // Bila libur tanpa pengganti di proyek dengan driver minim, beri catatan verifikasi
                if ($item['status_jadwal'] === 'libur' && empty($item['driver_pengganti_id'])) {
                    $statusVerifikasi = 'perlu_verifikasi';
                }

                $tglParsed = CarbonImmutable::parse($item['tanggal'])->toDateString();
                $jadwal = JadwalDriver::where('karyawan_id', $item['karyawan_id'])
                    ->whereDate('tanggal', $tglParsed)
                    ->first();

                if (! $jadwal) {
                    $jadwal = new JadwalDriver([
                        'karyawan_id' => $item['karyawan_id'],
                        'tanggal' => $tglParsed,
                    ]);
                }

                $jadwal->fill([
                    'status_jadwal' => $item['status_jadwal'],
                    'sumber' => 'import_pusat',
                    'batch_id' => $batch->id,
                    'proyek' => $item['proyek'] ?? 'Operasional MTU',
                    'driver_pengganti_id' => $item['driver_pengganti_id'] ?? null,
                    'status_verifikasi' => $statusVerifikasi,
                    'keterangan' => $item['keterangan'] ?? 'Import Jadwal Kantor Pusat',
                    'created_by' => $userId,
                ]);
                $jadwal->save();

                // Perbarui kolom proyek pada karyawan jika belum terisi
                Karyawan::where('id', $item['karyawan_id'])
                    ->where(function ($q) {
                        $q->whereNull('proyek')->orWhere('proyek', 'Operasional MTU');
                    })
                    ->update(['proyek' => $item['proyek'] ?? 'Operasional MTU']);
            }

            return $batch;
        });
    }
}
