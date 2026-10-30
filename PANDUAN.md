# Portal PT - Rekrutmen & Absensi (Fase 1-4)

Dibangun di atas template Laravel 13 milikmu. Tampilan (font Hind, koral `#f8694a`, gelap `#30323a`, latar `#f5f6f8`)
mengikuti proyek MTU. Logika yang dipakai hanya yang masuk akal; yang bermasalah diperbaiki (lihat bagian akhir).

## 1. Pasang (urutan penting)

```bash
# 1) Timpa file proyekmu dengan isi zip ini (folder app, config, database, resources, routes, tests, bootstrap, dll)

# 2) Pasang Sanctum (dipakai API mobile)
composer require laravel/sanctum

# 3) Salin pengaturan baru dari .env.example ke .env milikmu:
#    APP_NAME, APP_LOCALE=id, dan blok ABSENSI_* (koordinat kantor WAJIB disesuaikan)

# 4) Database & akun HRD pertama
php artisan migrate --seed

# 5) Tampilan
npm install
npm run dev        # atau: npm run build

# 6) Jalankan
php artisan serve
php artisan schedule:work   # terminal lain: menandai ALPA otomatis tiap 23:30 WIB
```

Login HRD pertama: `hrd@portal-pt.test` / `password`. Sistem langsung memaksa ganti password.
**Ganti email ini** di `database/seeders/DatabaseSeeder.php` sebelum dipakai sungguhan.

Bila sebelumnya kamu sudah membuat model/migrasi `JobVacancy` (percobaan awal), hapus
`app/Models/JobVacancy.php` dan file migrasinya; tabel itu tidak dipakai lagi.

Cek semuanya: `php artisan test` (50 pengujian PHP) dan `node --test tests/pwa/*.test.mjs` (25 pengujian aplikasi absensi; uji layar butuh `npm i -D jsdom`, tanpa itu dilewati).

## 2. Alur sistem

| Fase | Halaman / endpoint | Isi |
|---|---|---|
| 1 | `/lowongan`, `/lowongan/{id}`, `/lowongan/{id}/lamar`, `/cek-status` | Portal publik: daftar lowongan, form lamaran + upload CV/foto/KTP/ijazah, kode lamaran, cek status |
| 2 | `/login`, `/hrd/...` | Dashboard HRD: CRUD lowongan & jabatan, tinjau lamaran, ubah status, lihat berkas, kalender libur, absensi & rekap |
| 3 | tombol **Terima & Buat Akun** pada lamaran berstatus Seleksi | Status Diterima (Hired) otomatis membuat akun login + data karyawan + NIP, dengan password sementara (tampil sekali) |
| 4 | `/api/...` | API mobile absensi GPS + selfie (Sanctum) |

Status lamaran: `diajukan -> seleksi -> diterima | ditolak` (diterima/ditolak bersifat final).

## 3. Kontrak API mobile

Semua balasan JSON. Kirim header `Accept: application/json`. Setelah login: `Authorization: Bearer <token>`.

| Method & URL | Keterangan |
|---|---|
| `POST /api/login` | body: `email`, `password`, `device_name` -> `token`, `must_change_password`, data karyawan. Token berlaku 30 hari; satu token per nama perangkat |
| `PUT /api/password` | `password_lama`, `password`, `password_confirmation`. Wajib dipanggil bila `must_change_password = true` |
| `GET /api/me` / `POST /api/logout` | profil / keluar |
| `GET /api/absensi/hari-ini` | koordinat & radius kantor, jam masuk, `bisa_masuk`, `bisa_pulang`, catatan hari ini |
| `POST /api/absensi/masuk` | multipart: `foto` (jpg/png, maks 5 MB), `latitude`, `longitude`, opsional `akurasi` (meter), `lokasi_palsu` (isMock Android) |
| `POST /api/absensi/pulang` | sama dengan masuk |
| `GET /api/absensi?bulan=2026-10` | riwayat bulanan + ringkasan |

Penolakan memakai kode HTTP + `kode` mesin: `di_luar_radius` (422), `akurasi_rendah` (422), `lokasi_palsu` (422),
`bukan_hari_kerja` (422), `sudah_absen` (409), `belum_masuk` (409), `sudah_pulang` (409),
`wajib_ganti_password` (403), `akun_nonaktif` (403).


## 3b. Aplikasi absensi karyawan (PWA) - buka `/absen`

Aplikasi HP berupa PWA yang disajikan Laravel sendiri (satu server, tanpa CORS). Karyawan membuka
`https://domain-kamu/absen`, login dengan akun yang diberikan HRD, lalu bisa **dipasang ke layar utama**
(Android: tombol "Pasang di layar utama" di tab Akun; iPhone: Safari > Bagikan > Tambah ke Layar Utama).

Layar: login -> (ganti password sementara) -> Beranda (jam WIB mengikuti server, jarak langsung ke kantor,
tombol Absen Masuk/Pulang -> kamera selfie -> kirim), Riwayat per bulan, Akun.

**Wajib HTTPS.** Browser hanya mengizinkan kamera dan GPS di situs HTTPS (atau `localhost`). Karena itu:
- Produksi: pasang SSL (Let's Encrypt / Cloudflare).
- Mencoba dari HP saat pengembangan: buka terowongan HTTPS ke komputermu, mis. `cloudflared tunnel --url http://localhost:8000`
  atau `ngrok http 8000`, lalu buka alamat https yang diberikan di HP.
- Hanya mencoba dari komputer: `http://localhost:8000/absen` sudah cukup.

Desain yang disengaja:
- Absen **tidak bisa dilakukan offline**. Jam dan jarak diverifikasi server; antrean offline akan membuka celah memalsukan jam.
  Halaman tetap terbuka saat offline, tetapi tombol absen dikunci dan ada pemberitahuan.
- Jam di layar mengikuti jam server, bukan jam HP. Aplikasi tidak mengirim jam sama sekali.
- Selfie diambil langsung dari kamera depan (bukan dari galeri), diperkecil sisi terpanjang 1024 px sebelum dikirim.
- Token login disimpan di penyimpanan browser; halaman memakai Content-Security-Policy yang hanya mengizinkan skrip dari
  domain sendiri dan tidak memuat skrip pihak ketiga. Semua teks dari server dirender sebagai teks (bukan HTML).
- Service worker hanya menyimpan kerangka aplikasi; permintaan `/api/*` tidak pernah disentuh/di-cache.

Batasan jujur versi web:
- Browser **tidak bisa mendeteksi lokasi palsu** (aplikasi native bisa lewat `isMock`). Karena itu `lokasi_palsu` tidak dikirim;
  pertahanan yang tersisa adalah radius, batas akurasi GPS, selfie langsung dari kamera, dan audit foto oleh HRD.
  Bila kecurangan lokasi menjadi masalah nyata, aplikasi native adalah langkah berikutnya.
- Izin lokasi/kamera harus diberikan karyawan; bila ditolak, aplikasi menampilkan cara memperbaikinya.
- Belum diuji di perangkat dan browser sungguhan (Chrome Android, Safari iOS): lihat catatan pengujian di bawah.

## 4. Aturan absensi (config/absensi.php)

- Absen hanya di radius kantor (default 100 m, Haversine), akurasi GPS maks 100 m.
- Terlambat bila lewat jam masuk + toleransi (default 08:00 + 15 menit); menit telat dihitung dari 08:00.
- Hari kerja Senin-Jumat (atur di config); libur nasional dicatat di menu **Hari Libur**.
- Satu karyawan satu catatan per hari (dijaga indeks unik database, aman dari klik ganda).
- Tidak ada catatan pada hari kerja => `alpa` otomatis (`php artisan absensi:tandai-alpa [tanggal]`).
- HRD mencatat izin/sakit/cuti/alpa manual dari halaman Absensi.
- Selfie disimpan privat (`storage/app/private`), hanya HRD yang bisa melihat.

Batasan jujur: server tidak bisa membuktikan GPS asli. Flag `lokasi_palsu` dikirim oleh aplikasi
(bisa dilewati perangkat yang di-root). Selfie + jarak tersimpan agar HRD bisa mengaudit.

## 5. Dari MTU: yang dipakai, diubah, dibuang

Dipakai: lowongan dengan kuota & periode, larangan melamar dua kali, validasi tipe/ukuran berkas, alur status lamaran,
data karyawan (NIK, jabatan, tanggal masuk/keluar), kalender libur, satu absensi per hari, pengecekan akun aktif.

Diperbaiki:
- Route terima/tolak/proses lamaran di MTU **tanpa login** (siapa pun bisa menerima/menolak) -> kini hanya HRD.
- Berkas KTP/SKCK dll. disimpan di disk **publik** -> kini privat, diunduh lewat route ber-login.
- Status "penuh" diubah di database saat halaman form dibuka (GET) -> kini dihitung dari kuota dan dikunci saat menerima.
- Status tersimpan ganda (`pelamar.status` dan `lamaran.status`) dan data pelamar saling menimpa -> satu status per lamaran.
- Cek status hanya dengan nama (LIKE) -> kini kode lamaran + nomor HP.
- Pelamar diterima tidak otomatis jadi karyawan -> kini otomatis.

Dibuang (spesifik bisnis lain / belum bermakna): toko online (produk, order, Midtrans, RajaOngkir, login Google),
jenis armada / no. lambung / status order-pool, 8 berkas wajib (SIM, SKCK, paklaring, ...), rekap HK berbasis order,
tabel gaji (tanpa kode di MTU), role enum '0'-'3' (tak jelas artinya).
