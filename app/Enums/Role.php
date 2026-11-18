<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Hrd = 'hrd';
    case Karyawan = 'karyawan';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Hrd => 'HRD',
            self::Karyawan => 'Karyawan',
        };
    }
}
