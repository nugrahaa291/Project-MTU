<?php

namespace App\Enums;

enum KategoriIzin: string
{
    case Libur = 'libur';
    case Sakit = 'sakit';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Libur => 'Libur',
            self::Sakit => 'Sakit',
            self::Lainnya => 'Lain-lain',
        };
    }
}
