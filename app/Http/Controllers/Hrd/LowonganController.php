<?php

namespace App\Http\Controllers\Hrd;

use App\Enums\StatusLowongan;
use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Lowongan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LowonganController extends Controller
{
    public function index(): View
    {
        $lowongan = Lowongan::denganJumlahDiterima()
            ->withCount('lamaran')
            ->with('jabatan')
            ->latest()
            ->paginate(15);

        return view('hrd.lowongan.index', compact('lowongan'));
    }

    public function create(): View
    {
        return view('hrd.lowongan.form', [
            'lowongan' => new Lowongan([
                'kuota' => 1,
                'status' => StatusLowongan::Dibuka,
                'tanggal_buka' => today(),
                'tanggal_tutup' => today()->addDays(30),
            ]),
            'jabatan' => $this->daftarJabatan(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lowongan = Lowongan::create($this->validasi($request));

        return redirect()->route('hrd.lowongan.show', $lowongan)->with('success', 'Lowongan dibuat.');
    }

    public function show(Lowongan $lowongan): View
    {
        $lowongan->load('jabatan')->loadCount('lamaran');

        return view('hrd.lowongan.show', compact('lowongan'));
    }

    public function edit(Lowongan $lowongan): View
    {
        return view('hrd.lowongan.form', [
            'lowongan' => $lowongan,
            'jabatan' => $this->daftarJabatan($lowongan->jabatan_id),
        ]);
    }

    public function update(Request $request, Lowongan $lowongan): RedirectResponse
    {
        $lowongan->update($this->validasi($request, $lowongan));

        return redirect()->route('hrd.lowongan.show', $lowongan)->with('success', 'Lowongan diperbarui.');
    }

    public function destroy(Lowongan $lowongan): RedirectResponse
    {
        if ($lowongan->lamaran()->exists()) {
            return back()->with('error', 'Lowongan sudah punya lamaran dan tidak bisa dihapus. Ubah statusnya menjadi Ditutup.');
        }

        $lowongan->delete();

        return redirect()->route('hrd.lowongan.index')->with('success', 'Lowongan dihapus.');
    }

    private function daftarJabatan(?int $sertakanId = null)
    {
        return Jabatan::where('aktif', true)->orWhere('id', $sertakanId)->orderBy('nama')->get();
    }

    private function validasi(Request $request, ?Lowongan $lowongan = null): array
    {
        // Kuota tidak boleh lebih kecil dari jumlah yang sudah diterima
        $kuotaMin = $lowongan ? max(1, $lowongan->jumlahDiterima()) : 1;

        return $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'jabatan_id' => ['required', 'exists:jabatan,id'],
            'lokasi' => ['required', 'string', 'max:100'],
            'kuota' => ['required', 'integer', "min:{$kuotaMin}", 'max:500'],
            'tanggal_buka' => ['required', 'date'],
            'tanggal_tutup' => ['required', 'date', 'after_or_equal:tanggal_buka'],
            'status' => ['required', Rule::enum(StatusLowongan::class)],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'persyaratan' => ['nullable', 'string', 'max:5000'],
        ], [
            'kuota.min' => 'Kuota tidak boleh kurang dari jumlah pelamar yang sudah diterima.',
        ]);
    }
}
