<?php

namespace App\Models;

use App\Enums\JenisKegiatan;
use App\Enums\KategoriIzin;
use App\Enums\StatusKegiatan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('kegiatan_absensi')]
#[Fillable([
    'karyawan_id', 'kunci', 'jenis', 'kategori_izin', 'tanggal', 'dicatat_pada', 'status',
    'tujuan', 'lat_aktual', 'lng_aktual', 'akurasi_meter', 'nama_lokasi', 'jarak_kantor_meter', 'foto',
    'alasan', 'lampiran', 'pengganti_id', 'diputuskan_oleh', 'diputuskan_pada', 'alasan_penolakan',
])]
class KegiatanAbsensi extends Model
{
    protected function casts(): array
    {
        return [
            'jenis' => JenisKegiatan::class,
            'kategori_izin' => KategoriIzin::class,
            'status' => StatusKegiatan::class,
            'tanggal' => 'date',
            'dicatat_pada' => 'datetime',
            'diputuskan_pada' => 'datetime',
            'lat_aktual' => 'float',
            'lng_aktual' => 'float',
            'akurasi_meter' => 'integer',
            'jarak_kantor_meter' => 'integer',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function pengganti(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'pengganti_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    /** Pengajuan izin "Lain-lain" yang masih menunggu keputusan admin. */
    public function scopeIzinMenunggu(Builder $query): Builder
    {
        return $query->where('jenis', JenisKegiatan::Izin->value)
            ->where('kategori_izin', KategoriIzin::Lainnya->value)
            ->where('status', StatusKegiatan::Menunggu->value);
    }

    /** Waktu resmi catatan (jam server) dalam zona waktu absensi (WIB). */
    public function waktuWib(): ?CarbonInterface
    {
        return $this->dicatat_pada?->copy()->timezone(config('absensi.timezone', 'Asia/Jakarta'));
    }
}
