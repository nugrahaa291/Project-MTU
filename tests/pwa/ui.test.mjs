// Uji alur layar aplikasi di DOM tiruan (jsdom) dengan server palsu, GPS palsu, dan kamera palsu.
// Opsional: butuh `npm i -D jsdom`. Tanpa jsdom, berkas ini dilewati.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

let JSDOM = null;
try { ({ JSDOM } = await import('jsdom')); } catch { /* dilewati */ }

const baca = (f) => readFileSync(new URL(`../../public/pwa/${f}`, import.meta.url), 'utf8');

/** Gabungkan modul (hapus import/export) agar bisa dijalankan sebagai satu skrip di jsdom. */
function rakitSkrip() {
    const bersih = (s) => s.replace(/^import[\s\S]*?from\s+['"][^'"]+['"];?/gm, '').replace(/^export\s+/gm, '');
    return [bersih(baca('lib.js')), bersih(baca('api.js')), bersih(baca('app.js'))].join('\n');
}

const KANTOR = { latitude: -6.3215, longitude: 107.3376, radius_meter: 100, nama: 'Kantor Pusat' };
const POSISI = {
    dekat: { latitude: -6.3215, longitude: 107.3376, accuracy: 8 },
    jauh: { latitude: -6.3315, longitude: 107.3376, accuracy: 8 },
};

/** Membuat "dunia" aplikasi: server palsu + browser palsu. */
function buatDunia({ posisi = 'dekat', mustChange = false, hariIni = {} } = {}) {
    const server = {
        panggilan: [],
        token: 'TOKEN-PALSU',
        hari: { tanggal: '2026-10-28', waktu_server: '2026-10-28T08:00:00+07:00', hari_kerja: true, kantor: KANTOR, jam_masuk: '08:00', toleransi_menit: 15, bisa_masuk: true, bisa_pulang: false, absensi: null, ...hariIni },
        mustChange,
        kirim: null,
    };
    const dom = new JSDOM('<!DOCTYPE html><body><div id="app"></div><div id="toast"></div></body>', { url: 'https://absen.test/absen', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window;

    const json = (status, data) => ({ ok: status < 300, status, json: async () => data });
    w.fetch = async (url, opsi = {}) => {
        server.panggilan.push({ url, metode: opsi.method, auth: opsi.headers?.Authorization, body: opsi.body });
        const path = url.replace('/api', '');
        if (path === '/login') {
            const b = JSON.parse(opsi.body);
            return b.password === 'benar'
                ? json(200, { token: server.token, token_type: 'Bearer', must_change_password: server.mustChange, karyawan: { nama: 'Budi Santoso', nip: '26100001', jabatan: 'Staff IT', email: b.email } })
                : json(401, { message: 'Email atau password salah, atau akun dinonaktifkan.' });
        }
        if (opsi.headers?.Authorization !== `Bearer ${server.token}`) return json(401, { message: 'Unauthenticated.' });
        if (path === '/me') return json(200, { must_change_password: server.mustChange, karyawan: { nama: 'Budi Santoso', nip: '26100001', jabatan: 'Staff IT', email: 'budi@example.com' } });
        if (path === '/password') {
            server.mustChange = false;
            return json(200, { message: 'Password berhasil diganti.' });
        }
        if (path === '/absensi/hari-ini') return server.mustChange ? json(403, { message: 'Ganti password dulu.', kode: 'wajib_ganti_password' }) : json(200, server.hari);
        if (path === '/absensi/masuk' || path === '/absensi/pulang') {
            server.kirim = { path, form: opsi.body };
            const masuk = path.endsWith('masuk');
            server.hari = { ...server.hari, bisa_masuk: false, bisa_pulang: masuk, absensi: { id: 1, tanggal: '2026-10-28', status: 'hadir', status_label: 'Hadir', jam_masuk: '07:58:00', jam_pulang: masuk ? null : '17:02:00', menit_terlambat: 0 } };
            return json(201, { message: masuk ? 'Absen masuk berhasil. Selamat bekerja!' : 'Absen pulang berhasil.', absensi: server.hari.absensi });
        }
        if (path.startsWith('/absensi?bulan=')) return json(200, { bulan: '2026-10', ringkasan: { hadir: 1 }, data: [{ id: 1, tanggal: '2026-10-27', status: 'hadir', status_label: 'Hadir', jam_masuk: '07:55:00', jam_pulang: '17:01:00', menit_terlambat: 0 }] });
        return json(404, { message: 'tidak ada' });
    };

    const p = POSISI[posisi];
    const coords = () => ({ coords: { ...p } });
    Object.defineProperty(w.navigator, 'geolocation', { value: { watchPosition: (ok) => { setTimeout(() => ok(coords()), 0); return 7; }, getCurrentPosition: (ok) => ok(coords()), clearWatch() {} }, configurable: true });
    const trek = { stop() { server.kameraDihentikan = true; } };
    Object.defineProperty(w.navigator, 'mediaDevices', { value: { getUserMedia: async () => ({ getTracks: () => [trek] }) }, configurable: true });
    w.HTMLMediaElement.prototype.play = async () => {};
    Object.defineProperty(w.HTMLVideoElement.prototype, 'videoWidth', { get: () => 640, configurable: true });
    Object.defineProperty(w.HTMLVideoElement.prototype, 'videoHeight', { get: () => 480, configurable: true });
    w.HTMLCanvasElement.prototype.getContext = () => ({ drawImage() {} });
    w.HTMLCanvasElement.prototype.toBlob = function (cb) { cb(new w.Blob(['jpeg'], { type: 'image/jpeg' })); };
    w.URL.createObjectURL = () => 'blob:palsu';
    w.URL.revokeObjectURL = () => {};

    const jalan = () => w.eval(rakitSkrip());
    return { w, dom, server, jalan, doc: w.document };
}

async function tunggu(kondisi, ket = 'kondisi', ms = 1500) {
    const mulai = Date.now();
    while (Date.now() - mulai < ms) {
        if (kondisi()) return;
        await new Promise((r) => setTimeout(r, 10));
    }
    throw new Error(`Timeout menunggu: ${ket}`);
}
const teks = (d) => d.body.textContent.replace(/\s+/g, ' ');
const ketik = (el, v) => { el.value = v; };

async function login(dunia, sandi = 'benar') {
    const { doc, w } = dunia;
    await tunggu(() => doc.getElementById('email'), 'form login');
    ketik(doc.getElementById('email'), 'budi@example.com');
    ketik(doc.getElementById('sandi'), sandi);
    doc.querySelector('form').dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
}

const lewati = JSDOM ? false : 'jsdom tidak terpasang (npm i -D jsdom)';

test('login salah menampilkan pesan, tidak menyimpan token', { skip: lewati }, async () => {
    const d = buatDunia();
    d.jalan();
    await login(d, 'salah');
    await tunggu(() => !d.doc.querySelector('.galat').classList.contains('hidden'), 'pesan galat');
    assert.match(d.doc.querySelector('.galat').textContent, /salah/);
    assert.equal(d.w.localStorage.getItem('pt.token'), null);
    d.w.close();
});

test('alur lengkap: login -> absen masuk dengan selfie -> tombol berganti pulang', { skip: lewati }, async () => {
    const d = buatDunia({ posisi: 'dekat' });
    d.jalan();
    await login(d);

    await tunggu(() => d.doc.getElementById('tombol-aksi'), 'beranda');
    await tunggu(() => /Absen Masuk$/.test(d.doc.getElementById('tombol-aksi').textContent), 'tombol Absen Masuk aktif');
    assert.equal(d.doc.getElementById('tombol-aksi').disabled, false);
    assert.equal(d.w.localStorage.getItem('pt.token'), 'TOKEN-PALSU');
    assert.match(teks(d.doc), /Budi Santoso/);
    assert.match(d.doc.getElementById('kartu-lokasi').textContent, /0 m/);
    assert.match(d.doc.getElementById('kartu-lokasi').textContent, /Di dalam area absen/);
    assert.match(d.doc.getElementById('jam').textContent, /^08:00:\d\d$/); // jam mengikuti server (08:00 WIB)

    // Nama perangkat dikirim saat login
    const loginReq = d.server.panggilan.find((c) => c.url === '/api/login');
    assert.match(JSON.parse(loginReq.body).device_name, /^PWA Desktop [a-z0-9]+$/);

    d.doc.getElementById('tombol-aksi').click();
    await tunggu(() => d.doc.querySelector('#sheet-kamera video'), 'pratinjau kamera');
    assert.match(d.doc.querySelector('#sheet-kamera h2').textContent, /Masuk/);

    const ambil = [...d.doc.querySelectorAll('#sheet-kamera button')].find((b) => b.textContent === 'Ambil Foto');
    ambil.click();
    await tunggu(() => d.doc.querySelector('#sheet-kamera img'), 'pratinjau hasil foto');
    assert.equal(d.server.kameraDihentikan, true, 'kamera dimatikan setelah memotret');

    [...d.doc.querySelectorAll('#sheet-kamera button')].find((b) => /Kirim Absen Masuk/.test(b.textContent)).click();
    await tunggu(() => d.server.kirim, 'permintaan absen');

    const form = d.server.kirim.form;
    assert.equal(d.server.kirim.path, '/absensi/masuk');
    assert.equal(form.get('latitude'), '-6.3215');
    assert.equal(form.get('longitude'), '107.3376');
    assert.equal(form.get('akurasi'), '8');
    assert.equal(form.get('foto').type, 'image/jpeg');
    assert.equal(d.server.panggilan.at(-1).auth ?? d.server.panggilan.find((c) => c.url.endsWith('/masuk')).auth, 'Bearer TOKEN-PALSU');

    await tunggu(() => !d.doc.getElementById('sheet-kamera') && /Absen Pulang$/.test(d.doc.getElementById('tombol-aksi')?.textContent ?? ''), 'kembali ke beranda dengan tombol pulang');
    assert.match(d.doc.getElementById('toast').textContent, /Absen masuk berhasil/);
    assert.match(teks(d.doc), /Sudah absen masuk 07:58/);
    d.w.close();
});

test('di luar radius: tombol terkunci dan menampilkan jarak', { skip: lewati }, async () => {
    const d = buatDunia({ posisi: 'jauh' });
    d.jalan();
    await login(d);
    await tunggu(() => /Di luar area absen/.test(d.doc.getElementById('tombol-aksi')?.textContent ?? ''), 'tombol terkunci');
    assert.equal(d.doc.getElementById('tombol-aksi').disabled, true);
    assert.match(d.doc.getElementById('kartu-lokasi').textContent, /1,1 km/);
    d.w.close();
});

test('password sementara: dipaksa ganti sebelum beranda', { skip: lewati }, async () => {
    const d = buatDunia({ mustChange: true });
    d.jalan();
    await login(d);
    await tunggu(() => d.doc.getElementById('lama'), 'layar ganti password');
    assert.match(teks(d.doc), /password sementara/);

    ketik(d.doc.getElementById('lama'), 'Sementara1');
    ketik(d.doc.getElementById('baru'), 'PasswordBaru9');
    ketik(d.doc.getElementById('ulang'), 'Beda12345');
    d.doc.querySelector('form').dispatchEvent(new d.w.Event('submit', { cancelable: true, bubbles: true }));
    await tunggu(() => !d.doc.querySelector('.galat').classList.contains('hidden'), 'galat konfirmasi');
    assert.match(d.doc.querySelector('.galat').textContent, /tidak sama/);

    ketik(d.doc.getElementById('ulang'), 'PasswordBaru9');
    d.doc.querySelector('form').dispatchEvent(new d.w.Event('submit', { cancelable: true, bubbles: true }));
    await tunggu(() => d.doc.getElementById('tombol-aksi'), 'beranda setelah ganti password');
    assert.equal(d.server.mustChange, false);
    d.w.close();
});

test('sesi kedaluwarsa (401) mengembalikan ke login dengan pesan', { skip: lewati }, async () => {
    const d = buatDunia();
    d.w.localStorage.setItem('pt.token', 'TOKEN-LAMA');
    d.jalan();
    await tunggu(() => d.doc.getElementById('email') && /berakhir/.test(teks(d.doc)), 'login dengan pesan sesi berakhir');
    assert.equal(d.w.localStorage.getItem('pt.token'), null);
    d.w.close();
});

test('token tersimpan: langsung masuk beranda tanpa login ulang; keluar menghapus token', { skip: lewati }, async () => {
    const d = buatDunia();
    d.w.localStorage.setItem('pt.token', 'TOKEN-PALSU');
    d.jalan();
    await tunggu(() => d.doc.getElementById('tombol-aksi'), 'beranda');

    [...d.doc.querySelectorAll('.nav button')].find((b) => /Akun/.test(b.textContent)).click();
    await tunggu(() => [...d.doc.querySelectorAll('button')].some((b) => b.textContent === 'Keluar'), 'tab akun');
    [...d.doc.querySelectorAll('button')].find((b) => b.textContent === 'Keluar').click();
    await tunggu(() => d.doc.getElementById('email'), 'kembali ke login');
    assert.equal(d.w.localStorage.getItem('pt.token'), null);
    assert.ok(d.server.panggilan.some((c) => c.url === '/api/logout' && c.metode === 'POST'));
    d.w.close();
});

test('riwayat bulanan tampil', { skip: lewati }, async () => {
    const d = buatDunia();
    d.w.localStorage.setItem('pt.token', 'TOKEN-PALSU');
    d.jalan();
    await tunggu(() => d.doc.getElementById('tombol-aksi'), 'beranda');
    [...d.doc.querySelectorAll('.nav button')].find((b) => /Riwayat/.test(b.textContent)).click();
    await tunggu(() => /Oktober 2026/.test(teks(d.doc)) && d.doc.querySelector('.item-riwayat'), 'riwayat');
    assert.match(teks(d.doc), /07:55/);
    assert.match(teks(d.doc), /hadir 1/);
    d.w.close();
});

test('teks dari server tidak pernah diperlakukan sebagai HTML (anti XSS)', { skip: lewati }, async () => {
    const d = buatDunia({ hariIni: { absensi: { id: 1, tanggal: '2026-10-28', status: 'izin', status_label: '<img src=x onerror=alert(1)>', jam_masuk: null, jam_pulang: null, menit_terlambat: 0, keterangan: '<script>alert(1)</script>' }, bisa_masuk: false } });
    d.w.localStorage.setItem('pt.token', 'TOKEN-PALSU');
    d.jalan();
    await tunggu(() => d.doc.querySelector('.status-judul'), 'beranda');
    assert.equal(d.doc.querySelectorAll('img, script').length, 0);
    assert.match(d.doc.querySelector('.status-judul').textContent, /<img/); // tampil sebagai teks biasa
    d.w.close();
});
