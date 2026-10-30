<?php

namespace App\Enums;

enum StatusLowongan: string
{
    case Dibuka = 'dibuka';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
