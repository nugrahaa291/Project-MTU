import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ApiError, buatApi } from '../../public/pwa/api.js';

const balas = (status, data) => async () => ({ ok: status >= 200 && status < 300, status, json: async () => data });

test('mengirim token Bearer dan header Accept JSON', async () => {
    let diterima;
    const api = buatApi({ ambilToken: () => 'T0K3N', fetchFn: async (url, opsi) => { diterima = { url, opsi }; return balas(200, { ok: 1 })(); } });
    await api.ringkasan();
    assert.equal(diterima.url, '/api/aktivitas/ringkasan');
    assert.equal(diterima.opsi.headers.Authorization, 'Bearer T0K3N');
    assert.equal(diterima.opsi.headers.Accept, 'application/json');
});

test('login memakai NIP + Password (JSON) dan tanpa token', async () => {
    let opsi;
    const api = buatApi({ ambilToken: () => null, fetchFn: async (_u, o) => { opsi = o; return balas(200, { token: 'x' })(); } });
    await api.login('MTU-0123', '482913', 'PWA Android x');
    assert.equal(opsi.headers.Authorization, undefined);
    assert.deepEqual(JSON.parse(opsi.body), { nip: 'MTU-0123', password: '482913', device_name: 'PWA Android x' });
});

test('standby hanya mengirim kunci; order dan izin mengirim multipart tanpa Content-Type manual', async () => {
    const dipanggil = [];
    const api = buatApi({ ambilToken: () => 't', fetchFn: async (u, o) => { dipanggil.push({ u, o }); return balas(201, {})(); } });

    await api.standby('kunci-standby-0123456789');
    assert.equal(dipanggil[0].u, '/api/aktivitas/standby');
    assert.deepEqual(JSON.parse(dipanggil[0].o.body), { kunci: 'kunci-standby-0123456789' });

    const form = new FormData();
    form.append('a', '1');
    await api.order(form);
    await api.izin(form);
    assert.equal(dipanggil[1].u, '/api/aktivitas/order');
    assert.equal(dipanggil[2].u, '/api/aktivitas/izin');
    for (const i of [1, 2]) {
        assert.equal(dipanggil[i].o.method, 'POST');
        assert.equal(dipanggil[i].o.body, form);
        assert.equal(dipanggil[i].o.headers['Content-Type'], undefined);
    }
});

test('daftarkan Password dan riwayat bulanan', async () => {
    const dipanggil = [];
    const api = buatApi({ ambilToken: () => 't', fetchFn: async (u, o) => { dipanggil.push({ u, o }); return balas(200, {})(); } });
    await api.gantiPassword({ current_password: '482913', password: '739418', password_confirmation: '739418' });
    await api.riwayat('2026-11');
    assert.equal(dipanggil[0].u, '/api/password');
    assert.equal(dipanggil[0].o.method, 'PUT');
    assert.equal(dipanggil[1].u, '/api/aktivitas?bulan=2026-11');
});

test('401 dengan token memicu saatTidakSah; tanpa token (login salah) tidak', async () => {
    let dipicu = 0;
    const denganToken = buatApi({ ambilToken: () => 't', saatTidakSah: () => dipicu++, fetchFn: balas(401, { message: 'Unauthenticated.' }) });
    await assert.rejects(denganToken.me(), (e) => e instanceof ApiError && e.status === 401);
    assert.equal(dipicu, 1);

    const tanpaToken = buatApi({ ambilToken: () => null, saatTidakSah: () => dipicu++, fetchFn: balas(401, { message: 'NIP atau password salah.' }) });
    await assert.rejects(tanpaToken.login('a', 'b', 'c'), /NIP atau password salah/);
    assert.equal(dipicu, 1);
});

test('kesalahan jaringan menjadi ApiError status 0', async () => {
    const api = buatApi({ ambilToken: () => 't', fetchFn: async () => { throw new TypeError('fail'); } });
    await assert.rejects(api.me(), (e) => e.status === 0 && e.kode === 'jaringan' && /koneksi/.test(e.message));
});

test('422 menampilkan pesan field pertama; kode mesin dan 429 dibawa', async () => {
    const v = buatApi({ ambilToken: () => 't', fetchFn: balas(422, { message: 'x', errors: { foto: ['Foto selfie wajib diambil.'] } }) });
    await assert.rejects(v.order(new FormData()), (e) => e.message === 'Foto selfie wajib diambil.');

    const k = buatApi({ ambilToken: () => 't', fetchFn: balas(422, { message: 'Alasan izin wajib diisi (minimal 5 karakter).', kode: 'alasan_wajib' }) });
    await assert.rejects(k.izin(new FormData()), (e) => e.kode === 'alasan_wajib' && e.status === 422);

    const t = buatApi({ ambilToken: () => null, fetchFn: balas(429, { message: 'Terlalu banyak percobaan login.', kode: 'terlalu_banyak_percobaan' }) });
    await assert.rejects(t.login('a', 'b', 'c'), (e) => e.status === 429 && e.kode === 'terlalu_banyak_percobaan');
});

test('balasan non-JSON tidak membuat crash', async () => {
    const api = buatApi({ ambilToken: () => 't', fetchFn: async () => ({ ok: false, status: 502, json: async () => { throw new Error('bukan json'); } }) });
    await assert.rejects(api.me(), (e) => e.status === 502 && /502/.test(e.message));
});
