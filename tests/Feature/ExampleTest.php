<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Halaman utama (beranda) bisa dibuka. */
    public function test_halaman_utama_bisa_dibuka(): void
    {
        $this->get('/')->assertOk();
    }
}
