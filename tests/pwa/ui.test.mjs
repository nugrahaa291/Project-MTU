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

const POSISI = {
    dekat: { latitude: -6.3215, longitude: 107.3376, accuracy: 8 },
    jauh: { latitude: -6.0, longitude: 106.5, accuracy: 15 }, // jauh dari kantor mana pun
};
const PROFIL = { nama: 'Budi Santoso', nip: 'MTU-0123', jabatan: 'Driver' };

/** "Dunia" aplikasi: server palsu + browser palsu (GPS, kamera, kanvas). */
function buatDunia({ geo = ['ok'], posisi = 'dekat', mustChange = false, izinLainnya = [] } = {}) {
    const server = { panggilan: [], mustChange, hariIni: [], izinLainnya, kunci: new Map(), gagalBerikut: false, nomor: 0, kameraDibuka: 0 };
    const dom = new JSDOM('<!DOCTYPE html><body><div id="app"></div><div id="toast"></div></body>', { url: 'https://absen.test/absen', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window;
    const json = (status, data) => ({ ok: status < 300, status, json: async () => data });

    const buatCatatan = (jenis, tambahan = {}) => ({
        id: ++server.nomor, jenis, jenis_label: jenis, kategori_izin: null, kategori_label: null, status: 'tercatat', status_label: 'Tercatat',
        tanggal: '2026-11-14', waktu: '2026-11-14T10:32:05+07:00', jam: '10:32:05', tujuan: null, nama_lokasi: null, lokasi_aktual: null,
        jarak_kantor_meter: null, alasan: null, ada_lampiran: false, alasan_penolakan: null, pengganti: null, ...tambahan,
    });

    w.fetch = async (url, opsi = {}) => {
        const path = url.replace('/api', '');
        const body = opsi.body;
        server.panggilan.push({ url, metode: opsi.method, auth: opsi.headers?.Authorization, body });

        if (path === '/login') {
            const b = JSON.parse(body);
            return b.nip === 'MTU-0123' && b.password === '482913'
                ? json(200, { token: 'TOKEN-PALSU', token_type: 'Bearer', must_change_password: server.mustChange, karyawan: PROFIL })
                : json(401, { message: 'NIP atau password salah, atau akun dinonaktifkan.', kode: 'login_gagal' });
        }
        if (opsi.headers?.Authorization !== 'Bearer TOKEN-PALSU') return json(401, { message: 'Unauthenticated.' });
        if (path === '/me') return json(200, { must_change_password: server.mustChange, karyawan: PROFIL });
        if (path === '/password') {
            const b = JSON.parse(body);
            if (b.current_password !== '482913') return json(422, { message: 'x', errors: { current_password: ['Password lama tidak sesuai.'] } });
            server.mustChange = false;
            return json(200, { message: 'Password berhasil didaftarkan.' });
        }
        if (path === '/aktivitas/ringkasan') {
            return server.mustChange
                ? json(403, { message: 'Ganti Password dulu.', kode: 'wajib_ganti_password' })
                : json(200, { tanggal: '2026-11-14', waktu_server: '2026-11-14T10:32:05+07:00', hari_ini: server.hariIni, izin_lainnya: server.izinLainnya });
        }
        if (path.startsWith('/aktivitas?bulan=')) {
            return json(200, { bulan: '2026-11', ringkasan: { standby: 1 }, data: server.hariIni, absensi_lama: [{ id: 1, tanggal: '2026-11-03', status: 'hadir', status_label: 'Hadir', jam_masuk: '07:55:00', jam_pulang: '17:01:00' }] });
        }
        if (['/aktivitas/standby', '/aktivitas/order', '/aktivitas/izin'].includes(path)) {
            if (server.gagalBerikut) {
                server.gagalBerikut = false;
                return json(500, { message: 'Server sedang bermasalah.' });
            }
            const isForm = body && typeof body.get === 'function';
            const kunci = isForm ? body.get('kunci') : JSON.parse(body).kunci;
            if (server.kunci.has(kunci)) return json(200, { message: 'Kiriman ini sudah tercatat sebelumnya.', duplikat: true, kegiatan: server.kunci.get(kunci) });

            let catatan;
            if (path.endsWith('standby')) catatan = buatCatatan('standby');
            else if (path.endsWith('order')) {
                server.order = body;
                catatan = buatCatatan('order', { tujuan: body.get('tujuan'), nama_lokasi: 'Jalan Tuparev, Nagasari', lokasi_aktual: { latitude: Number(body.get('latitude')), longitude: Number(body.get('longitude')), akurasi_meter: Number(body.get('akurasi')) } });
            } else {
                server.izin = body;
                const kategori = body.get('kategori');
                catatan = buatCatatan('izin', {
                    kategori_izin: kategori, kategori_label: { libur: 'Libur', sakit: 'Sakit', lainnya: 'Lain-lain' }[kategori],
                    status: kategori === 'lainnya' ? 'menunggu' : 'tercatat', status_label: kategori === 'lainnya' ? 'Menunggu Keputusan' : 'Tercatat',
                    alasan: body.get('alasan'), ada_lampiran: body.has('lampiran'),
                });
                if (kategori === 'lainnya') server.izinLainnya = [catatan, ...server.izinLainnya];
            }
            server.kunci.set(kunci, catatan);
            server.hariIni = [catatan, ...server.hariIni];
            return json(201, { message: 'Tercatat.', duplikat: false, kegiatan: catatan });
        }
        return json(404, { message: 'tidak ada' });
    };

    // GPS palsu: daftar hasil berurutan, mis. ['ditolak', 'ok'] (hasil terakhir berulang)
    const gps = { urutan: [...geo], panggilan: 0, posisi };
    Object.defineProperty(w.navigator, 'geolocation', {
        configurable: true,
        value: {
            getCurrentPosition: (ok, gagal) => {
                const mode = gps.urutan[Math.min(gps.panggilan++, gps.urutan.length - 1)];
                if (mode === 'ok') setTimeout(() => ok({ coords: { ...POSISI[gps.posisi] } }), 0);
                else setTimeout(() => gagal({ code: mode === 'ditolak' ? 1 : mode === 'tak-tersedia' ? 2 : 3 }), 0);
            },
        },
    });
    const trek = { stop() { server.kameraDihentikan = (server.kameraDihentikan || 0) + 1; } };
    Object.defineProperty(w.navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => { server.kameraDibuka++; return { getTracks: () => [trek] }; } } });
    w.HTMLMediaElement.prototype.play = async () => {};
    Object.defineProperty(w.HTMLVideoElement.prototype, 'videoWidth', { get: () => 640, configurable: true });
    Object.defineProperty(w.HTMLVideoElement.prototype, 'videoHeight', { get: () => 480, configurable: true });
    w.HTMLCanvasElement.prototype.getContext = () => ({ drawImage() {} });
    w.HTMLCanvasElement.prototype.toBlob = function (cb) { cb(new w.Blob(['jpeg'], { type: 'image/jpeg' })); };
    w.URL.createObjectURL = () => 'blob:palsu';
    w.URL.revokeObjectURL = () => {};

    return { w, dom, server, gps, doc: w.document, jalan: () => w.eval(rakitSkrip()) };
}

async function tunggu(kondisi, ket = 'kondisi', ms = 2000) {
    const mulai = Date.now();
    while (Date.now() - mulai < ms) {
        if (kondisi()) return;
        await new Promise((r) => setTimeout(r, 10));
    }
    throw new Error(`Timeout menunggu: ${ket}`);
}
const teks = (d) => d.body.textContent.replace(/\s+/g, ' ');
const $ = (d, sel) => d.doc.querySelector(sel);
const submit = (d, form) => form.dispatchEvent(new d.w.Event('submit', { cancelable: true, bubbles: true }));

async function login(d, nip = 'MTU-0123', password = '482913') {
    await tunggu(() => $(d, '#nip'), 'form login');
    $(d, '#nip').value = nip;
    $(d, '#password').value = password;
    submit(d, $(d, 'form'));
}
async function keBeranda(d, opsi) {
    d.jalan();
    await login(d);
    await tunggu(() => $(d, '#tombol-absen') && !$(d, '#tombol-absen').disabled, 'tombol Absen aktif');
}
async function bukaJenis(d, id) {
    $(d, '#tombol-absen').click();
    await tunggu(() => $(d, `#${id}`), `pilihan ${id}`);
    $(d, `#${id}`).click();
}
const lewati = JSDOM ? false : 'jsdom tidak terpasang (npm i -D jsdom)';

/* --------------------------------------------------------------------- login */

test('login dengan NIP salah menampilkan pesan dan tidak menyimpan token', { skip: lewati }, async () => {
    const d = buatDunia();
    d.jalan();
    await login(d, 'MTU-0123', '000001');
    await tunggu(() => !$(d, '.galat').classList.contains('hidden'), 'pesan galat');
    assert.match($(d, '.galat').textContent, /NIP atau password salah/);
    assert.equal(d.w.localStorage.getItem('pt.token'), null);
    d.w.close();
});

test('login NIP + Password langsung ke halaman Absen dengan satu tombol utama', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);

    const kirim = JSON.parse(d.server.panggilan.find((c) => c.url === '/api/login').body);
    assert.equal(kirim.nip, 'MTU-0123');
    assert.equal(kirim.password, '482913');
    assert.match(kirim.device_name, /^PWA Desktop [a-z0-9]+$/);
    assert.equal(d.w.localStorage.getItem('pt.token'), 'TOKEN-PALSU');

    assert.match(teks(d.doc), /Budi Santoso/);
    assert.match($(d, '#jam').textContent, /^10:32:\d\d$/); // jam mengikuti server (WIB)
    assert.equal($(d, '#tombol-absen').textContent, 'Absen');
    assert.equal(d.doc.querySelectorAll('.tombol-absen').length, 1, 'hanya satu tombol utama');
    assert.equal($(d, '#sheet'), null, 'pilihan belum tampil sebelum tombol ditekan');
    d.w.close();
});

test('tombol Absen menampilkan tiga pilihan: Standby, Order, Izin', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    $(d, '#tombol-absen').click();
    await tunggu(() => $(d, '#sheet'), 'sheet pilihan');

    const pilihan = [...d.doc.querySelectorAll('#sheet .pilihan strong')].map((e) => e.textContent);
    assert.deepEqual(pilihan, ['Standby', 'Order', 'Izin']);
    d.w.close();
});

/* ------------------------------------------------------------------- standby */

test('Standby: langsung Kirim tanpa lokasi, selfie, atau keterangan', { skip: lewati }, async () => {
    const d = buatDunia({ geo: ['ditolak'] }); // bahkan bila GPS ditolak, Standby tetap jalan
    await keBeranda(d);
    await bukaJenis(d, 'pilih-standby');
    await tunggu(() => $(d, '#kirim-standby'), 'tombol kirim standby');

    assert.equal(d.doc.querySelectorAll('#sheet input, #sheet textarea').length, 0, 'tidak ada isian apa pun');
    $(d, '#kirim-standby').click();
    await tunggu(() => !$(d, '#sheet') && /Standby/.test($(d, '#kartu-hari-ini').textContent), 'standby tercatat');

    const req = d.server.panggilan.find((c) => c.url === '/api/aktivitas/standby');
    assert.deepEqual(Object.keys(JSON.parse(req.body)), ['kunci']);
    assert.equal(d.gps.panggilan, 0, 'lokasi tidak diminta');
    assert.equal(d.server.kameraDibuka, 0, 'kamera tidak dibuka');
    assert.match($(d, '#toast').textContent, /Tercatat/);
    d.w.close();
});

test('Kirim ditekan berulang tidak membuat permintaan ganda; kirim ulang memakai kunci yang sama', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-standby');
    await tunggu(() => $(d, '#kirim-standby'));

    d.server.gagalBerikut = true; // permintaan pertama gagal (mis. sinyal buruk)
    const tombol = $(d, '#kirim-standby');
    tombol.click();
    tombol.click(); // penekanan kedua saat proses berjalan
    await tunggu(() => !$(d, '.galat').classList.contains('hidden'), 'galat pengiriman');
    assert.equal(d.server.panggilan.filter((c) => c.url === '/api/aktivitas/standby').length, 1, 'klik ganda hanya 1 permintaan');
    assert.match($(d, '#kirim-standby').textContent, /Coba Kirim Lagi/);

    $(d, '#kirim-standby').click();
    await tunggu(() => !$(d, '#sheet'), 'berhasil setelah kirim ulang');

    const kunci = d.server.panggilan.filter((c) => c.url === '/api/aktivitas/standby').map((c) => JSON.parse(c.body).kunci);
    assert.equal(kunci.length, 2);
    assert.equal(kunci[0], kunci[1], 'kirim ulang harus memakai kunci yang sama');
    assert.equal(d.server.hariIni.length, 1);
    d.w.close();
});

/* --------------------------------------------------------------------- order */

test('Order: lokasi tujuan wajib diisi sebelum lanjut', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-order');
    await tunggu(() => $(d, '#lanjut-tujuan'));

    $(d, '#lanjut-tujuan').click();
    assert.match($(d, '#sheet .galat').textContent, /wajib diisi/);
    assert.equal(d.gps.panggilan, 0, 'lokasi belum diminta sebelum tujuan terisi');
    d.w.close();
});

test('Order: kamera TERKUNCI selama lokasi belum terbaca, ada pesan jelas dan tombol Coba Lagi', { skip: lewati }, async () => {
    const d = buatDunia({ geo: ['ditolak', 'ok'] });
    await keBeranda(d);
    await bukaJenis(d, 'pilih-order');
    await tunggu(() => $(d, '#tujuan'));
    $(d, '#tujuan').value = 'Gudang PT Maju Jaya, Cikarang';
    $(d, '#lanjut-tujuan').click();

    await tunggu(() => $(d, '#coba-lagi-lokasi'), 'tombol Coba Lagi');
    assert.match($(d, '#status-lokasi').textContent, /Izin lokasi ditolak/);
    assert.ok($(d, '#kamera-terkunci'), 'penanda kamera terkunci tampil');
    assert.equal(d.server.kameraDibuka, 0, 'kamera TIDAK dibuka');
    assert.equal($(d, 'video'), null);
    assert.equal($(d, '#ambil-foto'), null);

    // Coba Lagi berhasil -> kamera baru terbuka
    $(d, '#coba-lagi-lokasi').click();
    await tunggu(() => $(d, '#ambil-foto'), 'kamera terbuka setelah lokasi terbaca');
    assert.equal(d.server.kameraDibuka, 1);
    assert.equal($(d, '#kamera-terkunci'), null);
    d.w.close();
});

test('Order: lokasi gagal karena sinyal/timeout juga mengunci kamera', { skip: lewati }, async () => {
    for (const mode of ['tak-tersedia', 'timeout']) {
        const d = buatDunia({ geo: [mode] });
        await keBeranda(d);
        await bukaJenis(d, 'pilih-order');
        await tunggu(() => $(d, '#tujuan'));
        $(d, '#tujuan').value = 'Gudang X';
        $(d, '#lanjut-tujuan').click();
        await tunggu(() => $(d, '#coba-lagi-lokasi'));
        assert.equal(d.server.kameraDibuka, 0, `kamera tidak boleh terbuka (${mode})`);
        assert.match($(d, '#status-lokasi').textContent, mode === 'timeout' ? /terlalu lama/ : /GPS/);
        d.w.close();
    }
});

test('Order lengkap dikirim dari luar radius kantor: tujuan, lokasi aktual, selfie, kunci', { skip: lewati }, async () => {
    const d = buatDunia({ geo: ['ok'], posisi: 'jauh' });
    await keBeranda(d);
    await bukaJenis(d, 'pilih-order');
    await tunggu(() => $(d, '#tujuan'));
    $(d, '#tujuan').value = '  Gudang PT Maju Jaya,   Cikarang ';
    $(d, '#lanjut-tujuan').click();

    await tunggu(() => $(d, '#ambil-foto'), 'kamera');
    $(d, '#ambil-foto').click();
    await tunggu(() => $(d, '#sheet img'), 'pratinjau selfie');
    await tunggu(() => !$(d, '#kirim-order').disabled, 'tombol kirim aktif setelah lokasi selfie terbaca');

    assert.equal(d.server.kameraDihentikan, 1, 'kamera dimatikan setelah memotret');
    assert.match($(d, '#info-order').textContent, /Gudang PT Maju Jaya, Cikarang/);
    assert.match($(d, '#info-order').textContent, /-6\.000000, 106\.500000/);
    assert.match($(d, '#info-order').textContent, /watermark/i);

    $(d, '#kirim-order').click();
    await tunggu(() => !$(d, '#sheet') && d.server.order, 'order terkirim');

    const f = d.server.order;
    assert.equal(f.get('tujuan'), 'Gudang PT Maju Jaya, Cikarang'); // spasi dirapikan
    assert.equal(f.get('latitude'), '-6');
    assert.equal(f.get('longitude'), '106.5');
    assert.equal(f.get('akurasi'), '15');
    assert.equal(f.get('foto').type, 'image/jpeg');
    assert.match(f.get('kunci'), /^[A-Za-z0-9_-]{16,64}$/);
    assert.deepEqual([...f.keys()].sort(), ['akurasi', 'foto', 'kunci', 'latitude', 'longitude', 'tujuan'], 'tidak ada jam/waktu dari HP');
    assert.match($(d, '#kartu-hari-ini').textContent, /Order ke Gudang PT Maju Jaya, Cikarang/);
    d.w.close();
});

test('Order: Kirim ditekan dua kali mengirim satu permintaan', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-order');
    await tunggu(() => $(d, '#tujuan'));
    $(d, '#tujuan').value = 'Gudang X';
    $(d, '#lanjut-tujuan').click();
    await tunggu(() => $(d, '#ambil-foto'));
    $(d, '#ambil-foto').click();
    await tunggu(() => $(d, '#kirim-order') && !$(d, '#kirim-order').disabled);

    const tombol = $(d, '#kirim-order');
    tombol.click();
    tombol.click();
    await tunggu(() => !$(d, '#sheet'));
    assert.equal(d.server.panggilan.filter((c) => c.url === '/api/aktivitas/order').length, 1);
    d.w.close();
});

/* ---------------------------------------------------------------------- izin */

test('Izin Libur: langsung kirim', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-izin');
    await tunggu(() => $(d, '#izin-libur'));
    $(d, '#izin-libur').click();
    await tunggu(() => $(d, '#kirim-izin'));

    assert.equal($(d, '#kirim-izin').disabled, false);
    $(d, '#kirim-izin').click();
    await tunggu(() => !$(d, '#sheet') && d.server.izin);
    assert.equal(d.server.izin.get('kategori'), 'libur');
    assert.equal(d.server.izin.has('lampiran'), false);
    assert.equal(d.server.kameraDibuka, 0);
    d.w.close();
});

test('Izin Sakit: Kirim terkunci sampai SKD yang sah dipilih', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-izin');
    await tunggu(() => $(d, '#izin-sakit'));
    $(d, '#izin-sakit').click();
    await tunggu(() => $(d, '#skd'));

    assert.equal($(d, '#kirim-izin').disabled, true, 'tanpa SKD tidak bisa dikirim');
    const pilihBerkas = (berkas) => {
        Object.defineProperty($(d, '#skd'), 'files', { configurable: true, value: [berkas] });
        $(d, '#skd').dispatchEvent(new d.w.Event('change'));
    };

    pilihBerkas(new d.w.File(['isi teks biasa yang panjang'], 'catatan.txt', { type: 'text/plain' }));
    assert.match($(d, '#sheet .galat').textContent, /foto \(JPG\/PNG\) atau PDF/);
    assert.equal($(d, '#kirim-izin').disabled, true);

    pilihBerkas(new d.w.File(['%PDF-1.4 isi surat dokter'], 'skd-dokter.pdf', { type: 'application/pdf' }));
    assert.equal($(d, '#kirim-izin').disabled, false);
    assert.match($(d, '#nama-skd').textContent, /skd-dokter\.pdf/);

    $(d, '#kirim-izin').click();
    await tunggu(() => !$(d, '#sheet') && d.server.izin);
    assert.equal(d.server.izin.get('kategori'), 'sakit');
    assert.equal(d.server.izin.get('lampiran').name, 'skd-dokter.pdf');
    d.w.close();
});

test('Izin Lain-lain: wajib alasan, catatan syarat pengganti tampil, status Menunggu Keputusan', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-izin');
    await tunggu(() => $(d, '#izin-lain'));
    $(d, '#izin-lain').click();
    await tunggu(() => $(d, '#alasan'));

    assert.equal($(d, '#catatan-izin').textContent, 'Izin ini hanya dapat disetujui jika HRD sudah menemukan karyawan pengganti. Jika tidak ada pengganti, izin akan ditolak.');
    assert.equal($(d, '#kirim-izin').disabled, true);
    $(d, '#alasan').value = 'ya';
    $(d, '#alasan').dispatchEvent(new d.w.Event('input'));
    assert.equal($(d, '#kirim-izin').disabled, true, 'alasan terlalu pendek');

    $(d, '#alasan').value = 'Rumah keluarga terkena banjir';
    $(d, '#alasan').dispatchEvent(new d.w.Event('input'));
    assert.equal($(d, '#kirim-izin').disabled, false);
    $(d, '#kirim-izin').click();
    await tunggu(() => !$(d, '#sheet') && $(d, '#kartu-izin'), 'kartu status izin tampil');

    assert.equal(d.server.izin.get('alasan'), 'Rumah keluarga terkena banjir');
    assert.match($(d, '#kartu-izin').textContent, /Menunggu keputusan HRD/);
    assert.match($(d, '#kartu-izin').textContent, /Menunggu Keputusan/);
    d.w.close();
});

test('hasil keputusan izin tampil di halaman karyawan: pengganti atau alasan penolakan', { skip: lewati }, async () => {
    const izin = (status, label, tambahan) => ({ id: 1, jenis: 'izin', kategori_izin: 'lainnya', kategori_label: 'Lain-lain', status, status_label: label, tanggal: '2026-11-13', jam: '09:00:00', ...tambahan });
    const d = buatDunia({ izinLainnya: [
        izin('disetujui', 'Disetujui', { pengganti: 'Tono Wijaya' }),
        izin('ditolak', 'Ditolak', { alasan_penolakan: 'Belum ada pengganti' }),
        izin('menunggu', 'Menunggu Keputusan'),
    ] });
    await keBeranda(d);

    const kartu = $(d, '#kartu-izin').textContent;
    assert.match(kartu, /Disetujui\. Pengganti: Tono Wijaya/);
    assert.match(kartu, /Ditolak: Belum ada pengganti/);
    assert.match(kartu, /Menunggu keputusan HRD/);
    d.w.close();
});

/* ----------------------------------------------------------- Password & sesi lain */

test('Password sementara: dipaksa mendaftarkan Password sendiri sebelum beranda', { skip: lewati }, async () => {
    const d = buatDunia({ mustChange: true });
    d.jalan();
    await login(d);
    await tunggu(() => $(d, '#lama'), 'layar daftarkan Password');
    assert.match(teks(d.doc), /Buat password/);
    assert.equal($(d, '#tombol-absen'), null);

    const coba = async (baru, ulang) => {
        $(d, '#lama').value = '482913';
        $(d, '#baru').value = baru;
        $(d, '#ulang').value = ulang;
        submit(d, $(d, 'form'));
        await new Promise((r) => setTimeout(r, 30));
    };
    const panggilanPassword = () => d.server.panggilan.filter((c) => c.url === '/api/password').length;

    for (const [baru, ulang, pola] of [['12345', '12345', /8 karakter/], ['aaaaaaaa', 'aaaaaaaa', /huruf dan angka/], ['Rahasia123', 'Berbeda123', /tidak sama/]]) {
        await coba(baru, ulang);
        assert.match($(d, '.galat').textContent, pola);
    }
    assert.equal(panggilanPassword(), 0, 'Password lemah ditolak di HP sebelum dikirim');

    await coba('Rahasia123', 'Rahasia123');
    await tunggu(() => $(d, '#tombol-absen'), 'beranda setelah Password didaftarkan');
    assert.equal(d.server.mustChange, false);
    d.w.close();
});

test('sesi kedaluwarsa (401) kembali ke login dan menutup form yang terbuka', { skip: lewati }, async () => {
    const d = buatDunia();
    await keBeranda(d);
    await bukaJenis(d, 'pilih-standby');
    await tunggu(() => $(d, '#kirim-standby'));
    d.w.localStorage.setItem('pt.token', 'TOKEN-LAMA'); // token berubah/dicabut
    $(d, '#kirim-standby').click();

    await tunggu(() => $(d, '#nip') && /berakhir/.test(teks(d.doc)), 'kembali ke login');
    assert.equal($(d, '#sheet'), null);
    assert.equal(d.w.localStorage.getItem('pt.token'), null);
    d.w.close();
});

test('riwayat bulanan memuat catatan baru dan riwayat absensi lama', { skip: lewati }, async () => {
    const d = buatDunia();
    d.w.localStorage.setItem('pt.token', 'TOKEN-PALSU');
    d.jalan();
    await tunggu(() => $(d, '#tombol-absen'));

    [...d.doc.querySelectorAll('.nav button')].find((b) => /Riwayat/.test(b.textContent)).click();
    await tunggu(() => /November 2026/.test(teks(d.doc)) && /Riwayat absensi lama/.test(teks(d.doc)), 'riwayat');
    assert.match(teks(d.doc), /07:55/);
    d.w.close();
});

test('teks dari server tidak pernah diperlakukan sebagai HTML (anti XSS)', { skip: lewati }, async () => {
    const d = buatDunia();
    d.server.hariIni = [{ id: 9, jenis: 'order', jenis_label: 'order', status: 'tercatat', status_label: '<img src=x onerror=alert(1)>', tanggal: '2026-11-14', jam: '10:00:00', tujuan: '<script>alert(1)</script>', nama_lokasi: null, lokasi_aktual: null }];
    d.w.localStorage.setItem('pt.token', 'TOKEN-PALSU');
    d.jalan();
    await tunggu(() => $(d, '#kartu-hari-ini .item-riwayat'), 'daftar aktivitas');

    assert.equal(d.doc.querySelectorAll('img, script').length, 0);
    assert.match($(d, '#kartu-hari-ini').textContent, /<script>alert\(1\)<\/script>/);
    d.w.close();
});
