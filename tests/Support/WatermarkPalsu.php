<?php

namespace Tests\Support;

use App\Support\WatermarkFoto;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pengganti WatermarkFoto untuk pengujian Laravel: mencatat teks yang diminta dan mengembalikan JPEG kecil.
 * Watermark SUNGGUHAN (GD) diuji terpisah di tests/Unit/WatermarkFotoTest.
 */
class WatermarkPalsu extends WatermarkFoto
{
    /** @var list<array{waktu:string,tanggal:string,lokasi:string}> */
    public array $panggilan = [];

    public ?string $gagal = null; // 'gd' | 'foto'

    public function __construct()
    {
        parent::__construct(sys_get_temp_dir());
    }

    public function buat(string $pathAsli, string $teksWaktu, string $teksTanggal, string $teksLokasi): string
    {
        if ($this->gagal === 'gd') {
            throw new RuntimeException('Ekstensi GD tidak aktif di PHP.');
        }
        if ($this->gagal === 'foto') {
            throw new InvalidArgumentException('Berkas bukan gambar JPEG/PNG yang valid.');
        }

        $this->panggilan[] = ['waktu' => $teksWaktu, 'tanggal' => $teksTanggal, 'lokasi' => $teksLokasi];

        return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAAQABADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDPooor3Tyj/9k=');
    }
}
