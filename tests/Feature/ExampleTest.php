<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Halaman utama mengarah ke portal lowongan kerja. */
    public function test_halaman_utama_mengarah_ke_daftar_lowongan(): void
    {
        $this->get('/')->assertRedirect('/lowongan');
    }
}
