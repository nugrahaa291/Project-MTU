<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusLowongan;
use App\Models\Jabatan;
use App\Models\Lowongan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal untuk pengembangan. Aman dijalankan berulang (memakai firstOrCreate).
     */
    public function run(): void
    {
        // Akun Administrator utama
        $admin = User::firstOrNew(['email' => 'admin@portal-pt.test']);
        $admin->name = 'Admin';
        if (! $admin->exists) {
            $admin->password = 'password';
            $admin->forceFill([
                'role' => Role::Admin,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();
        } else {
            $admin->save();
        }

        // Akun HRD. Password awal: "password" - WAJIB diganti saat login pertama
        // (must_change_password = true). Ganti email di bawah ini sebelum dipakai sungguhan.
        $hrd = User::firstOrNew(['email' => 'hrd@portal-pt.test']);
        $hrd->name = 'HRD';
        if (! $hrd->exists) {
            $hrd->password = 'password';
            $hrd->forceFill([
                'role' => Role::Hrd,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();
        } else {
            $hrd->save();
        }

        foreach (['Staff Administrasi', 'Staff IT', 'Driver', 'Supervisor Operasional'] as $nama) {
            Jabatan::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        Lowongan::firstOrCreate(['judul' => 'Staff IT Support'], [
            'jabatan_id' => Jabatan::where('nama', 'Staff IT')->value('id'),
            'lokasi' => 'Karawang',
            'kuota' => 2,
            'tanggal_buka' => today(),
            'tanggal_tutup' => today()->addDays(30),
            'status' => StatusLowongan::Dibuka,
            'deskripsi' => "Mendukung kebutuhan teknis karyawan dan merawat jaringan kantor.",
            'persyaratan' => "Pendidikan minimal D3/S1 bidang IT\nPengalaman minimal 1 tahun\nMampu bekerja dalam tim",
        ]);

        \App\Models\Pengaturan::simpan('nama_perusahaan', 'PT Multi Talent Universal (MTU)', 'umum');
        \App\Models\Pengaturan::simpan('email_kontak', 'kontak@portal-pt.test', 'umum');
        \App\Models\Pengaturan::simpan('telepon', '0267-123456', 'umum');
        \App\Models\Pengaturan::simpan('toleransi_terlambat_menit', '15', 'absensi');
        \App\Models\Pengaturan::simpan('jam_masuk_mulai', '07:00', 'absensi');
        \App\Models\Pengaturan::simpan('jam_masuk_selesai', '08:30', 'absensi');
        \App\Models\Pengaturan::simpan('jam_pulang_mulai', '17:00', 'absensi');
    }
}
