<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('jadwal_riwayat_perubahan')]
#[Fillable([
    'jadwal_id',
    'karyawan_id',
    'tanggal',
    'status_sebelumnya',
    'status_baru',
    'driver_pengganti_sebelumnya',
    'driver_pengganti_baru',
    'diubah_oleh',
    'alasan',
])]
class JadwalRiwayatPerubahan extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function driverPenggantiBaru(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'driver_pengganti_baru');
    }
}
