<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusAbsensi;
use App\Http\Controllers\Controller;
use App\Http\Resources\AbsensiResource;
use App\Models\Absensi;
use App\Services\AbsensiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function __construct(private AbsensiService $service)
    {
    }

    /** Status hari ini: dipakai aplikasi untuk menentukan tombol Masuk/Pulang. */
    public function hariIni(Request $request): JsonResponse
    {
        $karyawan = $request->user()->karyawan;
        $sekarang = $this->service->sekarang();

        $absensi = Absensi::where('karyawan_id', $karyawan->id)
            ->whereDate('tanggal', $sekarang->toDateString())->first();

        $hariKerja = $this->service->hariKerja($sekarang);

        return response()->json([
            'tanggal' => $sekarang->toDateString(),
            'waktu_server' => $sekarang->toIso8601String(),
            'hari_kerja' => $hariKerja,
            'kantor' => [
                'nama' => config('absensi.kantor.nama'),
                'latitude' => config('absensi.kantor.latitude'),
                'longitude' => config('absensi.kantor.longitude'),
                'radius_meter' => config('absensi.kantor.radius_meter'),
            ],
            'jam_masuk' => config('absensi.jam_masuk'),
            'toleransi_menit' => config('absensi.toleransi_menit'),
            'bisa_masuk' => $karyawan->aktif && $hariKerja && ! $absensi,
            'bisa_pulang' => (bool) ($absensi?->jam_masuk && ! $absensi->jam_pulang),
            'absensi' => $absensi ? new AbsensiResource($absensi) : null,
        ]);
    }

    public function masuk(Request $request): JsonResponse
    {
        $data = $this->validasiAbsen($request);

        $absensi = $this->service->masuk(
            $request->user()->karyawan,
            (float) $data['latitude'],
            (float) $data['longitude'],
            $request->file('foto'),
            isset($data['akurasi']) ? (float) $data['akurasi'] : null,
            (bool) ($data['lokasi_palsu'] ?? false),
        );

        $pesan = $absensi->status === StatusAbsensi::Terlambat
            ? "Absen masuk tercatat. Anda terlambat {$absensi->menit_terlambat} menit."
            : 'Absen masuk berhasil. Selamat bekerja!';

        return response()->json(['message' => $pesan, 'absensi' => new AbsensiResource($absensi)], 201);
    }

    public function pulang(Request $request): JsonResponse
    {
        $data = $this->validasiAbsen($request);

        $absensi = $this->service->pulang(
            $request->user()->karyawan,
            (float) $data['latitude'],
            (float) $data['longitude'],
            $request->file('foto'),
            isset($data['akurasi']) ? (float) $data['akurasi'] : null,
            (bool) ($data['lokasi_palsu'] ?? false),
        );

        return response()->json(['message' => 'Absen pulang berhasil. Hati-hati di jalan!', 'absensi' => new AbsensiResource($absensi)]);
    }

    /** Riwayat per bulan. Contoh: GET /api/absensi?bulan=2026-10 */
    public function riwayat(Request $request): JsonResponse
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        $bulan = $request->input('bulan', $this->service->sekarang()->format('Y-m'));
        $awal = CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();

        $data = Absensi::where('karyawan_id', $request->user()->karyawan->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->endOfMonth()->toDateString()])
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'bulan' => $bulan,
            'ringkasan' => $data->groupBy(fn ($a) => $a->status->value)->map->count(),
            'data' => AbsensiResource::collection($data),
        ]);
    }

    private function validasiAbsen(Request $request): array
    {
        return $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'akurasi' => ['nullable', 'numeric', 'min:0'],   // akurasi GPS dalam meter, dari perangkat
            'lokasi_palsu' => ['nullable', 'boolean'],       // flag isMock dari Android
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);
    }
}
