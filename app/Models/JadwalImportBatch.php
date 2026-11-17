<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('jadwal_import_batch')]
#[Fillable([
    'nomor_batch',
    'nama_file',
    'periode_bulan',
    'periode_tahun',
    'format_import',
    'total_baris',
    'total_driver',
    'total_kerja',
    'total_libur',
    'created_by',
])]
class JadwalImportBatch extends Model
{
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalDriver::class, 'batch_id');
    }
}
