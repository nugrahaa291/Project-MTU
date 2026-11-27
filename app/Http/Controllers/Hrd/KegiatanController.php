<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\JenisKegiatan;
use App\Enums\StatusKegiatan;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KegiatanAbsensi;
use App\Services\AbsensiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Panel admin: semua catatan alur baru (Standby / Order / Izin). Hanya HRD (grup route role:hrd). */
class KegiatanController extends Controller
{
    public function index(Request $request, AbsensiService $waktu): View
    {
        $hariIni = $waktu->sekarang()->toDateString();
        $request->validate([
            'dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date'],
            'karyawan_id' => ['nullable', 'integer'],
            'jenis' => ['nullable', 'in:'.implode(',', array_column(JenisKegiatan::cases(), 'value'))],
            'status' => ['nullable', 'in:'.implode(',', array_column(StatusKegiatan::cases(), 'value'))],
        ]);
        $dari = $request->input('dari', $hariIni);
        $sampai = $request->input('sampai', $hariIni);

        $kegiatan = KegiatanAbsensi::with(['karyawan.jabatan', 'pengganti'])
            ->whereDate('tanggal', '>=', $dari)->whereDate('tanggal', '<=', $sampai)
            ->when($request->filled('karyawan_id'), fn ($q) => $q->where('karyawan_id', $request->karyawan_id))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('dicatat_pada')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('hrd.kegiatan.index', [
            'kegiatan' => $kegiatan,
            'karyawan' => Karyawan::orderBy('nama')->get(['id', 'nama']),
            'dari' => $dari,
            'sampai' => $sampai,
        ]);
    }

    /** Selfie ber-watermark (disk privat) - hanya bisa dilihat admin yang login. */
    public function foto(KegiatanAbsensi $kegiatan): StreamedResponse
    {
        return $this->kirimBerkas($kegiatan->foto);
    }

    /** Surat Keterangan Dokter (disk privat) - hanya bisa dilihat admin yang login. */
    public function lampiran(KegiatanAbsensi $kegiatan): StreamedResponse
    {
        return $this->kirimBerkas($kegiatan->lampiran);
    }

    private function kirimBerkas(?string $path): StreamedResponse
    {
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
