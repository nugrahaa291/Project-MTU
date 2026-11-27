<?php

return [

    // Zona waktu untuk semua perhitungan absensi (terlepas dari timezone aplikasi)
    'timezone' => env('ABSENSI_TIMEZONE', 'Asia/Jakarta'),

    // Titik kantor. Pada alur baru, jarak ke kantor hanya DICATAT sebagai informasi untuk Order
    // (driver boleh berada di luar radius). Radius dan jam di bawah masih dipakai alur lama (masuk/pulang).
    'kantor' => [
        'nama' => env('ABSENSI_KANTOR_NAMA', 'Kantor Pusat'),
        'latitude' => (float) env('ABSENSI_KANTOR_LAT', -6.3215),
        'longitude' => (float) env('ABSENSI_KANTOR_LNG', 107.3376),
        'radius_meter' => (int) env('ABSENSI_RADIUS_METER', 100),
    ],

    // ---- Alur lama (masuk/pulang), dipertahankan agar riwayat lama tetap terbaca ----
    'jam_masuk' => env('ABSENSI_JAM_MASUK', '08:00'),
    'toleransi_menit' => (int) env('ABSENSI_TOLERANSI_MENIT', 15),
    'hari_kerja' => [1, 2, 3, 4, 5],
    'maks_akurasi_meter' => (int) env('ABSENSI_MAKS_AKURASI', 100),

    // ---- Alur baru: Standby / Order / Izin ----
    'order' => [
        // Posisi dengan akurasi lebih buruk dari ini (meter) dianggap tidak bisa dipercaya.
        // Sengaja longgar: driver bisa sedang di perjalanan dengan sinyal GPS kurang baik.
        'maks_akurasi_meter' => (int) env('ABSENSI_ORDER_MAKS_AKURASI', 1000),
    ],

    'izin' => [
        'maks_lampiran_kb' => (int) env('ABSENSI_IZIN_MAKS_KB', 5120),
    ],

    // Nama lokasi untuk watermark diambil dari layanan geocoding (secara bawaan OpenStreetMap Nominatim).
    // Koordinat dikirim ke layanan itu. Matikan dengan ABSENSI_GEOCODER=false: watermark lalu memakai koordinat.
    'geocoder' => [
        'aktif' => filter_var(env('ABSENSI_GEOCODER', true), FILTER_VALIDATE_BOOL),
        'url' => env('ABSENSI_GEOCODER_URL', 'https://nominatim.openstreetmap.org/reverse'),
        // Kebijakan Nominatim mewajibkan User-Agent yang mengidentifikasi aplikasi. Isi kontak yang benar.
        'user_agent' => env('ABSENSI_GEOCODER_UA', 'PortalPT-Absensi/1.0 (admin@example.com)'),
        'timeout' => (int) env('ABSENSI_GEOCODER_TIMEOUT', 3),
    ],
];
