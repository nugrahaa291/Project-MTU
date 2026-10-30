<?php

namespace App\Models;

use App\Enums\StatusAbsensi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('absensi')]
#[Fillable([
    'karyawan_id', 'tanggal', 'status', 'menit_terlambat',
    'jam_masuk', 'lat_masuk', 'lng_masuk', 'jarak_masuk', 'foto_masuk',
    'jam_pulang', 'lat_pulang', 'lng_pulang', 'jarak_pulang', 'foto_pulang',
    'keterangan', 'dicatat_oleh',
])]
class Absensi extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusAbsensi::class,
            'menit_terlambat' => 'integer',
            'lat_masuk' => 'float',
            'lng_masuk' => 'float',
            'lat_pulang' => 'float',
            'lng_pulang' => 'float',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
