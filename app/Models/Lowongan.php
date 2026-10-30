<?php

namespace App\Models;

use App\Enums\StatusLamaran;
use App\Enums\StatusLowongan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('lowongan')]
#[Fillable(['judul', 'jabatan_id', 'lokasi', 'kuota', 'tanggal_buka', 'tanggal_tutup', 'status', 'deskripsi', 'persyaratan'])]
class Lowongan extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
            'status' => StatusLowongan::class,
            'kuota' => 'integer',
        ];
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function lamaran(): HasMany
    {
        return $this->hasMany(Lamaran::class);
    }

    /** Lowongan yang statusnya dibuka DAN hari ini berada dalam periode pendaftaran. */
    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', StatusLowongan::Dibuka->value)
            ->whereDate('tanggal_buka', '<=', today())
            ->whereDate('tanggal_tutup', '>=', today());
    }

    /** Menambahkan kolom virtual diterima_count (jumlah pelamar berstatus diterima). */
    public function scopeDenganJumlahDiterima(Builder $query): Builder
    {
        return $query->withCount([
            'lamaran as diterima_count' => fn ($q) => $q->where('status', StatusLamaran::Diterima->value),
        ]);
    }

    public function jumlahDiterima(): int
    {
        return $this->diterima_count
            ?? $this->lamaran()->where('status', StatusLamaran::Diterima->value)->count();
    }

    public function sisaKuota(): int
    {
        return max(0, $this->kuota - $this->jumlahDiterima());
    }

    public function isPenuh(): bool
    {
        return $this->sisaKuota() === 0;
    }

    public function dalamPeriode(): bool
    {
        return today()->betweenIncluded($this->tanggal_buka, $this->tanggal_tutup);
    }

    public function bisaDilamar(): bool
    {
        return $this->status === StatusLowongan::Dibuka
            && $this->dalamPeriode()
            && ! $this->isPenuh();
    }

    /** Label status yang ditampilkan ke manusia (gabungan status + periode + kuota). */
    public function labelStatus(): string
    {
        return match (true) {
            $this->status === StatusLowongan::Ditutup => 'Ditutup',
            $this->isPenuh() => 'Penuh',
            today()->lt($this->tanggal_buka) => 'Belum dibuka',
            today()->gt($this->tanggal_tutup) => 'Berakhir',
            default => 'Dibuka',
        };
    }
}
