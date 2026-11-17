<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            'Wingbox' => 'Armada Truk Wingbox untuk pengiriman kargo tertutup kapasitas besar.',
            'Trailer' => 'Armada Truk Trailer / Peti Kemas muatan berat pelabuhan & industri.',
            'Engkel (CDE)' => 'Armada Colt Diesel Engkel 4 roda distribusi perkotaan.',
            'CDD (Colt Diesel Double)' => 'Armada Colt Diesel Double 6 roda muatan kapasitas sedang.',
            'Fuso' => 'Armada Truk Fuso muatan berat antarkota.',
            'Tronton' => 'Armada Truk Tronton 3 sumbu muatan tonase besar.',
            'Logistik' => 'Unit Operasional, Pergudangan, dan Distribusi Kargo.',
            'Operasional Cabang' => 'Unit Operasional Pool dan Lapangan Cabang MTU.',
            'Staff Administrasi' => 'Unit Administrasi dan Keuangan Cabang.',
            'Staff IT' => 'Unit IT dan Sistem Informasi Cabang.',
            'Driver' => 'Unit Pengemudi dan Awak Armada MTU.',
            'Supervisor Operasional' => 'Unit Pengawas Operasional Armada dan Pengiriman.',
        ];

        foreach ($units as $nama => $deskripsi) {
            Jabatan::firstOrCreate(['nama' => $nama], [
                'deskripsi' => $deskripsi,
                'aktif' => true,
            ]);
        }
    }
}
