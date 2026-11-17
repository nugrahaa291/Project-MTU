<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $fillable = [
        'kunci',
        'nilai',
        'kategori',
    ];

    public static function ambil(string $kunci, ?string $default = null): ?string
    {
        $baris = static::where('kunci', $kunci)->first();

        return $baris?->nilai ?? $default;
    }

    public static function simpan(string $kunci, ?string $nilai, string $kategori = 'umum'): void
    {
        static::updateOrCreate(
            ['kunci' => $kunci],
            ['nilai' => $nilai, 'kategori' => $kategori]
        );
    }
}
