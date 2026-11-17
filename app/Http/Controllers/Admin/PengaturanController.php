<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    public function index(): View
    {
        $pengaturan = [
            'nama_perusahaan' => Pengaturan::ambil('nama_perusahaan', 'PT Multi Talent Universal (MTU)'),
            'lokasi_cabang' => Pengaturan::ambil('lokasi_cabang', 'Cabang Karawang / Operasional Wilayah'),
            'email_kontak' => Pengaturan::ambil('email_kontak', 'kontak@portal-pt.test'),
            'telepon' => Pengaturan::ambil('telepon', '0267-123456'),
            'alamat_kantor' => Pengaturan::ambil('alamat_kantor', 'Kawasan Industri KIIC, Karawang, Jawa Barat'),
            'status_kontak' => Pengaturan::ambil('status_kontak', 'Perlu Verifikasi Admin'),
            'toleransi_terlambat_menit' => Pengaturan::ambil('toleransi_terlambat_menit', '15'),
            'jam_masuk_mulai' => Pengaturan::ambil('jam_masuk_mulai', '07:00'),
            'jam_masuk_selesai' => Pengaturan::ambil('jam_masuk_selesai', '08:30'),
            'jam_pulang_mulai' => Pengaturan::ambil('jam_pulang_mulai', '17:00'),
            'min_driver_aktif' => Pengaturan::ambil('min_driver_aktif', '2'),
        ];

        return view('admin.pengaturan', compact('pengaturan'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_perusahaan' => ['required', 'string', 'max:100'],
            'lokasi_cabang' => ['nullable', 'string', 'max:100'],
            'email_kontak' => ['required', 'email', 'max:100'],
            'telepon' => ['required', 'string', 'max:30'],
            'alamat_kantor' => ['nullable', 'string', 'max:300'],
            'status_kontak' => ['nullable', 'string', 'max:50'],
            'toleransi_terlambat_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'jam_masuk_mulai' => ['required', 'date_format:H:i'],
            'jam_masuk_selesai' => ['required', 'date_format:H:i', 'after:jam_masuk_mulai'],
            'jam_pulang_mulai' => ['required', 'date_format:H:i', 'after:jam_masuk_selesai'],
            'min_driver_aktif' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'jam_masuk_selesai.after' => 'Jam batas akhir masuk harus setelah jam mulai masuk.',
            'jam_pulang_mulai.after' => 'Jam pulang harus setelah jam masuk selesai.',
        ]);

        foreach ($data as $kunci => $nilai) {
            $kategori = in_array($kunci, ['toleransi_terlambat_menit', 'jam_masuk_mulai', 'jam_masuk_selesai', 'jam_pulang_mulai', 'min_driver_aktif'])
                ? 'absensi'
                : 'umum';

            Pengaturan::simpan($kunci, (string) $nilai, $kategori);
        }

        ActivityLog::catat(
            $request->user(),
            'ubah_pengaturan',
            'Memperbarui konfigurasi operasional sistem'
        );

        return back()->with('success', 'Pengaturan sistem berhasil diperbarui.');
    }
}
