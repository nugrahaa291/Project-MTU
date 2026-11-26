<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** Halaman utama (beranda) bisa dibuka. */
    public function test_halaman_utama_bisa_dibuka(): void
    {
        $this->get('/')->assertOk();
    }
}
