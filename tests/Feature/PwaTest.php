<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_halaman_absen_publik_dengan_header_keamanan(): void
    {
        $response = $this->get('/absen')->assertOk()
            ->assertSee('/absen/manifest.webmanifest', false)
            ->assertSee('/pwa/app.js', false);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));
    }

    public function test_service_worker_boleh_mengelola_scope_absen(): void
    {
        $response = $this->get('/absen/sw.js')->assertOk();

        $this->assertSame('/absen', $response->headers->get('Service-Worker-Allowed'));
        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type'));
    }

    public function test_manifest_valid_dan_semua_ikon_ada(): void
    {
        $response = $this->get('/absen/manifest.webmanifest')->assertOk();
        $this->assertStringContainsString('manifest+json', $response->headers->get('Content-Type'));

        $manifest = $response->json();
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/absen', $manifest['start_url']);
        $this->assertStringStartsWith($manifest['scope'], $manifest['start_url']);

        foreach ($manifest['icons'] as $ikon) {
            $this->assertFileExists(public_path($ikon['src']));
        }
        $ukuran = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $ukuran);
        $this->assertContains('512x512', $ukuran);
    }

    public function test_berkas_aplikasi_tersedia(): void
    {
        foreach (['app.js', 'lib.js', 'api.js', 'sw.js', 'style.css', 'icons/apple-touch-icon.png'] as $berkas) {
            $this->assertFileExists(public_path("pwa/$berkas"));
        }
    }

    public function test_validasi_berbahasa_indonesia(): void
    {
        app()->setLocale('id');

        $this->postJson('/api/login', [])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email wajib diisi.');
    }
}
