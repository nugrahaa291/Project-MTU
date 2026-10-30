import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ApiError, buatApi } from '../../public/pwa/api.js';

const balas = (status, data) => async () => ({ ok: status >= 200 && status < 300, status, json: async () => data });

test('mengirim token Bearer dan header Accept JSON', async () => {
    let diterima;
    const api = buatApi({ ambilToken: () => 'T0K3N', fetchFn: async (url, opsi) => { diterima = { url, opsi }; return balas(200, { ok: 1 })(); } });
    await api.hariIni();
    assert.equal(diterima.url, '/api/absensi/hari-ini');
    assert.equal(diterima.opsi.headers.Authorization, 'Bearer T0K3N');
    assert.equal(diterima.opsi.headers.Accept, 'application/json');
});

test('multipart: tidak menyetel Content-Type sendiri (boundary diatur browser)', async () => {
    let opsi;
    const api = buatApi({ ambilToken: () => 't', fetchFn: async (_u, o) => { opsi = o; return balas(201, {})(); } });
    const form = new FormData();
    form.append('a', '1');
    await api.masuk(form);
    assert.equal(opsi.method, 'POST');
    assert.equal(opsi.body, form);
    assert.equal(opsi.headers['Content-Type'], undefined);
});

test('login memakai JSON dan tanpa token', async () => {
    let opsi;
    const api = buatApi({ ambilToken: () => null, fetchFn: async (_u, o) => { opsi = o; return balas(200, { token: 'x' })(); } });
    await api.login('a@b.c', 'pw', 'PWA Android x');
    assert.equal(opsi.headers.Authorization, undefined);
    assert.deepEqual(JSON.parse(opsi.body), { email: 'a@b.c', password: 'pw', device_name: 'PWA Android x' });
});

test('401 dengan token memicu saatTidakSah; tanpa token (salah password) tidak', async () => {
    let dipicu = 0;
    const denganToken = buatApi({ ambilToken: () => 't', saatTidakSah: () => dipicu++, fetchFn: balas(401, { message: 'Unauthenticated.' }) });
    await assert.rejects(denganToken.me(), (e) => e instanceof ApiError && e.status === 401);
    assert.equal(dipicu, 1);

    const tanpaToken = buatApi({ ambilToken: () => null, saatTidakSah: () => dipicu++, fetchFn: balas(401, { message: 'Email atau password salah.' }) });
    await assert.rejects(tanpaToken.login('a', 'b', 'c'), /Email atau password salah/);
    assert.equal(dipicu, 1);
});

test('kesalahan jaringan menjadi ApiError status 0', async () => {
    const api = buatApi({ ambilToken: () => 't', fetchFn: async () => { throw new TypeError('fail'); } });
    await assert.rejects(api.me(), (e) => e.status === 0 && e.kode === 'jaringan' && /koneksi/.test(e.message));
});

test('kesalahan validasi 422 menampilkan pesan field pertama; kode mesin dibawa', async () => {
    const v = buatApi({ ambilToken: () => 't', fetchFn: balas(422, { message: 'x', errors: { foto: ['Foto wajib diisi.'] } }) });
    await assert.rejects(v.masuk(new FormData()), (e) => e.message === 'Foto wajib diisi.');

    const k = buatApi({ ambilToken: () => 't', fetchFn: balas(422, { message: 'Anda berada 1112 m dari Kantor Pusat.', kode: 'di_luar_radius' }) });
    await assert.rejects(k.masuk(new FormData()), (e) => e.kode === 'di_luar_radius' && e.status === 422);
});

test('balasan non-JSON tidak membuat crash', async () => {
    const api = buatApi({ ambilToken: () => 't', fetchFn: async () => ({ ok: false, status: 502, json: async () => { throw new Error('bukan json'); } }) });
    await assert.rejects(api.me(), (e) => e.status === 502 && /502/.test(e.message));
});
