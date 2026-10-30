<?php

namespace App\Models;

use App\Enums\StatusLamaran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('lamaran')]
#[Fillable([
    'kode', 'lowongan_id', 'nik', 'nama', 'email', 'no_hp', 'jenis_kelamin', 'tempat_lahir',
    'tanggal_lahir', 'alamat', 'pendidikan', 'cv', 'pas_foto', 'ktp', 'ijazah',
    'status', 'catatan', 'diputuskan_pada',
])]
class Lamaran extends Model
{
    /** Kolom berkas => label tampilan. */
    public const DOKUMEN = [
        'cv' => 'CV',
        'pas_foto' => 'Pas Foto',
        'ktp' => 'KTP',
        'ijazah' => 'Ijazah',
    ];

    public const PENDIDIKAN = ['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'status' => StatusLamaran::class,
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function lowongan(): BelongsTo
    {
        return $this->belongsTo(Lowongan::class);
    }

    public function karyawan(): HasOne
    {
        return $this->hasOne(Karyawan::class);
    }

    /** Menyeragamkan nomor HP: +62812.. / 62812.. / 0812.. => 0812.. */
    public static function normalisasiHp(string $hp): string
    {
        $angka = preg_replace('/\D/', '', $hp);

        return str_starts_with($angka, '62') ? '0'.substr($angka, 2) : $angka;
    }
}
