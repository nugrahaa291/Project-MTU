<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Table('pelamar')]
#[Fillable(['nama', 'email', 'password'])]
class Pelamar extends Authenticatable
{
    protected $hidden = ['password', 'google_id'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function lamaran(): HasMany
    {
        return $this->hasMany(Lamaran::class);
    }
}
