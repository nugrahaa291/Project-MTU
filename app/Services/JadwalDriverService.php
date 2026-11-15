<?php

namespace App\Services;

use App\Models\JadwalDriver;
use App\Models\JadwalRiwayatPerubahan;
use App\Models\Karyawan;
use App\Models\ProyekKebutuhanDriver;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class JadwalDriverService
{
    /**
     * Mengambil ringkasan kalender bulanan lengkap dengan titik merah indikator driver libur.
     */
    public function ambilKalenderBulanan(int $tahun, int $bulan, array $filter = []): array
    {
        $awalBulan = CarbonImmutable::createFromDate($tahun, $bulan, 1);
        $akhirBulan = $awalBulan->endOfMonth();
        $hariIni = CarbonImmutable::now(config('absensi.timezone', 'Asia/Jakarta'))->toDateString();

        // Query seluruh jadwal libur pada bulan tersebut
        $query = JadwalDriver::with(['karyawan', 'driverPengganti'])
            ->whereBetween('tanggal', [$awalBulan->toDateString(), $akhirBulan->toDateString()]);

        if (! empty($filter['cari'])) {
            $cari = $filter['cari'];
            $query->whereHas('karyawan', function ($q) use ($cari) {
                $q->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nip', 'like', "%{$cari}%")
                    ->orWhere('nik', 'like', "%{$cari}%");
            });
        }

        if (! empty($filter['proyek'])) {
            $query->where('proyek', $filter['proyek']);
        }

        if (! empty($filter['status'])) {
            $query->where('status_jadwal', $filter['status']);
        }

        $semuaJadwal = $query->get()->groupBy(fn ($item) => $item->tanggal->toDateString());

        // Susun struktur hari kalender
        $daftarHari = [];
        // Hari pertama bulan jatuh pada hari apa (1=Senin..7=Minggu)
        $offsetAwal = $awalBulan->dayOfWeekIso - 1; // 0 jika Senin

        for ($i = 0; $i < $offsetAwal; $i++) {
            $daftarHari[] = [
                'tanggal' => null,
                'nomor_hari' => null,
                'apakah_bulan_ini' => false,
                'apakah_hari_ini' => false,
                'ada_driver_libur' => false,
                'jumlah_driver_libur' => 0,
                'daftar_driver_libur' => [],
            ];
        }

        for ($d = 1; $d <= $akhirBulan->day; $d++) {
            $tglStr = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
            $jadwalTgl = $semuaJadwal->get($tglStr, collect());
            $driverLibur = $jadwalTgl->where('status_jadwal', 'libur');

            $daftarHari[] = [
                'tanggal' => $tglStr,
                'nomor_hari' => $d,
                'apakah_bulan_ini' => true,
                'apakah_hari_ini' => ($tglStr === $hariIni),
                'ada_driver_libur' => $driverLibur->isNotEmpty(),
                'jumlah_driver_libur' => $driverLibur->count(),
                'daftar_driver_libur' => $driverLibur->map(function ($j) {
                    return [
                        'id' => $j->id,
                        'karyawan_id' => $j->karyawan_id,
                        'nama' => $j->karyawan?->nama ?? 'Driver #'.$j->karyawan_id,
                        'nip' => $j->karyawan?->nip ?? '-',
                        'nik' => $j->karyawan?->nik ?? '-',
                        'proyek' => $j->proyek ?? 'Operasional MTU',
                        'status_jadwal' => $j->status_jadwal,
                        'sumber' => $j->sumber === 'import_pusat' ? 'Import Kantor Pusat' : 'Input Manual Cabang',
                        'pengganti' => $j->driverPengganti?->nama ?? null,
                        'status_verifikasi' => $j->status_verifikasi,
                        'keterangan' => $j->keterangan ?? '-',
                    ];
                })->values()->all(),
            ];
        }

        return [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'nama_bulan' => $awalBulan->translatedFormat('F Y'),
            'hariIni' => $hariIni,
            'daftar_hari' => $daftarHari,
            'total_driver_libur_bulan_ini' => $semuaJadwal->map(fn ($g) => $g->where('status_jadwal', 'libur')->count())->sum(),
        ];
    }

    /**
     * Memvalidasi dan menyimpan input libur manual (mendadak) dari cabang.
     */
    public function tambahLiburManual(array $data, User $admin): array
    {
        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        $proyek = $data['proyek'] ?? $karyawan->proyek ?? 'Operasional MTU';
        $tglMulai = CarbonImmutable::parse($data['tanggal_mulai']);
        $tglSelesai = ! empty($data['tanggal_selesai'])
            ? CarbonImmutable::parse($data['tanggal_selesai'])
            : $tglMulai;

        if ($tglSelesai->lt($tglMulai)) {
            throw new \InvalidArgumentException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }

        $period = CarbonPeriod::create($tglMulai, $tglSelesai);
        $minKebutuhan = ProyekKebutuhanDriver::kebutuhanProyek($proyek);

        $jadwalDibuat = [];
        $peringatan = [];

        DB::transaction(function () use ($period, $karyawan, $proyek, $data, $admin, $minKebutuhan, &$jadwalDibuat, &$peringatan) {
            foreach ($period as $date) {
                $tglStr = $date->toDateString();

                // 1. Cek bentrok pribadi: apakah driver sudah libur pada tanggal tersebut
                $existing = JadwalDriver::where('karyawan_id', $karyawan->id)
                    ->whereDate('tanggal', $tglStr)
                    ->first();

                $statusLama = $existing?->status_jadwal;
                $penggantiLama = $existing?->driver_pengganti_id;

                // 2. Cek apakah driver ditugaskan sebagai pengganti orang lain pada tanggal tersebut
                $jadiPengganti = JadwalDriver::where('driver_pengganti_id', $karyawan->id)
                    ->whereDate('tanggal', $tglStr)
                    ->first();

                if ($jadiPengganti) {
                    throw new \DomainException("Driver {$karyawan->nama} tidak dapat diliburkan pada {$tglStr} karena sedang ditugaskan sebagai driver pengganti untuk {$jadiPengganti->karyawan?->nama}.");
                }

                // 3. Validasi driver pengganti jika dipilih
                $penggantiId = ! empty($data['driver_pengganti_id']) ? (int) $data['driver_pengganti_id'] : null;
                if ($penggantiId) {
                    if ($penggantiId === $karyawan->id) {
                        throw new \DomainException('Driver tidak dapat menjadi pengganti untuk dirinya sendiri.');
                    }

                    // Cek apakah calon pengganti sedang libur pada tanggal tersebut
                    $penggantiLibur = JadwalDriver::where('karyawan_id', $penggantiId)
                        ->whereDate('tanggal', $tglStr)
                        ->where('status_jadwal', 'libur')
                        ->first();

                    if ($penggantiLibur) {
                        throw new \DomainException("Driver pengganti yang dipilih sedang dijadwalkan libur pada tanggal {$tglStr}.");
                    }
                }

                // 4. Hitung kebutuhan minimum driver aktif per proyek
                $driverAktif = JadwalDriver::where('proyek', $proyek)
                    ->whereDate('tanggal', $tglStr)
                    ->where('status_jadwal', 'kerja')
                    ->where('karyawan_id', '!=', $karyawan->id)
                    ->count();

                $statusVerifikasi = 'disetujui';
                if ($driverAktif < $minKebutuhan && empty($penggantiId)) {
                    $statusVerifikasi = 'perlu_verifikasi';
                    $peringatan[] = "Kebutuhan minimum driver aktif proyek {$proyek} pada {$tglStr} belum terpenuhi (tersisa {$driverAktif} aktif, min: {$minKebutuhan}). Jadwal ditandai Perlu Verifikasi Admin.";
                }

                $jadwal = JadwalDriver::updateOrCreate(
                    [
                        'karyawan_id' => $karyawan->id,
                        'tanggal' => $tglStr,
                    ],
                    [
                        'status_jadwal' => 'libur',
                        'sumber' => 'manual_cabang',
                        'proyek' => $proyek,
                        'driver_pengganti_id' => $penggantiId,
                        'status_verifikasi' => $statusVerifikasi,
                        'alasan_pengecualian' => $data['alasan_pengecualian'] ?? null,
                        'keterangan' => $data['keterangan'] ?? 'Libur mendadak diajukan cabang',
                        'created_by' => $admin->id,
                    ]
                );

                // Catat riwayat perubahan jika sebelumnya ada jadwal
                JadwalRiwayatPerubahan::create([
                    'jadwal_id' => $jadwal->id,
                    'karyawan_id' => $karyawan->id,
                    'tanggal' => $tglStr,
                    'status_sebelumnya' => $statusLama,
                    'status_baru' => 'libur',
                    'driver_pengganti_sebelumnya' => $penggantiLama,
                    'driver_pengganti_baru' => $penggantiId,
                    'diubah_oleh' => $admin->id,
                    'alasan' => $data['alasan_perubahan'] ?? 'Pengajuan libur manual cabang',
                ]);

                $jadwalDibuat[] = $jadwal;
            }
        });

        return [
            'sukses' => true,
            'total_hari' => count($jadwalDibuat),
            'peringatan' => $peringatan,
        ];
    }

    /**
     * Memperbarui penugasan driver pengganti pada jadwal yang sudah ada.
     */
    public function tentukanPengganti(JadwalDriver $jadwal, ?int $penggantiId, string $alasan, User $admin): void
    {
        if ($penggantiId) {
            $pengganti = Karyawan::findOrFail($penggantiId);
            if (! $pengganti->aktif) {
                throw new \DomainException('Driver pengganti harus merupakan karyawan aktif.');
            }

            if ($penggantiId === $jadwal->karyawan_id) {
                throw new \DomainException('Driver tidak dapat menjadi pengganti dirinya sendiri.');
            }

            $sedangLibur = JadwalDriver::where('karyawan_id', $penggantiId)
                ->whereDate('tanggal', $jadwal->tanggal->toDateString())
                ->where('status_jadwal', 'libur')
                ->exists();

            if ($sedangLibur) {
                throw new \DomainException("Driver {$pengganti->nama} sedang libur pada tanggal tersebut.");
            }
        }

        $penggantiLama = $jadwal->driver_pengganti_id;

        DB::transaction(function () use ($jadwal, $penggantiId, $penggantiLama, $alasan, $admin) {
            $jadwal->update([
                'driver_pengganti_id' => $penggantiId,
                'status_verifikasi' => 'disetujui',
            ]);

            JadwalRiwayatPerubahan::create([
                'jadwal_id' => $jadwal->id,
                'karyawan_id' => $jadwal->karyawan_id,
                'tanggal' => $jadwal->tanggal->toDateString(),
                'status_sebelumnya' => $jadwal->status_jadwal,
                'status_baru' => $jadwal->status_jadwal,
                'driver_pengganti_sebelumnya' => $penggantiLama,
                'driver_pengganti_baru' => $penggantiId,
                'diubah_oleh' => $admin->id,
                'alasan' => $alasan ?: 'Penugasan driver pengganti oleh Admin',
            ]);
        });
    }

    /**
     * Membuat template file Excel standar Format 1 (Matriks) dan Format 2 (Tabel Standar).
     */
    public function buatTemplateSpreadsheet(string $format = 'matriks', int $tahun = 2026, int $bulan = 9): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($format === 'matriks') {
            $sheet->setTitle('Jadwal Matriks Driver');
            $headers = ['No', 'Nama Driver', 'NIP', 'NIK MTU', 'PROJECT'];

            $daysInMonth = CarbonImmutable::createFromDate($tahun, $bulan, 1)->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $headers[] = sprintf('%02d-%s', $d, CarbonImmutable::createFromDate($tahun, $bulan, $d)->format('M'));
            }

            $sheet->fromArray($headers, null, 'A1');

            // Baris contoh driver
            $sampleRows = [
                [1, 'Diki Maulana', 'MTU2001', '3215010101900001', 'Wingbox'],
                [2, 'Teguh Setiawan', 'MTU2002', '3215010202920002', 'Trailer'],
                [3, 'Budi Santoso', 'MTU2003', '3215010303930003', 'Logistik'],
            ];

            // Tambahkan jadwal contoh: '1' untuk kerja, 'L' untuk libur
            foreach ($sampleRows as $rIdx => $row) {
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    // Beri libur setiap hari ke-7
                    $row[] = ($d % 7 === 0) ? 'L' : '1';
                }
                $sheet->fromArray($row, null, 'A' . ($rIdx + 2));
            }

            // Styling header
            $highestCol = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        } else {
            $sheet->setTitle('Jadwal Standar Driver');
            $headers = ['NIP', 'NIK MTU', 'Nama Driver', 'Proyek', 'Tanggal', 'Status Jadwal', 'Driver Pengganti', 'Keterangan'];
            $sheet->fromArray($headers, null, 'A1');

            $sampleRows = [
                ['MTU2001', '3215010101900001', 'Diki Maulana', 'Wingbox', '2026-09-07', 'Libur', 'Teguh Setiawan', 'Libur bergilir mingguan'],
                ['MTU2002', '3215010202920002', 'Teguh Setiawan', 'Trailer', '2026-09-08', 'Libur', '', 'Libur rutin'],
            ];
            $sheet->fromArray($sampleRows, null, 'A2');

            $sheet->getStyle('A1:H1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Auto width kolom
        $highestCol = $sheet->getHighestColumn();
        $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
        for ($i = 1; $i <= $colIndex; $i++) {
            $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colString)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
