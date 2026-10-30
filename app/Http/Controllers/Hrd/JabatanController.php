<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JabatanController extends Controller
{
    public function index(): View
    {
        $jabatan = Jabatan::withCount(['lowongan', 'karyawan'])->orderBy('nama')->get();

        return view('hrd.jabatan.index', compact('jabatan'));
    }

    public function create(): View
    {
        return view('hrd.jabatan.form', ['jabatan' => new Jabatan(['aktif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Jabatan::create($this->validasi($request));

        return redirect()->route('hrd.jabatan.index')->with('success', 'Jabatan ditambahkan.');
    }

    public function edit(Jabatan $jabatan): View
    {
        return view('hrd.jabatan.form', compact('jabatan'));
    }

    public function update(Request $request, Jabatan $jabatan): RedirectResponse
    {
        $jabatan->update($this->validasi($request, $jabatan));

        return redirect()->route('hrd.jabatan.index')->with('success', 'Jabatan diperbarui.');
    }

    public function destroy(Jabatan $jabatan): RedirectResponse
    {
        if ($jabatan->lowongan()->exists() || $jabatan->karyawan()->exists()) {
            return back()->with('error', 'Jabatan masih dipakai lowongan/karyawan. Nonaktifkan saja bila tidak dipakai lagi.');
        }

        $jabatan->delete();

        return redirect()->route('hrd.jabatan.index')->with('success', 'Jabatan dihapus.');
    }

    private function validasi(Request $request, ?Jabatan $jabatan = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('jabatan', 'nama')->ignore($jabatan)],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ]);

        // Checkbox yang tidak dicentang tidak dikirim browser, jadi dibaca lewat boolean()
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
