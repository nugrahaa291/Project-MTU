// Fungsi murni (tanpa DOM) untuk aplikasi absensi. Dipisah agar mudah diuji: node --test tests/pwa/
export const TZ = 'Asia/Jakarta';

/** Jarak dua koordinat (meter), rumus Haversine - SAMA dengan App\Support\Geo di server. */
export function jarakMeter(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const rad = (d) => (d * Math.PI) / 180;
    const dLat = rad(lat2 - lat1);
    const dLng = rad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(dLng / 2) ** 2;
    return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}

export function formatJarak(meter) {
    if (meter < 1000) return `${meter} m`;
    return `${(meter / 1000).toFixed(1).replace('.', ',')} km`;
}

/** Posisi dibandingkan dengan kantor: {jarak, akurasi, dalam, pastiLuar}. */
export function statusRadius(posisi, kantor) {
    if (!posisi || !kantor) return null;
    const jarak = jarakMeter(posisi.latitude, posisi.longitude, kantor.latitude, kantor.longitude);
    const akurasi = Math.round(posisi.akurasi ?? 0);
    return {
        jarak,
        akurasi,
        dalam: jarak <= kantor.radius_meter,
        // Selisih jarak dan akurasi masih melebihi radius => hampir pasti di luar (bukan sekadar GPS meleset)
        pastiLuar: jarak - akurasi > kantor.radius_meter,
    };
}

export function pesanGeolokasi(kode) {
    switch (kode) {
        case 1:
            return 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di pengaturan browser, lalu muat ulang halaman.';
        case 2:
            return 'Lokasi tidak tersedia. Pastikan GPS/Lokasi di HP Anda aktif.';
        case 3:
            return 'Mencari lokasi terlalu lama. Pindah ke area terbuka lalu coba lagi.';
        default:
            return 'Tidak dapat membaca lokasi perangkat.';
    }
}

/** Selisih jam server - jam perangkat (ms). Jam di layar mengikuti SERVER, bukan jam HP yang bisa diubah. */
export function hitungOffset(waktuServerIso, sekarangMs) {
    const t = Date.parse(waktuServerIso);
    return Number.isNaN(t) ? 0 : t - sekarangMs;
}

export function formatJamLengkap(date) {
    return new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(date);
}

export function formatTanggalPanjang(date) {
    return new Intl.DateTimeFormat('id-ID', { timeZone: TZ, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

/** '2026-10-28' -> 'Rab, 28 Okt' (dibaca sebagai tanggal kalender, bebas pengaruh zona waktu perangkat). */
export function formatTanggalPendek(iso) {
    return new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(`${iso}T00:00:00Z`));
}

export function formatJam(hms) {
    return hms ? hms.slice(0, 5) : '-';
}

export function bulanSekarang(date) {
    const bagian = new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit' }).formatToParts(date);
    const ambil = (t) => bagian.find((p) => p.type === t).value;
    return `${ambil('year')}-${ambil('month')}`;
}

export function geserBulan(bulan, delta) {
    const [y, m] = bulan.split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + delta, 1));
    return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
}

export function namaBulan(bulan) {
    const [y, m] = bulan.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', month: 'long', year: 'numeric' }).format(new Date(Date.UTC(y, m - 1, 1)));
}

/** Tombol apa yang boleh ditekan sekarang: 'masuk' | 'pulang' | null. */
export function aksiTersedia(hariIni) {
    if (!hariIni) return null;
    if (hariIni.bisa_masuk) return 'masuk';
    if (hariIni.bisa_pulang) return 'pulang';
    return null;
}

/** Ringkasan kondisi hari ini untuk kartu status. */
export function ringkasHariIni(hariIni) {
    if (!hariIni) return { judul: 'Memuat...', rinci: '', warna: 'slate' };
    const a = hariIni.absensi;
    if (!a) {
        return hariIni.hari_kerja
            ? { judul: 'Belum absen', rinci: `Jam masuk ${hariIni.jam_masuk} (toleransi ${hariIni.toleransi_menit} menit)`, warna: 'slate' }
            : { judul: 'Hari ini libur', rinci: 'Bukan hari kerja. Tidak perlu absen.', warna: 'blue' };
    }
    if (!a.jam_masuk) {
        return { judul: a.status_label, rinci: a.keterangan || 'Dicatat oleh HRD', warna: a.status === 'alpa' ? 'red' : 'blue' };
    }
    const telat = a.menit_terlambat > 0 ? ` (terlambat ${a.menit_terlambat} menit)` : '';
    if (a.jam_pulang) {
        return { judul: 'Absensi hari ini selesai', rinci: `Masuk ${formatJam(a.jam_masuk)}${telat} · Pulang ${formatJam(a.jam_pulang)}`, warna: 'green' };
    }
    return { judul: `Sudah absen masuk ${formatJam(a.jam_masuk)}`, rinci: telat ? `Terlambat ${a.menit_terlambat} menit` : 'Tepat waktu', warna: a.status === 'terlambat' ? 'amber' : 'green' };
}

export function warnaStatus(status) {
    return { hadir: 'green', terlambat: 'amber', izin: 'blue', sakit: 'blue', cuti: 'blue', alpa: 'red' }[status] ?? 'slate';
}

/** Data kiriman absen. Server TIDAK menerima jam dari klien: waktu selalu dari server. */
export function bangunFormAbsen({ foto, latitude, longitude, akurasi }) {
    const form = new FormData();
    form.append('foto', foto, 'selfie.jpg');
    form.append('latitude', String(latitude));
    form.append('longitude', String(longitude));
    if (akurasi != null) form.append('akurasi', String(Math.round(akurasi)));
    return form;
}

/** Nama perangkat stabil (supaya login ulang menggantikan token lama di perangkat yang sama). */
export function namaPerangkat(userAgent, idAcak) {
    const jenis = /iPhone|iPad|iPod/i.test(userAgent) ? 'iOS' : /Android/i.test(userAgent) ? 'Android' : 'Desktop';
    return `PWA ${jenis} ${idAcak}`;
}

/** Skala gambar agar sisi terpanjang <= maks (px). */
export function ukuranTerskala(lebar, tinggi, maks = 1024) {
    const rasio = Math.min(1, maks / Math.max(lebar, tinggi));
    return { lebar: Math.round(lebar * rasio), tinggi: Math.round(tinggi * rasio) };
}

/* ===================================================================================
 * Alur absensi baru: Standby / Order / Izin
 * =================================================================================== */

/** Kunci unik untuk SATU kali pengiriman. Server memakainya agar kirim ulang tidak membuat catatan ganda. */
export function buatKunci() {
    const c = globalThis.crypto;
    if (c?.randomUUID) return c.randomUUID();
    const bait = new Uint8Array(16);
    if (c?.getRandomValues) c.getRandomValues(bait);
    else for (let i = 0; i < 16; i++) bait[i] = Math.floor(Math.random() * 256);
    return [...bait].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** Aturan PIN yang sama dengan server (App\Support\Pin): 6 digit, bukan pola yang mudah ditebak. */
export function pinValid(pin) {
    if (!/^\d{6}$/.test(pin)) return false;
    if (/^(\d{1,3})\1+$/.test(pin)) return false; // 111111, 121212, 123123
    let naik = true;
    let turun = true;
    for (let i = 1; i < 6; i++) {
        const selisih = (Number(pin[i]) - Number(pin[i - 1]) + 10) % 10;
        naik = naik && selisih === 1;
        turun = turun && selisih === 9;
    }
    return !(naik || turun);
}

export function pesanPin(pin) {
    if (!/^\d{6}$/.test(pin)) return 'PIN harus 6 digit angka.';
    if (!pinValid(pin)) return 'PIN terlalu mudah ditebak (angka berulang atau berurutan). Pilih kombinasi lain.';
    return null;
}

export function formatKoordinat(lat, lng) {
    return `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
}

/** Memeriksa berkas SKD sebelum dikirim (server tetap memeriksa ulang). Mengembalikan pesan salah atau null. */
export function validasiLampiran(berkas, maksMb = 5) {
    if (!berkas) return 'Pilih berkas SKD terlebih dahulu.';
    const jenisOk = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!jenisOk.includes(berkas.type)) return 'SKD harus berupa foto (JPG/PNG) atau PDF.';
    if (berkas.size > maksMb * 1024 * 1024) return `Ukuran SKD maksimal ${maksMb} MB.`;
    if (berkas.size < 1) return 'Berkas SKD kosong atau rusak.';
    return null;
}

export function bangunFormStandby() {
    return null; // Standby hanya butuh kunci (JSON); tidak ada lokasi, selfie, atau keterangan
}

export function bangunFormOrder({ kunci, tujuan, foto, latitude, longitude, akurasi }) {
    const form = new FormData();
    form.append('kunci', kunci);
    form.append('tujuan', tujuan);
    form.append('foto', foto, 'selfie.jpg');
    form.append('latitude', String(latitude));
    form.append('longitude', String(longitude));
    if (akurasi != null) form.append('akurasi', String(Math.round(akurasi)));
    return form;
}

export function bangunFormIzin({ kunci, kategori, alasan, lampiran }) {
    const form = new FormData();
    form.append('kunci', kunci);
    form.append('kategori', kategori);
    if (alasan) form.append('alasan', alasan);
    if (lampiran) form.append('lampiran', lampiran, lampiran.name || 'skd');
    return form;
}

/** Ringkasan satu catatan untuk daftar: {judul, rinci, warna}. */
export function ringkasKegiatan(k) {
    const warna = { tercatat: 'green', menunggu: 'amber', disetujui: 'blue', ditolak: 'red' }[k.status] ?? 'slate';

    if (k.jenis === 'standby') return { judul: 'Standby', rinci: 'Status standby tercatat', warna };
    if (k.jenis === 'order') {
        const lokasi = k.nama_lokasi
            || (k.lokasi_aktual ? formatKoordinat(k.lokasi_aktual.latitude, k.lokasi_aktual.longitude) : '');
        return { judul: `Order ke ${k.tujuan}`, rinci: lokasi ? `Lokasi HP: ${lokasi}` : '', warna };
    }

    const kategori = k.kategori_label || 'Izin';
    let rinci = '';
    if (k.status === 'menunggu') rinci = 'Menunggu keputusan HRD';
    else if (k.status === 'disetujui') rinci = k.pengganti ? `Disetujui. Pengganti: ${k.pengganti}` : 'Disetujui';
    else if (k.status === 'ditolak') rinci = k.alasan_penolakan ? `Ditolak: ${k.alasan_penolakan}` : 'Ditolak';
    else if (k.kategori_izin === 'sakit') rinci = 'SKD terlampir';
    else rinci = 'Tercatat';

    return { judul: `Izin ${kategori}`, rinci, warna };
}
