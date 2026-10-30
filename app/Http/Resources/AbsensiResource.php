<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Absensi */
class AbsensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tanggal' => $this->tanggal->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'jam_masuk' => $this->jam_masuk,
            'jam_pulang' => $this->jam_pulang,
            'menit_terlambat' => $this->menit_terlambat,
            'jarak_masuk_meter' => $this->jarak_masuk,
            'jarak_pulang_meter' => $this->jarak_pulang,
            'keterangan' => $this->keterangan,
        ];
    }
}
