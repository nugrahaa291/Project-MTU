<?php

namespace Tests\Unit;

use App\Support\Pin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PinTest extends TestCase
{
    #[DataProvider('pinBagus')]
    public function test_pin_yang_boleh(string $pin): void
    {
        $this->assertTrue(Pin::valid($pin));
    }

    #[DataProvider('pinBuruk')]
    public function test_pin_yang_ditolak(string $pin): void
    {
        $this->assertFalse(Pin::valid($pin));
    }

    public static function pinBagus(): array
    {
        return array_map(fn ($p) => [$p], ['482913', '739418', '102938', '050505x' === '' ? '' : '905173', '271828']);
    }

    public static function pinBuruk(): array
    {
        return array_map(fn ($p) => [$p], [
            '', '12345', '1234567', 'abcdef', '12 456', '000000', '111111', '999999',   // bentuk salah / angka sama
            '123456', '234567', '345678', '567890', '890123', '012345',                  // berurutan naik (menyambung 9 ke 0)
            '654321', '987654', '543210', '321098',                                      // berurutan turun
            '121212', '123123', '112112', '454545',                                      // blok berulang
        ]);
    }

    public function test_pin_acak_selalu_lolos_aturan(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $pin = Pin::acak();
            $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
            $this->assertTrue(Pin::valid($pin), "PIN acak $pin melanggar aturan");
        }
    }
}
