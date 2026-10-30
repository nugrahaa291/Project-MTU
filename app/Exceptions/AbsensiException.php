<?php

namespace App\Exceptions;

use RuntimeException;

/** Absensi ditolak karena melanggar aturan (di luar radius, absen ganda, dsb). */
class AbsensiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $kode = 'absensi_ditolak',
    ) {
        parent::__construct($message);
    }
}
