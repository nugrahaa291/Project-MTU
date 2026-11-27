<?php

namespace App\Http\Controllers\Api;

use App\Enums\KategoriIzin;
use App\Http\Controllers\Controller;
use App\Http\Resources\AbsensiResource;
use App\Http\Resources\KegiatanResource;
use App\Models\Absensi;
use App\Models\KegiatanAbsensi;
use App\Services\AbsensiService;
use App\Services\KegiatanAbsensiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API alur absensi baru. Identitas karyawan SELALU dari token (akun yang login), tidak pernah dari data kiriman.
 */
class AktivitasController extends Controller
{
    public function __construct(private KegiatanAbsensiService $service, private AbsensiService $waktu)
    {
    }

    /** Data untuk halaman utama: aktivitas hari ini dan status pengajuan izin lain-lain. */
    public function ringkasan(Request $request): JsonResponse
    {
        $karyawan = $request->user()->karyawan;
        $sekarang = $this->waktu->sekarang();

        $hariIni = KegiatanAbsensi::where('karyawan_id', $karyawan->id)
            ->whereDate('tanggal', $sekarang->toDateString())
            ->latest('dicatat_pada')->latest('id')->get();

        $izinLainnya = KegiatanAbsensi::with('pengganti')
            ->where('karyawan_id', $karyawan->id)
            ->where('kategori_izin', KategoriIzin::Lainnya->value)
            ->latest('dicatat_pada')->latest('id')->limit(5)->get();

        return response()->json([
            'tanggal' => $sekarang->toDateString(),
            'waktu_server' => $sekarang->toIso8601String(),
            'hari_ini' => KegiatanResource::collection($hariIni),
            'izin_lainnya' => KegiatanResource::collection($izinLainnya),
        ]);
    }

    public function standby(Request $request): JsonResponse
    {
        $data = $request->validate($this->aturanKunci());

        [$catatan, $baru] = $this->service->standby($request->user()->karyawan, $data['kunci']);

        return $this->balasan($catatan, $baru, 'Status Standby tercatat.');
    }

    public function order(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->aturanKunci(),
            'tujuan' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'akurasi' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'tujuan.required' => 'Lokasi tujuan pengantaran wajib diisi.',
            'foto.required' => 'Foto selfie wajib diambil.',
            'latitude.required' => 'Lokasi HP belum terbaca.',
            'longitude.required' => 'Lokasi HP belum terbaca.',
        ]);

        [$catatan, $baru] = $this->service->order(
            $request->user()->karyawan,
            $data['kunci'],
            $data['tujuan'],
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['akurasi']) ? (float) $data['akurasi'] : null,
            $request->file('foto'),
        );

        return $this->balasan($catatan, $baru, 'Order tercatat.');
    }

    public function izin(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->aturanKunci(),
            'kategori' => ['required', Rule::enum(KategoriIzin::class)],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'lampiran' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.(int) config('absensi.izin.maks_lampiran_kb', 5120)],
        ], [
            'lampiran.mimes' => 'SKD harus berupa foto (JPG/PNG) atau PDF.',
            'lampiran.max' => 'Ukuran SKD terlalu besar.',
        ]);

        $kategori = KategoriIzin::from($data['kategori']);

        [$catatan, $baru] = $this->service->izin(
            $request->user()->karyawan,
            $data['kunci'],
            $kategori,
            $data['alasan'] ?? null,
            $request->file('lampiran'),
        );

        $pesan = $kategori === KategoriIzin::Lainnya
            ? 'Pengajuan izin terkirim. Status: Menunggu Keputusan admin.'
            : 'Izin '.$kategori->label().' tercatat.';

        return $this->balasan($catatan, $baru, $pesan);
    }

    /** Riwayat per bulan: catatan alur baru, ditambah riwayat absensi lama (masuk/pulang) bila ada. */
    public function riwayat(Request $request): JsonResponse
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        $bulan = $request->input('bulan', $this->waktu->sekarang()->format('Y-m'));
        $awal = CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();
        $rentang = [$awal->toDateString(), $awal->endOfMonth()->toDateString()];
        $karyawan = $request->user()->karyawan;

        $baru = KegiatanAbsensi::with('pengganti')
            ->where('karyawan_id', $karyawan->id)
            ->whereDate('tanggal', '>=', $rentang[0])->whereDate('tanggal', '<=', $rentang[1])
            ->orderByDesc('dicatat_pada')->orderByDesc('id')->get();

        $lama = Absensi::where('karyawan_id', $karyawan->id)
            ->whereDate('tanggal', '>=', $rentang[0])->whereDate('tanggal', '<=', $rentang[1])
            ->orderByDesc('tanggal')->get();

        return response()->json([
            'bulan' => $bulan,
            'ringkasan' => $baru->groupBy(fn ($k) => $k->jenis->value)->map->count(),
            'data' => KegiatanResource::collection($baru),
            'absensi_lama' => AbsensiResource::collection($lama),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function aturanKunci(): array
    {
        return ['kunci' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/']];
    }

    private function balasan(KegiatanAbsensi $catatan, bool $baru, string $pesan): JsonResponse
    {
        $catatan->loadMissing('pengganti');

        return response()->json([
            'message' => $baru ? $pesan : 'Kiriman ini sudah tercatat sebelumnya (tidak dicatat dua kali).',
            'duplikat' => ! $baru,
            'kegiatan' => new KegiatanResource($catatan),
        ], $baru ? 201 : 200);
    }
}
