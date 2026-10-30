<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('jabatan')]
#[Fillable(['nama', 'deskripsi', 'aktif'])]
class Jabatan extends Model
{
    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function lowongan(): HasMany
    {
        return $this->hasMany(Lowongan::class);
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
