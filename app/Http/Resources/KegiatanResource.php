<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\KegiatanAbsensi */
class KegiatanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $waktu = $this->waktuWib();

        return [
            'id' => $this->id,
            'jenis' => $this->jenis->value,
            'jenis_label' => $this->jenis->label(),
            'kategori_izin' => $this->kategori_izin?->value,
            'kategori_label' => $this->kategori_izin?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'tanggal' => $this->tanggal->toDateString(),
            'waktu' => $waktu?->toIso8601String(),   // jam resmi = jam SERVER, WIB
            'jam' => $waktu?->format('H:i:s'),
            'tujuan' => $this->tujuan,
            'nama_lokasi' => $this->nama_lokasi,
            'lokasi_aktual' => $this->lat_aktual !== null
                ? ['latitude' => $this->lat_aktual, 'longitude' => $this->lng_aktual, 'akurasi_meter' => $this->akurasi_meter]
                : null,
            'jarak_kantor_meter' => $this->jarak_kantor_meter,
            'alasan' => $this->alasan,
            'ada_lampiran' => $this->lampiran !== null,
            'alasan_penolakan' => $this->alasan_penolakan,
            'pengganti' => $this->whenLoaded('pengganti', fn () => $this->pengganti?->nama),
            'diputuskan_pada' => $this->diputuskan_pada?->copy()->timezone(config('absensi.timezone', 'Asia/Jakarta'))->toIso8601String(),
        ];
    }
}
