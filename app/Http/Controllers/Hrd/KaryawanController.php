<?php

namespace App\Http\Controllers\Hrd;

use App\Exceptions\RekrutmenException;
use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Services\KaryawanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KaryawanController extends Controller
{
    public function __construct(private KaryawanService $service)
    {
    }

    public function index(Request $request): View
    {
        $karyawan = Karyawan::with(['jabatan', 'user'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $kata)->orWhere('nip', 'like', $kata)->orWhere('nik', 'like', $kata));
            })
            ->when($request->filled('aktif'), fn ($q) => $q->where('aktif', $request->boolean('aktif')))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('hrd.karyawan.index', compact('karyawan'));
    }

    /** Tambah karyawan manual (untuk pegawai lama yang tidak lewat portal lamaran). */
    public function create(): View
    {
        return view('hrd.karyawan.create', [
            'jabatan' => Jabatan::where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'nik' => ['required', 'digits:16', Rule::unique('karyawan', 'nik')],
            'no_hp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'jabatan_id' => ['required', Rule::exists('jabatan', 'id')->where('aktif', true)],
            'tanggal_masuk' => ['required', 'date'],
        ], ['nik.digits' => 'NIK harus 16 digit angka.']);

        $data['no_hp'] = \App\Models\Lamaran::normalisasiHp($data['no_hp']);

        try {
            [$karyawan, $password] = $this->service->buat($data);
        } catch (RekrutmenException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('hrd.karyawan.show', $karyawan)
            ->with('success', 'Karyawan ditambahkan.')
            ->with('password_sementara', $password);
    }

    public function show(Karyawan $karyawan): View
    {
        $karyawan->load(['jabatan', 'user', 'lamaran.lowongan']);
        $absensi = $karyawan->absensi()->latest('tanggal')->limit(10)->get();

        return view('hrd.karyawan.show', compact('karyawan', 'absensi'));
    }

    public function edit(Karyawan $karyawan): View
    {
        return view('hrd.karyawan.edit', [
            'karyawan' => $karyawan->load('user'),
            'jabatan' => Jabatan::where('aktif', true)->orWhere('id', $karyawan->jabatan_id)->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'jabatan_id' => ['required', 'exists:jabatan,id'],
        ]);
        $data['no_hp'] = \App\Models\Lamaran::normalisasiHp($data['no_hp']);

        $karyawan->update($data);
        $karyawan->user->update(['name' => $data['nama']]);

        // Status aktif ditangani lewat service karena ikut mengubah akun login & token
        $aktif = $request->boolean('aktif');
        if ($aktif !== $karyawan->aktif) {
            $this->service->setAktif($karyawan, $aktif);
        }

        return redirect()->route('hrd.karyawan.show', $karyawan)->with('success', 'Data karyawan diperbarui.');
    }

    public function resetPassword(Karyawan $karyawan): RedirectResponse
    {
        $password = $this->service->resetPassword($karyawan);

        return redirect()->route('hrd.karyawan.show', $karyawan)
            ->with('success', 'Password direset. Karyawan wajib menggantinya saat login berikutnya.')
            ->with('password_sementara', $password);
    }
}
