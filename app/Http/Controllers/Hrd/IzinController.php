<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\JenisKegiatan;
use App\Enums\KategoriIzin;
use App\Enums\StatusKegiatan;
use App\Exceptions\AbsensiException;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\KegiatanAbsensi;
use App\Services\KegiatanAbsensiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Keputusan admin atas pengajuan izin "Lain-lain". Seluruh grup route dibatasi role:hrd. */
class IzinController extends Controller
{
    public function __construct(private KegiatanAbsensiService $service)
    {
    }

    public function index(): View
    {
        $menunggu = KegiatanAbsensi::with('karyawan.jabatan')->izinMenunggu()->oldest('dicatat_pada')->get();

        // Untuk tiap pengajuan: siapa saja yang SEDANG izin pada tanggal itu (tidak boleh jadi pengganti)
        $sedangIzin = [];
        foreach ($menunggu->pluck('tanggal')->map->toDateString()->unique() as $tanggal) {
            $sedangIzin[$tanggal] = KegiatanAbsensi::where('jenis', JenisKegiatan::Izin->value)
                ->whereIn('status', [StatusKegiatan::Tercatat->value, StatusKegiatan::Disetujui->value])
                ->whereDate('tanggal', $tanggal)->pluck('karyawan_id')->all();
        }

        $diputuskan = KegiatanAbsensi::with(['karyawan', 'pengganti', 'pemutus'])
            ->where('jenis', JenisKegiatan::Izin->value)
            ->where('kategori_izin', KategoriIzin::Lainnya->value)
            ->whereIn('status', [StatusKegiatan::Disetujui->value, StatusKegiatan::Ditolak->value])
            ->latest('diputuskan_pada')->limit(20)->get();

        return view('hrd.izin.index', [
            'menunggu' => $menunggu,
            'diputuskan' => $diputuskan,
            'kandidat' => Karyawan::aktif()->orderBy('nama')->get(['id', 'nama', 'nip']),
            'sedangIzin' => $sedangIzin,
        ]);
    }

    public function setujui(Request $request, KegiatanAbsensi $kegiatan): RedirectResponse
    {
        // pengganti_id TIDAK dibuat "required" di sini agar pesan kesalahan berasal dari aturan bisnis yang sama di service
        $data = $request->validate(['pengganti_id' => ['nullable', 'integer']]);

        try {
            $pengganti = isset($data['pengganti_id']) ? Karyawan::find($data['pengganti_id']) : null;
            $this->service->setujui($kegiatan, $pengganti, $request->user());
        } catch (AbsensiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Izin disetujui dengan karyawan pengganti.');
    }

    public function tolak(Request $request, KegiatanAbsensi $kegiatan): RedirectResponse
    {
        $data = $request->validate(['alasan_penolakan' => ['nullable', 'string', 'max:500']]);

        try {
            $this->service->tolak($kegiatan, $data['alasan_penolakan'] ?? '', $request->user());
        } catch (AbsensiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan izin ditolak.');
    }
}
