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

/* ============================ alur baru: Standby / Order / Izin ============================ */
import { bangunFormIzin, bangunFormOrder, buatKunci, formatKoordinat, pesanPin, pinValid, ringkasKegiatan, validasiLampiran } from '../../public/pwa/lib.js';

test('buatKunci: format aman untuk server dan selalu unik', () => {
    const kunci = new Set(Array.from({ length: 200 }, () => buatKunci()));
    assert.equal(kunci.size, 200);
    for (const k of kunci) assert.match(k, /^[A-Za-z0-9_-]{16,64}$/);
});

test('pinValid sama dengan aturan server', () => {
    for (const ok of ['482913', '739418', '102938', '905173']) assert.equal(pinValid(ok), true, ok);
    for (const buruk of ['', '12345', '1234567', 'abcdef', '000000', '111111', '123456', '234567', '567890', '890123', '012345', '654321', '987654', '121212', '123123', '112112']) {
        assert.equal(pinValid(buruk), false, `"${buruk}" harus ditolak`);
    }
    assert.match(pesanPin('12345'), /6 digit/);
    assert.match(pesanPin('123456'), /mudah ditebak/);
    assert.equal(pesanPin('739418'), null);
});

test('validasiLampiran: hanya foto/PDF, tidak kosong, maksimal 5 MB', () => {
    const berkas = (type, size) => ({ type, size });
    assert.match(validasiLampiran(null), /Pilih berkas/);
    assert.equal(validasiLampiran(berkas('application/pdf', 2048)), null);
    assert.equal(validasiLampiran(berkas('image/jpeg', 400000)), null);
    assert.equal(validasiLampiran(berkas('image/png', 400000)), null);
    assert.match(validasiLampiran(berkas('text/plain', 2048)), /JPG\/PNG\) atau PDF/);
    assert.match(validasiLampiran(berkas('application/x-php', 2048)), /JPG\/PNG\) atau PDF/);
    assert.match(validasiLampiran(berkas('application/pdf', 6 * 1024 * 1024)), /maksimal 5 MB/);
    assert.match(validasiLampiran(berkas('application/pdf', 0)), /kosong/);
});

test('bangunFormOrder: tujuan, lokasi aktual, selfie, dan kunci; tanpa jam dari HP', () => {
    const form = bangunFormOrder({ kunci: 'kunci-order-0123456789', tujuan: 'Gudang X', foto: new Blob(['x'], { type: 'image/jpeg' }), latitude: -6.3019925, longitude: 107.3054416, akurasi: 12.4 });
    assert.deepEqual([...form.keys()].sort(), ['akurasi', 'foto', 'kunci', 'latitude', 'longitude', 'tujuan']);
    assert.equal(form.get('tujuan'), 'Gudang X');
    assert.equal(form.get('latitude'), '-6.3019925');
    assert.equal(form.get('akurasi'), '12');
    assert.equal(form.get('foto').name, 'selfie.jpg');
});

test('bangunFormIzin: lampiran dan alasan hanya bila ada', () => {
    const libur = bangunFormIzin({ kunci: 'k-0123456789abcdef', kategori: 'libur' });
    assert.deepEqual([...libur.keys()].sort(), ['kategori', 'kunci']);

    const sakit = bangunFormIzin({ kunci: 'k-0123456789abcdef', kategori: 'sakit', lampiran: new File(['%PDF-'], 'skd.pdf', { type: 'application/pdf' }) });
    assert.equal(sakit.get('lampiran').name, 'skd.pdf');

    const lain = bangunFormIzin({ kunci: 'k-0123456789abcdef', kategori: 'lainnya', alasan: 'Musibah keluarga' });
    assert.equal(lain.get('alasan'), 'Musibah keluarga');
    assert.equal(lain.has('lampiran'), false);
});

test('ringkasKegiatan untuk tiap jenis dan status', () => {
    assert.equal(ringkasKegiatan({ jenis: 'standby', status: 'tercatat' }).judul, 'Standby');

    const order = ringkasKegiatan({ jenis: 'order', status: 'tercatat', tujuan: 'Gudang X', nama_lokasi: 'Jalan Tuparev' });
    assert.equal(order.judul, 'Order ke Gudang X');
    assert.match(order.rinci, /Jalan Tuparev/);
    assert.match(ringkasKegiatan({ jenis: 'order', status: 'tercatat', tujuan: 'G', nama_lokasi: null, lokasi_aktual: { latitude: -6.3, longitude: 107.3 } }).rinci, /-6\.300000, 107\.300000/);

    assert.match(ringkasKegiatan({ jenis: 'izin', kategori_label: 'Lain-lain', kategori_izin: 'lainnya', status: 'menunggu' }).rinci, /Menunggu keputusan admin/);
    const setuju = ringkasKegiatan({ jenis: 'izin', kategori_label: 'Lain-lain', kategori_izin: 'lainnya', status: 'disetujui', pengganti: 'Budi' });
    assert.match(setuju.rinci, /Pengganti: Budi/);
    assert.equal(setuju.warna, 'blue');
    const tolak = ringkasKegiatan({ jenis: 'izin', kategori_label: 'Lain-lain', kategori_izin: 'lainnya', status: 'ditolak', alasan_penolakan: 'Tidak ada pengganti' });
    assert.match(tolak.rinci, /Ditolak: Tidak ada pengganti/);
    assert.equal(tolak.warna, 'red');
    assert.match(ringkasKegiatan({ jenis: 'izin', kategori_label: 'Sakit', kategori_izin: 'sakit', status: 'tercatat' }).rinci, /SKD/);
});

test('formatKoordinat', () => {
    assert.equal(formatKoordinat(-6.3019926, 107.3054416), '-6.301993, 107.305442'); // 6 desimal (~0,1 m)
});
