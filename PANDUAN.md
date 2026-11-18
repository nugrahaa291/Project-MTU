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

## 3. Kontrak API mobile (alur absensi baru)

Semua balasan JSON. Kirim header `Accept: application/json`. Setelah login: `Authorization: Bearer <token>`.
Identitas karyawan SELALU dari token, dan waktu resmi SELALU dari jam server (WIB).

| Method & URL | Keterangan |
|---|---|
| `POST /api/login` | body: `nip`, `pin`, `device_name` -> `token`, `must_change_pin`, data karyawan. Dibatasi 5 salah-PIN per NIP+IP per 5 menit dan 20 per NIP per jam |
| `PUT /api/pin` | `pin_lama`, `pin`, `pin_confirmation`. PIN = 6 digit angka, bukan angka berulang/berurutan. Wajib dipanggil bila `must_change_pin = true` |
| `GET /api/me` / `POST /api/logout` | profil / keluar |
| `GET /api/aktivitas/ringkasan` | catatan hari ini + 5 pengajuan izin Lain-lain terakhir (status, pengganti, alasan penolakan) |
| `POST /api/aktivitas/standby` | JSON: `kunci`. Tanpa lokasi/selfie/keterangan |
| `POST /api/aktivitas/order` | multipart: `kunci`, `tujuan`, `latitude`, `longitude`, `akurasi`, `foto` (JPG/PNG maks 5 MB). Semua wajib; di luar radius kantor tetap diterima |
| `POST /api/aktivitas/izin` | multipart: `kunci`, `kategori` (`libur` / `sakit` / `lainnya`), `alasan` (wajib untuk `lainnya`), `lampiran` (SKD, wajib untuk `sakit`; JPG/PNG/PDF maks 5 MB) |
| `GET /api/aktivitas?bulan=2026-11` | riwayat bulanan (alur baru) + `absensi_lama` (masuk/pulang lama) |

`kunci` = kode unik dari aplikasi untuk SATU kali penekanan Kirim (UUID). Kirim ulang dengan kunci yang sama
(klik ganda, sinyal putus) tidak membuat catatan baru: server membalas 200 dengan `duplikat: true`.

Kode `kode` penolakan: `login_gagal` (401), `terlalu_banyak_percobaan` (429), `wajib_ganti_pin` (403),
`akun_nonaktif` (403), `karyawan_tidak_aktif` (403), `tujuan_kosong`, `akurasi_rendah`, `foto_tidak_valid`,
`skd_wajib`, `alasan_wajib`, `lampiran_tidak_valid` (422), `kunci_bentrok` (409), `server_tanpa_gd` (500).

Endpoint LAMA (`/api/absensi/masuk|pulang|hari-ini|`) masih ada agar data lama tetap bisa dibaca dan diuji,
tetapi aplikasi `/absen` tidak lagi memakainya.

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

## 4. Aturan absensi LAMA (masuk/pulang, config/absensi.php)

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

## 6. Alur absensi baru: Standby / Order / Izin

**Login karyawan:** NIP + PIN di `/absen` (bukan email/password). Panel web `/login` kini khusus HRD.
HRD membuat akun (otomatis saat pelamar diterima, atau lewat Tambah Karyawan; NIP boleh diisi sendiri) dan
mendapat **PIN sementara 6 digit** (tampil sekali). Saat login pertama karyawan wajib mendaftarkan PIN sendiri.
Karyawan lama yang password-nya bukan 6 digit: klik **Reset PIN** di halaman detail karyawan.

| Pilihan | Aturan yang dijaga server |
|---|---|
| **Standby** | Langsung Kirim. Dicatat atas akun yang login + jam server. Tanpa lokasi, selfie, keterangan |
| **Order** | Wajib tujuan + lokasi terkini HP + selfie. Kamera terkunci sampai lokasi terbaca (izin ditolak/gagal: pesan + tombol Coba Lagi). Selfie diberi watermark di SERVER: lokasi aktual (nama bila ada, koordinat bila tidak), tanggal & jam WIB, logo MTU. Tujuan dan lokasi aktual disimpan terpisah. Di luar radius kantor tetap sah |
| **Izin > Libur** | Langsung Kirim |
| **Izin > Sakit** | SKD (JPG/PNG/PDF) wajib; isinya diperiksa di server dan disimpan privat |
| **Izin > Lain-lain** | Alasan wajib; status Menunggu Keputusan. Admin hanya bisa **Setujui setelah memilih karyawan pengganti**; bila tidak ada pengganti, admin **Tolak** dengan alasan. Hasilnya tampil di halaman karyawan |

Panel admin: menu **Aktivitas Absensi** (semua catatan, selfie, SKD, peta) dan **Pengajuan Izin**
(keputusan; lencana jumlah yang menunggu). Semuanya khusus HRD. Menu absensi lama diberi nama "Absensi Lama".

**Database:** tabel baru `kegiatan_absensi`. Tabel `absensi` lama TIDAK diubah, jadi riwayat lama tetap utuh.
Perintah: `php artisan migrate`.

**Wajib untuk watermark:** ekstensi PHP **GD** dengan FreeType. Cek: `php -m | findstr gd`. Bila belum aktif, buka `php.ini`
(Laragon: menu Menu > PHP > php.ini), hapus tanda `;` pada `extension=gd`, lalu restart Laragon. Tanpa GD, Order ditolak dengan
pesan jelas (kode `server_tanpa_gd`), tidak ada catatan setengah jadi.

**Nama lokasi pada watermark** diambil dari OpenStreetMap Nominatim (koordinat dikirim ke layanan itu). Isi `ABSENSI_GEOCODER_UA`
dengan kontak yang benar. Bila layanan mati/lambat atau `ABSENSI_GEOCODER=false`, watermark otomatis memakai koordinat.

**Alpa otomatis dimatikan** (jadwal `absensi:tandai-alpa` dikomentari di `routes/console.php`): alur baru tidak punya jam masuk, jadi
alpa otomatis akan salah menandai semua karyawan. Aturan alpa untuk alur baru belum ditetapkan.

Asumsi yang saya ambil (ubah bila tidak sesuai): izin berlaku untuk **hari pengajuan**; satu karyawan boleh mengirim beberapa Order/Standby
dalam sehari; posisi Order dengan akurasi lebih buruk dari 1000 m ditolak (`ABSENSI_ORDER_MAKS_AKURASI`).

### Menguji kamera dan lokasi di HP (wajib HTTPS)
1. Jalankan server Laravel (`php artisan serve`), lalu terowongan HTTPS: `cloudflared tunnel --url http://127.0.0.1:8000`.
2. Di HP buka `https://....trycloudflare.com/absen`, login NIP + PIN, izinkan **Lokasi** dan **Kamera**.
3. Uji berurutan: Standby; Order (tolak izin lokasi dulu: kamera harus tetap terkunci, lalu Coba Lagi); Izin Libur/Sakit/Lain-lain.
4. Di komputer, login HRD: cek **Aktivitas Absensi** (selfie ber-watermark), lalu **Pengajuan Izin** (pilih pengganti, Setujui).

### Pengujian
`php artisan test` (PHP), `node --test --test-force-exit tests/pwa/*.test.mjs` (aplikasi absensi; butuh `npm i -D jsdom` untuk uji layar).
Uji watermark nyata (`tests/Unit/WatermarkFotoTest`) otomatis berjalan bila GD aktif; bila tidak, dilewati.
