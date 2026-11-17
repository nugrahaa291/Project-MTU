<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function catat(?User $user, string $action, string $description, ?array $properties = null): self
    {
        $req = request();

        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $req ? $req->ip() : null,
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 500) : null,
            'properties' => $properties,
        ]);
    }
}
