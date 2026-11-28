<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sidikPasswordKaryawan(): ?string
    {
        $user = $this->user;
        if (! $user?->isKaryawan() || ! $user->is_active || $user->must_change_password || ! $user->karyawan?->aktif) {
            return null;
        }

        return hash_hmac('sha256', $user->password, config('app.key'));
    }

    public function lamaran(): HasMany
    {
        return $this->hasMany(Lamaran::class);
    }
}
