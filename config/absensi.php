<?php

return [

    // Zona waktu untuk semua perhitungan absensi (terlepas dari timezone aplikasi)
    'timezone' => env('ABSENSI_TIMEZONE', 'Asia/Jakarta'),

    // Titik kantor + radius yang diperbolehkan untuk absen (meter).
    // GANTI koordinat ini dengan lokasi kantor sebenarnya (cek di Google Maps: klik kanan > salin koordinat).
    'kantor' => [
        'nama' => env('ABSENSI_KANTOR_NAMA', 'Kantor Pusat'),
        'latitude' => (float) env('ABSENSI_KANTOR_LAT', -6.3215),
        'longitude' => (float) env('ABSENSI_KANTOR_LNG', 107.3376),
        'radius_meter' => (int) env('ABSENSI_RADIUS_METER', 100),
    ],

    // Jam kerja
    'jam_masuk' => env('ABSENSI_JAM_MASUK', '08:00'),
    'toleransi_menit' => (int) env('ABSENSI_TOLERANSI_MENIT', 15),

    // Hari kerja (ISO: 1 = Senin ... 7 = Minggu). Hari libur nasional dicatat di tabel kalender_kerja.
    'hari_kerja' => [1, 2, 3, 4, 5],

    // Lokasi dengan akurasi GPS lebih buruk dari ini (meter) ditolak, karena posisinya tidak bisa dipercaya
    'maks_akurasi_meter' => (int) env('ABSENSI_MAKS_AKURASI', 100),
];
