<?php

namespace App\Enums;

enum StatusLamaran: string
{
    case Diajukan = 'diajukan';
    case Seleksi = 'seleksi';
    case Diterima = 'diterima'; // = Hired
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Seleksi => 'Dalam Seleksi',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }

    /** Warna lencana (badge) di tampilan. */
    public function warna(): string
    {
        return match ($this) {
            self::Diajukan => 'slate',
            self::Seleksi => 'amber',
            self::Diterima => 'green',
            self::Ditolak => 'red',
        };
    }

    /** Alur yang diizinkan: diterima & ditolak adalah status akhir. */
    public function bisaPindahKe(self $tujuan): bool
    {
        return match ($this) {
            self::Diajukan => in_array($tujuan, [self::Seleksi, self::Ditolak], true),
            self::Seleksi => in_array($tujuan, [self::Diterima, self::Ditolak], true),
            self::Diterima, self::Ditolak => false,
        };
    }
}
