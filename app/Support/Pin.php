<?php

namespace App\Support;

/** Aturan PIN karyawan: 6 digit angka, dan bukan pola yang mudah ditebak. */
final class Pin
{
    public const PANJANG = 6;

    public static function valid(string $pin): bool
    {
        if (! preg_match('/^\d{'.self::PANJANG.'}$/', $pin)) {
            return false;
        }

        // 111111, 121212, 123123, 112112 (satu blok 1-3 digit yang berulang)
        if (preg_match('/^(\d{1,3})\1+$/', $pin)) {
            return false;
        }

        // Urutan naik/turun: 123456, 654321, 345678, 890123 (menyambung 9 -> 0)
        $naik = $turun = true;
        for ($i = 1; $i < self::PANJANG; $i++) {
            $selisih = ((int) $pin[$i] - (int) $pin[$i - 1] + 10) % 10;
            $naik = $naik && $selisih === 1;
            $turun = $turun && $selisih === 9;
        }

        return ! ($naik || $turun);
    }

    /** PIN acak yang lolos aturan di atas (dipakai sebagai PIN sementara). */
    public static function acak(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), self::PANJANG, '0', STR_PAD_LEFT);
        } while (! self::valid($pin));

        return $pin;
    }
}
