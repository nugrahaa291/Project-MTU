<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('proyek_kebutuhan_driver')]
#[Fillable([
    'nama_proyek',
    'min_driver_aktif',
    'keterangan',
])]
class ProyekKebutuhanDriver extends Model
{
    public static function kebutuhanProyek(string $namaProyek): int
    {
        $konfig = static::where('nama_proyek', $namaProyek)->first();
        if ($konfig) {
            return (int) $konfig->min_driver_aktif;
        }

        return (int) Pengaturan::ambil('min_driver_aktif', '2');
    }
}
