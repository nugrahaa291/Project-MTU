<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusAbsensi;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Services\AbsensiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsensiController extends Controller
{
    public function index(Request $request, AbsensiService $service): View
    {
        $hariIni = $service->sekarang()->toDateString();
        $dari = $request->input('dari', $hariIni);
        $sampai = $request->input('sampai', $hariIni);

        $absensi = Absensi::with('karyawan.jabatan')
            ->whereBetween('tanggal', [$dari, $sampai])
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

    /** Rekap bulanan per karyawan. */
    public function rekap(Request $request, AbsensiService $service): View
    {
        $bulan = $request->input('bulan', $service->sekarang()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $bulan), 404);

        $awal = CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();
        $akhir = $awal->endOfMonth();

        $hitung = Absensi::selectRaw('karyawan_id, status, count(*) as jumlah, sum(menit_terlambat) as menit')
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->groupBy('karyawan_id', 'status')
            ->get()
            ->groupBy('karyawan_id');

        $baris = Karyawan::orderBy('nama')->get(['id', 'nama', 'nip', 'aktif'])->map(function (Karyawan $k) use ($hitung) {
            $data = $hitung->get($k->id, collect())->keyBy(fn ($r) => $r->status->value);

            return [
                'karyawan' => $k,
                'jumlah' => collect(StatusAbsensi::cases())->mapWithKeys(
                    fn ($s) => [$s->value => (int) ($data->get($s->value)->jumlah ?? 0)]
                ),
                'menit_terlambat' => (int) ($data->get('terlambat')->menit ?? 0),
            ];
        })->filter(fn ($b) => $b['karyawan']->aktif || $b['jumlah']->sum() > 0)->values();

        return view('hrd.absensi.rekap', compact('baris', 'bulan', 'awal'));
    }

    /** HRD mencatat izin/sakit/cuti/alpa secara manual. */
    public function store(Request $request): RedirectResponse
    {
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
