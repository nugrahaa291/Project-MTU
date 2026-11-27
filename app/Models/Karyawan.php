<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('karyawan')]
#[Fillable([
    'user_id', 'lamaran_id', 'jabatan_id', 'proyek', 'nip', 'nik', 'nama', 'no_hp', 'alamat',
    'tanggal_masuk', 'tanggal_keluar', 'aktif',
])]
class Karyawan extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'aktif' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function lamaran(): BelongsTo
    {
        return $this->belongsTo(Lamaran::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function jadwalDriver(): HasMany
    {
        return $this->hasMany(JadwalDriver::class, 'karyawan_id');
    }

    public function jadwalLibur(): HasMany
    {
        return $this->hasMany(JadwalDriver::class, 'karyawan_id')->where('status_jadwal', 'libur');
    }

    public function penugasanPengganti(): HasMany
    {
        return $this->hasMany(JadwalDriver::class, 'driver_pengganti_id');
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(KegiatanAbsensi::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
