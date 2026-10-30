<?php

namespace App\Enums;

enum StatusAbsensi: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case Alpa = 'alpa';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function warna(): string
    {
        return match ($this) {
            self::Hadir => 'green',
            self::Terlambat => 'amber',
            self::Izin, self::Sakit, self::Cuti => 'blue',
            self::Alpa => 'red',
        };
    }

    /** Status yang boleh dicatat manual oleh HRD. */
    public static function manual(): array
    {
        return [self::Izin, self::Sakit, self::Cuti, self::Alpa];
    }
}
