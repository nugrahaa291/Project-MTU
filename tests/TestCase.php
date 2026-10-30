<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pengujian tidak perlu membangun aset Vite (npm run build)
        $this->withoutVite();
    }
}
