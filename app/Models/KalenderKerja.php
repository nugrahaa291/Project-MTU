<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('kalender_kerja')]
#[Fillable(['tanggal', 'nama'])]
class KalenderKerja extends Model
{
    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }
}
