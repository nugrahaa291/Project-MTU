<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\KalenderKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KalenderKerjaController extends Controller
{
    public function index(Request $request): View
    {
        $tahun = (int) $request->input('tahun', now()->year);

        return view('hrd.kalender.index', [
            'libur' => KalenderKerja::whereYear('tanggal', $tahun)->orderBy('tanggal')->get(),
            'tahun' => $tahun,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengelola hari libur.');
        }

        $data = $request->validate([
            'tanggal' => ['required', 'date', Rule::unique('kalender_kerja', 'tanggal')],
            'nama' => ['required', 'string', 'max:100'],
        ], ['tanggal.unique' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.']);

        KalenderKerja::create($data);

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function destroy(Request $request, KalenderKerja $kalender): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Admin yang dapat mengelola hari libur.');
        }

        $kalender->delete();

        return back()->with('success', 'Hari libur dihapus.');
    }
}
