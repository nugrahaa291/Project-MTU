import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    aksiTersedia, bangunFormAbsen, bulanSekarang, formatJam, formatJarak, formatTanggalPendek, geserBulan, hitungOffset,
    jarakMeter, namaBulan, namaPerangkat, pesanGeolokasi, ringkasHariIni, statusRadius, ukuranTerskala,
} from '../../public/pwa/lib.js';

test('jarakMeter sama dengan hitungan server (Haversine)', () => {
    assert.equal(jarakMeter(-6.3215, 107.3376, -6.3215, 107.3376), 0);
    assert.ok(Math.abs(jarakMeter(-6.3215, 107.3376, -6.3225, 107.3376) - 111) <= 2);
    assert.ok(Math.abs(jarakMeter(0, 100, 1, 100) - 111195) <= 100);
});

test('formatJarak', () => {
    assert.equal(formatJarak(85), '85 m');
    assert.equal(formatJarak(1250), '1,3 km');
});

test('statusRadius: dalam, luar, dan pasti luar memperhitungkan akurasi', () => {
    const kantor = { latitude: -6.3215, longitude: 107.3376, radius_meter: 100 };
    const dalam = statusRadius({ latitude: -6.3215, longitude: 107.3376, akurasi: 10 }, kantor);
    assert.deepEqual([dalam.dalam, dalam.pastiLuar], [true, false]);

    // ~111 m: di luar radius, tetapi akurasi 60 m membuat posisi belum pasti di luar (111-60 < 100)
    const ragu = statusRadius({ latitude: -6.3225, longitude: 107.3376, akurasi: 60 }, kantor);
    assert.deepEqual([ragu.dalam, ragu.pastiLuar], [false, false]);

    const jauh = statusRadius({ latitude: -6.3315, longitude: 107.3376, akurasi: 20 }, kantor);
    assert.deepEqual([jauh.dalam, jauh.pastiLuar], [false, true]);

    assert.equal(statusRadius(null, kantor), null);
});

test('pesanGeolokasi', () => {
    assert.match(pesanGeolokasi(1), /Izin lokasi ditolak/);
    assert.match(pesanGeolokasi(2), /GPS/);
    assert.match(pesanGeolokasi(3), /terlalu lama/);
    assert.match(pesanGeolokasi(99), /Tidak dapat/);
});

test('waktu mengikuti server, bukan jam perangkat', () => {
    const offset = hitungOffset('2026-10-28T08:00:00+07:00', Date.parse('2026-10-28T01:00:30Z'));
    assert.equal(offset, -30000); // perangkat 30 detik lebih cepat dari server
    assert.equal(hitungOffset('bukan-tanggal', 1000), 0);
});

test('bulan: zona WIB, geser, nama', () => {
    assert.equal(bulanSekarang(new Date('2026-10-31T17:30:00Z')), '2026-11'); // 00:30 WIB tanggal 1 November
    assert.equal(bulanSekarang(new Date('2026-10-31T16:30:00Z')), '2026-10');
    assert.equal(geserBulan('2026-01', -1), '2025-12');
    assert.equal(geserBulan('2026-12', 1), '2027-01');
    assert.match(namaBulan('2026-10'), /Oktober 2026/);
    assert.match(formatTanggalPendek('2026-10-28'), /Rab/);
    assert.equal(formatJam('08:05:09'), '08:05');
    assert.equal(formatJam(null), '-');
});

test('aksiTersedia', () => {
    assert.equal(aksiTersedia(null), null);
    assert.equal(aksiTersedia({ bisa_masuk: true, bisa_pulang: false }), 'masuk');
    assert.equal(aksiTersedia({ bisa_masuk: false, bisa_pulang: true }), 'pulang');
    assert.equal(aksiTersedia({ bisa_masuk: false, bisa_pulang: false }), null);
});

test('ringkasHariIni untuk tiap kondisi', () => {
    const dasar = { hari_kerja: true, jam_masuk: '08:00', toleransi_menit: 15, absensi: null };
    assert.equal(ringkasHariIni(dasar).judul, 'Belum absen');
    assert.equal(ringkasHariIni({ ...dasar, hari_kerja: false }).judul, 'Hari ini libur');
    assert.equal(ringkasHariIni({ ...dasar, absensi: { status: 'sakit', status_label: 'Sakit', jam_masuk: null, keterangan: 'Surat dokter' } }).judul, 'Sakit');
    assert.equal(ringkasHariIni({ ...dasar, absensi: { status: 'alpa', status_label: 'Alpa', jam_masuk: null } }).warna, 'red');
    const masuk = ringkasHariIni({ ...dasar, absensi: { status: 'terlambat', jam_masuk: '08:20:00', menit_terlambat: 20, jam_pulang: null } });
    assert.equal(masuk.warna, 'amber');
    assert.match(masuk.rinci, /20 menit/);
    const selesai = ringkasHariIni({ ...dasar, absensi: { status: 'hadir', jam_masuk: '07:55:00', menit_terlambat: 0, jam_pulang: '17:05:00' } });
    assert.match(selesai.judul, /selesai/);
    assert.match(selesai.rinci, /17:05/);
});

test('bangunFormAbsen mengirim foto + koordinat + akurasi (dibulatkan), tanpa jam dari klien', () => {
    const form = bangunFormAbsen({ foto: new Blob(['x'], { type: 'image/jpeg' }), latitude: -6.3215, longitude: 107.3376, akurasi: 12.6 });
    assert.equal(form.get('latitude'), '-6.3215');
    assert.equal(form.get('longitude'), '107.3376');
    assert.equal(form.get('akurasi'), '13');
    assert.equal(form.get('foto').name, 'selfie.jpg');
    assert.deepEqual([...form.keys()].sort(), ['akurasi', 'foto', 'latitude', 'longitude']);
    assert.equal(bangunFormAbsen({ foto: new Blob(['x']), latitude: 1, longitude: 2 }).has('akurasi'), false);
});

test('namaPerangkat & ukuranTerskala', () => {
    assert.equal(namaPerangkat('Mozilla/5.0 (Linux; Android 14; Pixel 7)', 'abc123'), 'PWA Android abc123');
    assert.equal(namaPerangkat('Mozilla/5.0 (iPhone; CPU iPhone OS 17)', 'x'), 'PWA iOS x');
    assert.deepEqual(ukuranTerskala(2000, 1000, 1000), { lebar: 1000, tinggi: 500 });
    assert.deepEqual(ukuranTerskala(640, 480, 1024), { lebar: 640, tinggi: 480 }); // tidak diperbesar
});
