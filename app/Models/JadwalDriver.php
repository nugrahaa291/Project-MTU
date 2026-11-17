<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('jadwal_driver')]
#[Fillable([
    'karyawan_id',
    'tanggal',
    'status_jadwal',
    'sumber',
    'batch_id',
    'proyek',
    'driver_pengganti_id',
    'status_verifikasi',
    'alasan_pengecualian',
    'keterangan',
    'created_by',
])]
class JadwalDriver extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id');
    }

    public function driverPengganti(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'driver_pengganti_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JadwalImportBatch::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function riwayatPerubahan(): HasMany
    {
        return $this->hasMany(JadwalRiwayatPerubahan::class, 'jadwal_id');
    }

    public function scopeLibur(Builder $query): Builder
    {
        return $query->where('status_jadwal', 'libur');
    }

    public function scopeKerja(Builder $query): Builder
    {
        return $query->where('status_jadwal', 'kerja');
    }
}
