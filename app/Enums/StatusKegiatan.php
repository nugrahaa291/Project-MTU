<?php

namespace App\Enums;

enum StatusKegiatan: string
{
    case Tercatat = 'tercatat';
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Tercatat => 'Tercatat',
            self::Menunggu => 'Menunggu Keputusan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Tercatat => 'green',
            self::Menunggu => 'amber',
            self::Disetujui => 'blue',
            self::Ditolak => 'red',
        };
    }
}
