<?php

/*
|--------------------------------------------------------------------------
| Dokumen yang diminta saat melamar
|--------------------------------------------------------------------------
| 'berkas'    : aturan unggah tiap dokumen (format, ukuran maks. dalam KB, petunjuk).
| 'pendukung' : satu dokumen yang dipilih pelamar dari beberapa pilihan.
| 'paket'     : dokumen apa saja yang diminta tiap jenis lowongan.
|               true = wajib (tanda * merah), false = opsional.
*/

return [
    'berkas' => [
        'cv' => ['mimes' => ['pdf', 'doc', 'docx'], 'max' => 3072, 'hint' => 'PDF/DOC/DOCX, maks. 3 MB.'],
        'pas_foto' => ['mimes' => ['jpg', 'jpeg', 'png'], 'max' => 2048, 'gambar' => true, 'hint' => 'Pas foto terbaru. JPG/PNG, maks. 2 MB.'],
        'ktp' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 2048, 'hint' => 'JPG/PNG/PDF, maks. 2 MB.'],
        'sim' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 2048, 'hint' => 'JPG/PNG/PDF, maks. 2 MB.'],
        'kk' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 2048, 'hint' => 'Fotokopi/scan KK. JPG/PNG/PDF, maks. 2 MB.'],
        'skck' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 2048, 'hint' => 'Pastikan SKCK masih aktif. JPG/PNG/PDF, maks. 2 MB.'],
        'domisili' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 2048, 'hint' => 'Fotokopi/scan surat domisili. JPG/PNG/PDF, maks. 2 MB.'],
        'paklaring' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 2048, 'hint' => 'Surat keterangan pengalaman kerja. JPG/PNG/PDF, maks. 2 MB.'],
        'ijazah' => ['mimes' => ['jpg', 'jpeg', 'png', 'pdf'], 'max' => 3072, 'hint' => 'JPG/PNG/PDF, maks. 3 MB.'],
        'surat_lamaran' => ['mimes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], 'max' => 3072, 'hint' => 'PDF/DOC/DOCX/JPG/PNG, maks. 3 MB.'],
    ],

    'pendukung' => [
        'label' => 'Ijazah / Akte Lahir / Buku Nikah (pilih salah satu)',
        'pilihan' => ['ijazah', 'akte_lahir', 'buku_nikah'],
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
        'max' => 3072,
        'hint' => 'Cukup unggah satu dokumen. JPG/PNG/PDF, maks. 3 MB.',
    ],

        /*
    | Pilihan "Unit" di form lowongan HRD => paket dokumen yang dipakai.
    | Nama unit disimpan sebagai judul lowongan.
    */
    'unit' => [
        'Driver Trailer' => 'trailer',
        'Driver Wingbox' => 'wingbox',
        'Driver CDD' => 'cdd_fuso',
        'Driver Fuso' => 'cdd_fuso',
    ],

    'paket' => [
        'umum' => [
            'label' => 'Umum (CV, Pas Foto, KTP)',
            'berkas' => ['cv' => true, 'pas_foto' => true, 'ktp' => true, 'ijazah' => false],
        ],
        'trailer' => [
            'label' => 'Driver Trailer',
            'berkas' => [
                'pas_foto' => true, 'ktp' => true, 'sim' => true, 'kk' => true, 'skck' => true,
                'domisili' => true, 'paklaring' => false, 'pendukung' => true, 'surat_lamaran' => true,
            ],
        ],
        'wingbox' => [
            'label' => 'Driver Wingbox',
            'berkas' => [
                'pas_foto' => true, 'ktp' => true, 'sim' => true, 'kk' => true, 'skck' => true,
                'domisili' => true, 'paklaring' => true, 'pendukung' => true, 'surat_lamaran' => true,
            ],
        ],
        'cdd_fuso' => [
            'label' => 'Driver CDD / Fuso',
            'berkas' => [
                'pas_foto' => true, 'ktp' => true, 'sim' => true, 'kk' => true, 'skck' => true,
                'domisili' => true, 'paklaring' => true, 'pendukung' => true, 'surat_lamaran' => true,
            ],
        ],
    ],
];
