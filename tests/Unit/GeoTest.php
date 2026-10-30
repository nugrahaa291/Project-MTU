<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_titik_yang_sama_berjarak_nol(): void
    {
        $this->assertSame(0, Geo::jarakMeter(-6.3215, 107.3376, -6.3215, 107.3376));
    }

    public function test_satu_derajat_lintang_sekitar_111_km(): void
    {
        $jarak = Geo::jarakMeter(0, 100, 1, 100);

        $this->assertEqualsWithDelta(111195, $jarak, 100);
    }

    public function test_jarak_pendek_seratusan_meter(): void
    {
        // 0.001 derajat lintang ~ 111 m
        $this->assertEqualsWithDelta(111, Geo::jarakMeter(-6.3215, 107.3376, -6.3225, 107.3376), 2);
    }
}
