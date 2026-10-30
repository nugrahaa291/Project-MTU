<?php

namespace App\Enums;

enum Role: string
{
    case Hrd = 'hrd';
    case Karyawan = 'karyawan';

    public function label(): string
    {
        return match ($this) {
            self::Hrd => 'HRD',
            self::Karyawan => 'Karyawan',
        };
    }
}
