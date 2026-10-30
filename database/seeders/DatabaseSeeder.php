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
        // Akun HRD pertama. Password awal: "password" - WAJIB diganti saat login pertama
        // (must_change_password = true). Ganti email di bawah ini sebelum dipakai sungguhan.
        $hrd = User::firstOrNew(['email' => 'hrd@portal-pt.test']);
        if (! $hrd->exists) {
            $hrd->name = 'Admin HRD';
            $hrd->password = 'password';
            $hrd->forceFill([
                'role' => Role::Hrd,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();
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
    }
}
