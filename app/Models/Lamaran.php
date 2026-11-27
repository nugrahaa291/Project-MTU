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
    'kode', 'lowongan_id', 'pelamar_id', 'nik', 'nama', 'email', 'no_hp', 'jenis_kelamin', 'tempat_lahir',
    'tanggal_lahir', 'alamat', 'pendidikan',
    'cv', 'pas_foto', 'ktp', 'sim', 'kk', 'skck', 'domisili', 'paklaring',
    'ijazah', 'akte_lahir', 'buku_nikah', 'surat_lamaran',
    'status', 'catatan', 'diputuskan_pada',
])]
class Lamaran extends Model
{
    /** Kolom berkas => label tampilan. */
    public const DOKUMEN = [
        'cv' => 'CV',
        'pas_foto' => 'Pas Foto',
        'ktp' => 'KTP',
        'sim' => 'SIM',
        'kk' => 'KK (Kartu Keluarga)',
        'skck' => 'SKCK (aktif)',
        'domisili' => 'Surat Domisili',
        'paklaring' => 'Paklaring',
        'ijazah' => 'Ijazah',
        'akte_lahir' => 'Akte Lahir',
        'buku_nikah' => 'Buku Nikah',
        'surat_lamaran' => 'Surat Lamaran',
    ];
    

    /** Kolom data diri yang diisi pelamar pada tahap 1. */
    public const DATA_DIRI = [
        'nik', 'nama', 'email', 'no_hp', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'alamat', 'pendidikan',
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

        /**
     * Berkas yang ditampilkan di halaman HRD: yang sudah diunggah,
     * ditambah yang diminta paket lowongannya (agar terlihat bila kosong).
     */
    public function berkasTampil(): array
    {
        $diminta = array_keys($this->lowongan?->paketDokumen() ?? []);

        return array_filter(
            self::DOKUMEN,
            fn ($label, $kolom) => filled($this->{$kolom}) || in_array($kolom, $diminta, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** Menyeragamkan nomor HP: +62812.. / 62812.. / 0812.. => 0812.. */
    public static function normalisasiHp(string $hp): string
    {
        $angka = preg_replace('/\D/', '', $hp);

        return str_starts_with($angka, '62') ? '0'.substr($angka, 2) : $angka;
    }
}
