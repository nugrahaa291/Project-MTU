<?php

namespace App\Enums;

enum JenisKegiatan: string
{
    case Standby = 'standby';
    case Order = 'order';
    case Izin = 'izin';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
