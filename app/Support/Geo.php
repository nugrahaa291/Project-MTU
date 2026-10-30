<?php

namespace App\Support;

class Geo
{
    /**
     * Jarak dua koordinat dalam meter (rumus Haversine).
     * Bumi dianggap bola dengan jari-jari 6.371.000 m - cukup akurat untuk radius absensi.
     */
    public static function jarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $radius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($radius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
