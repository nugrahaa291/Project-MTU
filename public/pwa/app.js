// Aplikasi absensi (PWA). Vanilla JS tanpa library: tidak ada skrip pihak ketiga yang bisa mengakses token.
// Alur: login (NIP + Password) -> tombol Absen -> pilih Standby / Order / Izin.
import { buatApi } from './api.js';
import {
    bangunFormIzin, bangunFormOrder, buatKunci, bulanSekarang, formatJam, formatJamLengkap, formatKoordinat,
    formatTanggalPanjang, formatTanggalPendek, geserBulan, hitungOffset, namaBulan, namaPerangkat, pesanGeolokasi,
    pesanPassword, ringkasKegiatan, ukuranTerskala, validasiLampiran, warnaStatus,
} from './lib.js';

const KUNCI_TOKEN = 'pt.token';
const KUNCI_PERANGKAT = 'pt.perangkat';
const CATATAN_IZIN_LAIN = 'Izin ini hanya dapat disetujui jika HRD sudah menemukan karyawan pengganti. Jika tidak ada pengganti, izin akan ditolak.';

/* ------------------------------------------------------------------ util */
const simpan = {
    ambil: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* mode privat */ } },
    hapus: (k) => { try { localStorage.removeItem(k); } catch { /* abaikan */ } },
};

/** Pembuat elemen. Teks selalu lewat text node (aman dari XSS), tidak pernah innerHTML. */
function h(tag, props = {}, ...anak) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') el.className = v;
        else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
        else el.setAttribute(k, v === true ? '' : v);
    }
    for (const a of anak.flat()) {
        if (a === null || a === undefined || a === false) continue;
        el.append(a);
    }
    return el;
}

function toast(pesan, jenis = '') {
    const wadah = document.getElementById('toast');
    const el = h('div', { class: `toast ${jenis}` }, pesan);
    wadah.append(el);
    setTimeout(() => el.remove(), 5000);
}

function idPerangkat() {
    let id = simpan.ambil(KUNCI_PERANGKAT);
    if (!id) {
        id = Math.random().toString(36).slice(2, 8);
        simpan.set(KUNCI_PERANGKAT, id);
    }
    return id;
}

/* ----------------------------------------------------------------- state */
const S = {
    layar: 'login', // login | ganti | utama
    tab: 'beranda', // beranda | riwayat | akun
    profil: null,
    ringkasan: null,
    offset: 0, // selisih jam server - jam perangkat
    bulan: null,
    riwayat: null,
    online: navigator.onLine,
    pasang: null,
    notice: '',
    mengirim: false,
};

const api = buatApi({
    ambilToken: () => simpan.ambil(KUNCI_TOKEN),
    saatTidakSah: () => keluarLokal('Sesi Anda berakhir. Silakan masuk kembali.'),
});

const $app = document.getElementById('app');

/* --------------------------------------------------------------- data */
async function muatRingkasan() {
    S.ringkasan = await api.ringkasan();
    S.offset = hitungOffset(S.ringkasan.waktu_server, Date.now());
}

async function muatRiwayat() {
    S.bulan ||= bulanSekarang(new Date(Date.now() + S.offset));
    S.riwayat = null;
    render();
    try {
        S.riwayat = await api.riwayat(S.bulan);
    } catch (e) {
        toast(e.message, 'err');
        S.riwayat = { ringkasan: {}, data: [], absensi_lama: [] };
    }
    if (S.layar === 'utama' && S.tab === 'riwayat') render();
}

function keluarLokal(pesan = '') {
    tutupSheet(true);
    simpan.hapus(KUNCI_TOKEN);
    Object.assign(S, { layar: 'login', tab: 'beranda', profil: null, ringkasan: null, riwayat: null, bulan: null, notice: pesan, mengirim: false });
    render();
}

async function masukKeUtama() {
    S.layar = 'utama';
    S.tab = 'beranda';
    try {
        await muatRingkasan();
    } catch (e) {
        if (e.kode === 'wajib_ganti_password') return (S.layar = 'ganti'), render();
        if (e.kode === 'akun_nonaktif') return keluarLokal(e.message);
        toast(e.message, 'err');
    }
    render();
}

/* ------------------------------------------------------------- layar: login */
function layarLogin() {
    const galat = h('div', { class: 'galat hidden', role: 'alert' });
    const nip = h('input', { type: 'text', id: 'nip', autocomplete: 'username', required: true, autocapitalize: 'characters', spellcheck: 'false' });
    const password = h('input', { type: 'password', id: 'password', autocomplete: 'current-password', required: true });
    const tombol = h('button', { class: 'tombol', type: 'submit' }, 'Masuk');

    const form = h('form', {
        novalidate: true,
        onsubmit: async (ev) => {
            ev.preventDefault();
            galat.classList.add('hidden');
            if (!nip.value.trim() || !password.value) {
                galat.textContent = 'NIP dan password wajib diisi.';
                return galat.classList.remove('hidden');
            }
            tombol.disabled = true;
            tombol.textContent = 'Memeriksa...';
            try {
                const res = await api.login(nip.value.trim(), password.value, namaPerangkat(navigator.userAgent, idPerangkat()));
                simpan.set(KUNCI_TOKEN, res.token);
                S.profil = res.karyawan;
                S.notice = '';
                if (res.must_change_password) {
                    S.layar = 'ganti';
                    return render();
                }
                await masukKeUtama(); // langsung ke halaman Absen
            } catch (e) {
                galat.textContent = e.message;
                galat.classList.remove('hidden');
                tombol.disabled = false;
                tombol.textContent = 'Masuk';
            }
        },
    }, h('label', { for: 'nip' }, 'NIP'), nip, h('label', { for: 'password' }, 'Password'), password, tombol, galat);

    return h('div', { class: 'layar-gelap' },
        h('div', { class: 'merek' }, 'MTU', h('span', {}, '.')),
        h('div', { class: 'kartu' },
            h('h1', {}, 'Masuk'),
            h('p', { class: 'muted' }, 'Gunakan NIP dan password. Jika masih memakai PIN lama, masukkan PIN itu sekali untuk membuat password baru.'),
            S.notice ? h('div', { class: 'info' }, S.notice) : null,
            form),
    );
}

/* ----------------------------------------------------- layar: daftarkan Password */
function layarGanti() {
    const galat = h('div', { class: 'galat hidden', role: 'alert' });
    const lama = h('input', { type: 'password', id: 'lama', autocomplete: 'current-password', required: true });
    const baru = h('input', { type: 'password', id: 'baru', autocomplete: 'new-password', required: true, maxlength: '72' });
    const ulang = h('input', { type: 'password', id: 'ulang', autocomplete: 'new-password', required: true, maxlength: '72' });
    const tombol = h('button', { class: 'tombol', type: 'submit' }, 'Simpan Password');

    const form = h('form', {
        novalidate: true,
        onsubmit: async (ev) => {
            ev.preventDefault();
            galat.classList.add('hidden');
            const salah = pesanPassword(baru.value) || (baru.value !== ulang.value ? 'Konfirmasi Password tidak sama.' : null);
            if (salah) {
                galat.textContent = salah;
                return galat.classList.remove('hidden');
            }
            tombol.disabled = true;
            try {
                await api.gantiPassword({ current_password: lama.value, password: baru.value, password_confirmation: ulang.value });
                toast('Password berhasil didaftarkan.', 'ok');
                await masukKeUtama();
            } catch (e) {
                galat.textContent = e.message;
                galat.classList.remove('hidden');
                tombol.disabled = false;
            }
        },
    },
    h('label', { for: 'lama' }, 'Password saat ini atau PIN lama'), lama,
    h('label', { for: 'baru' }, 'Password baru'), baru,
    h('label', { for: 'ulang' }, 'Ulangi Password baru'), ulang,
    tombol, galat);

    return h('div', { class: 'layar-gelap' },
        h('div', { class: 'merek' }, 'MTU', h('span', {}, '.')),
        h('div', { class: 'kartu' },
            h('h1', {}, 'Buat password'),
            h('p', { class: 'muted' }, 'Gunakan minimal 8 karakter dengan huruf dan angka. Password ini berlaku untuk Absen dan portal Pelamar.'),
            form,
            h('button', { class: 'tombol garis kecil', type: 'button', onclick: () => keluarLokal() }, 'Keluar')),
    );
}

// Ikon garis memakai geometri lokal agar tetap tersedia saat offline.
const bentukIkon = {
    beranda: 'M3 10 12 3l9 7v11h-6v-7H9v7H3z',
    riwayat: 'M3 11a9 9 0 1 1 2 7M3 5v6h6m3-4v5l3 2',
    akun: 'M16 8a4 4 0 1 1-8 0 4 4 0 1 1 8 0zM4 21v-2a8 8 0 0 1 16 0v2',
    standby: 'M8 5v14m8-14v14',
    order: 'M2 5h12v12H2zM14 9h4l4 5v3h-8M8 18a2 2 0 1 1-4 0 2 2 0 1 1 4 0m12 0a2 2 0 1 1-4 0 2 2 0 1 1 4 0',
    izin: 'M4 5h16v16H4zm0 5h16M8 3v4m8-4v4',
    sakit: 'M12 5v14m-7-7h14',
    lainnya: 'M4 4h16v13H9l-5 4zM8 9h8m-8 4h5',
    absen: 'M3 8V4h5m8 0h5v4m0 8v4h-5m-8 0H3v-4m4-4 3 3 6-6',
};
function ikon(nama) {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'ikon');
    const path = document.createElementNS(ns, 'path');
    path.setAttribute('d', bentukIkon[nama]);
    svg.append(path);
    return svg;
}

function barisKegiatan(k) {
    const t = ringkasKegiatan(k);
    return h('div', { class: 'item-riwayat aktivitas', 'data-jenis': k.jenis },
        h('time', {}, formatJam(k.jam)),
        h('div', { class: 'rincian' },
            h('strong', {}, t.judul),
            t.rinci ? h('div', { class: 'j' }, t.rinci) : null,
            h('span', { class: `lencana ${t.warna}` }, k.status_label)));
}

/* ----------------------------------------------------------- layar: utama */
function tabBeranda() {
    const r = S.ringkasan;
    const sekarang = new Date(Date.now() + S.offset);

    const hariIni = r?.hari_ini ?? [];
    const daftarHariIni = hariIni.length
        ? hariIni.map(barisKegiatan)
        : h('div', { class: 'muted' }, 'Belum ada aktivitas. Ketuk Absen untuk mencatat kegiatan pertama.');

    const izin = (r?.izin_lainnya ?? []).slice(0, 3);

    return h('div', {},
        h('div', { class: 'kartu kartu-jam' },
            h('div', { class: 'tgl' }, formatTanggalPanjang(sekarang)),
            h('div', { class: 'jam', id: 'jam' }, formatJamLengkap(sekarang)),
            h('div', { class: 'muted' }, 'Waktu WIB (mengikuti server)')),
        h('div', { class: 'kartu', id: 'kartu-hari-ini' }, h('div', { class: 'judul-baris' }, h('h2', {}, 'Aktivitas hari ini'), h('span', { class: 'muted' }, `${hariIni.length} catatan`)), daftarHariIni),
        izin.length
            ? h('div', { class: 'kartu', id: 'kartu-izin' },
                h('div', { class: 'muted judul-kartu' }, 'Pengajuan izin Lain-lain'),
                izin.map((k) => {
                    const t = ringkasKegiatan(k);
                    return h('div', { class: 'item-riwayat' },
                        h('div', {}, h('div', { class: 't' }, formatTanggalPendek(k.tanggal)), h('div', { class: 'j' }, t.rinci)),
                        h('span', { class: `lencana ${t.warna}` }, k.status_label));
                }))
            : null,

    );
}

function tabRiwayat() {
    const bulan = S.bulan;
    const saatIni = bulanSekarang(new Date(Date.now() + S.offset));
    const nav = h('div', { class: 'bulan-bar' },
        h('button', { class: 'tombol garis kecil', type: 'button', onclick: () => { S.bulan = geserBulan(bulan, -1); muatRiwayat(); } }, '‹'),
        h('strong', {}, namaBulan(bulan)),
        h('button', { class: 'tombol garis kecil', type: 'button', disabled: bulan >= saatIni, onclick: () => { S.bulan = geserBulan(bulan, 1); muatRiwayat(); } }, '›'));

    if (!S.riwayat) return h('div', {}, nav, h('div', { class: 'kartu muted' }, 'Memuat riwayat...'));

    const chip = Object.entries(S.riwayat.ringkasan || {}).map(([j, n]) => h('span', { class: 'lencana slate' }, `${j} ${n}`));
    const perTanggal = new Map();
    for (const k of S.riwayat.data || []) {
        if (!perTanggal.has(k.tanggal)) perTanggal.set(k.tanggal, []);
        perTanggal.get(k.tanggal).push(barisKegiatan(k));
    }
    const baris = [...perTanggal].map(([tanggal, daftar]) => h('section', {}, h('h3', { class: 'tanggal-grup' }, formatTanggalPendek(tanggal)), daftar));
    const lama = (S.riwayat.absensi_lama || []).map((a) => h('div', { class: 'item-riwayat' },
        h('div', {}, h('div', { class: 't' }, formatTanggalPendek(a.tanggal)),
            h('div', { class: 'j' }, a.jam_masuk ? `Masuk ${formatJam(a.jam_masuk)} · Pulang ${formatJam(a.jam_pulang)}` : (a.keterangan || 'Dicatat HRD'))),
        h('span', { class: `lencana ${warnaStatus(a.status)}` }, a.status_label)));

    return h('div', {}, nav, chip.length ? h('div', { class: 'ringkas' }, chip) : null,
        h('div', { class: 'kartu' }, baris.length ? baris : h('div', { class: 'muted' }, 'Belum ada catatan pada bulan ini.')),
        lama.length ? h('div', { class: 'kartu' }, h('div', { class: 'muted judul-kartu' }, 'Riwayat absensi lama (masuk/pulang)'), lama) : null);
}

function tabAkun() {
    const p = S.profil;
    const ios = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    const terpasang = window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone;

    return h('div', {},
        h('div', { class: 'kartu' },
            h('h2', {}, p?.nama || '-'),
            h('div', { class: 'muted' }, `${p?.jabatan || ''} · NIP ${p?.nip || '-'}`)),
        !terpasang && S.pasang
            ? h('div', { class: 'kartu' }, h('strong', {}, 'Pasang aplikasi'), h('p', { class: 'muted' }, 'Tambahkan ke layar utama agar terbuka seperti aplikasi biasa.'),
                h('button', { class: 'tombol', type: 'button', onclick: async () => { S.pasang.prompt(); await S.pasang.userChoice; S.pasang = null; render(); } }, 'Pasang di layar utama'))
            : null,
        !terpasang && ios
            ? h('div', { class: 'kartu' }, h('strong', {}, 'Pasang di iPhone'), h('p', { class: 'muted' }, 'Ketuk tombol Bagikan di Safari, lalu pilih "Tambah ke Layar Utama".'))
            : null,
        h('div', { class: 'kartu' },
            h('button', { class: 'tombol garis', type: 'button', onclick: () => { S.layar = 'ganti'; render(); } }, 'Ganti Password'),
            h('button', { class: 'tombol gelap', type: 'button', onclick: async () => { try { await api.logout(); } catch { /* tetap keluar */ } keluarLokal(); } }, 'Keluar')),
    );
}

function layarUtama() {
    const isi = S.tab === 'beranda' ? tabBeranda() : S.tab === 'riwayat' ? tabRiwayat() : tabAkun();
    const tab = (id, ikon, label) => h('button', { class: S.tab === id ? 'aktif' : '', type: 'button', onclick: () => { S.tab = id; if (id === 'riwayat') muatRiwayat(); else render(); } }, h('span', { class: 'ik' }, ikon), label);

    return h('div', {},
        h('div', { class: `offline ${S.online ? 'hidden' : ''}`, id: 'banner-offline' }, 'Tidak ada koneksi internet. Absen membutuhkan koneksi.'),
        h('div', { class: 'atas' },
            h('div', { class: 'baris' },
                h('div', {}, h('div', { class: 'nama' }, S.profil?.nama || 'Karyawan'), h('div', { class: 'jabatan' }, S.profil?.jabatan || '')),
                h('div', { class: 'logo' }, 'Absensi', h('span', {}, '.')))),
        h('div', { class: 'konten' }, isi, S.tab === 'beranda' ? h('div', { class: 'tombol-wadah' },
            h('button', { class: 'tombol besar tombol-absen', id: 'tombol-absen', type: 'button', disabled: !S.online || !S.ringkasan, onclick: bukaPilihan }, 'Absen')) : null),
        h('nav', { class: 'nav', 'aria-label': 'Navigasi utama' }, tab('beranda', '⌂', 'Beranda'), tab('riwayat', '☰', 'Riwayat'), tab('akun', '☺', 'Akun')),
    );
}

/* ================================================================== sheet */
let sheet = null; // { el, isi, judul, bersih: [fungsi] }

function bukaSheet(judul) {
    tutupSheet(true);
    const h2 = h('h2', { id: 'judul-sheet', tabindex: '-1' }, judul);
    const isi = h('div', { class: 'sheet-isi' });
    const tutup = h('button', { class: 'sheet-tutup', type: 'button', 'aria-label': 'Tutup', onclick: () => tutupSheet() }, '×');
    const el = h('div', { class: 'sheet', id: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'judul-sheet' },
        h('div', { class: 'panel' }, h('div', { class: 'sheet-kepala' }, h2, tutup), isi));
    const pemicu = document.activeElement;
    document.getElementById('app').inert = true;
    document.body.append(el);
    h2.focus();
    el.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') tutupSheet();
        if (e.key !== 'Tab') return;
        const fokus = [...el.querySelectorAll('button:enabled, input:enabled, textarea')].filter(node => node.getClientRects().length);
        const tujuan = e.shiftKey ? fokus.at(-1) : fokus[0];
        if (document.activeElement === (e.shiftKey ? fokus[0] : fokus.at(-1)) || (e.shiftKey && document.activeElement === h2)) {
            e.preventDefault();
            tujuan?.focus();
        }
    });
    sheet = { el, isi, pemicu, judul: h2, bersih: [] };
    return sheet;
}

/** paksa = penutupan oleh program (setelah sukses). Pengguna tidak bisa menutup saat pengiriman berjalan. */
function tutupSheet(paksa = false) {
    if (!sheet || (S.mengirim && !paksa)) return;
    for (const f of sheet.bersih) { try { f(); } catch { /* abaikan */ } }
    sheet.el.remove();
    document.getElementById('app').inert = false;
    sheet.pemicu?.focus();
    sheet = null;
}

function isiSheet(...nodes) {
    if (!sheet) return;
    sheet.isi.replaceChildren(...nodes.flat().filter(Boolean));
    sheet.judul.focus();
}

/** Satu penekanan Kirim: tombol dikunci selama proses; kunci tetap sama bila perlu kirim ulang. */
async function kirim({ tombol, galat, jalankan }) {
    if (S.mengirim) return;
    S.mengirim = true;
    galat.classList.add('hidden');
    tombol.disabled = true;
    tombol.replaceChildren(h('span', { class: 'pemuat' }));
    try {
        const res = await jalankan();
        S.mengirim = false;
        tutupSheet(true);
        toast(res.message, res.duplikat ? '' : 'ok');
        try { await muatRingkasan(); } catch { /* ditangani saatTidakSah */ }
        render();
    } catch (e) {
        S.mengirim = false;
        if (e.kode === 'wajib_ganti_password') { tutupSheet(true); S.layar = 'ganti'; return render(); }
        if (e.kode === 'akun_nonaktif') return keluarLokal(e.message);
        if (!sheet) return;
        galat.textContent = e.message;
        galat.classList.remove('hidden');
        tombol.disabled = false;
        tombol.textContent = 'Coba Kirim Lagi';
    }
}

const kotakGalat = () => h('div', { class: 'galat hidden', role: 'alert' });

/* ------------------------------------------------------------ pilih jenis */
function pilihan(id, simbol, judul, isi, aksi) {
    return h('button', { class: 'pilihan', id, type: 'button', onclick: aksi },
        ikon(simbol), h('span', {}, h('strong', {}, judul), h('span', { class: 'muted' }, isi)));
}

function bukaPilihan() {
    if (!S.online) return toast('Tidak ada koneksi internet.', 'err');
    bukaSheet('Pilih jenis absen');
    isiSheet(
        h('div', { class: 'pilihan-daftar' },
            pilihan('pilih-standby', 'standby', 'Standby', 'Catat bahwa Anda siap bertugas.', alurStandby),
            pilihan('pilih-order', 'order', 'Order', 'Catat tujuan dan pengantaran Anda.', alurOrder),
            pilihan('pilih-izin', 'izin', 'Izin', 'Libur, sakit, atau keperluan lain.', alurIzin)),
    );
}

/* ---------------------------------------------------------------- standby */
function alurStandby() {
    bukaSheet('Standby');
    const kunci = buatKunci();
    const galat = kotakGalat();
    const tombol = h('button', { class: 'tombol', id: 'kirim-standby', type: 'button' }, 'Kirim');
    tombol.onclick = () => kirim({ tombol, galat, jalankan: () => api.standby(kunci) });

    isiSheet(
        h('p', { class: 'muted' }, 'Status Standby dicatat atas nama akun yang sedang login, dengan waktu dari server. Tidak perlu lokasi, selfie, atau keterangan.'),
        h('div', { class: 'ringkas-akun' }, `${S.profil?.nama || ''} · NIP ${S.profil?.nip || ''}`),
        galat,
        h('div', { class: 'aksi' }, h('button', { class: 'tombol garis', type: 'button', onclick: bukaPilihan }, 'Kembali'), tombol),
    );
}

/* ------------------------------------------------------------------ order */
/** Lokasi terkini dari GPS HP. Menolak dengan {pesan, kode} agar kamera tetap terkunci. */
function ambilPosisi({ umurMaks = 0, batas = 20000 } = {}) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) return reject({ pesan: 'Browser ini tidak mendukung lokasi.', kode: 0 });
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, akurasi: p.coords.accuracy }),
            (e) => reject({ pesan: pesanGeolokasi(e.code), kode: e.code }),
            { enableHighAccuracy: true, maximumAge: umurMaks, timeout: batas },
        );
    });
}

function alurOrder() {
    const s = bukaSheet('Order');
    const o = { kunci: buatKunci(), tujuan: '', posisi: null, janjiPosisi: null, posisiSelfie: null, galatPosisiSelfie: null, stream: null, blob: null, url: null, percobaan: 0 };

    const matikanKamera = () => { o.stream?.getTracks().forEach((t) => t.stop()); o.stream = null; };
    s.bersih.push(() => { matikanKamera(); if (o.url) URL.revokeObjectURL(o.url); o.percobaan = -1; });

    const langkah = (aktif) => h('ol', { class: 'langkah', 'aria-label': 'Tahap Order' },
        ['Tujuan', 'Lokasi', 'Selfie', 'Kirim'].map((nama, i) =>
            h('li', { 'aria-current': i === aktif ? 'step' : null }, nama)));

    /* Tahap 1: tujuan pengantaran */
    function tahapTujuan() {
        o.percobaan++;
        s.judul.textContent = 'Tujuan pengantaran';
        const galat = kotakGalat();
        const input = h('input', { type: 'text', id: 'tujuan', maxlength: '255', value: o.tujuan, placeholder: 'Contoh: Gudang PT Maju Jaya, Cikarang', autocomplete: 'off' });
        const lanjut = h('button', { class: 'tombol', id: 'lanjut-tujuan', type: 'button' }, 'Lanjut ke lokasi');
        lanjut.onclick = () => {
            const nilai = input.value.replace(/\s+/g, ' ').trim();
            if (nilai.length < 3) {
                galat.textContent = 'Lokasi tujuan pengantaran wajib diisi.';
                return galat.classList.remove('hidden');
            }
            o.tujuan = nilai;
            tahapLokasi();
        };
        isiSheet(langkah(0), h('label', { for: 'tujuan' }, 'Nama tempat atau alamat'), input, h('p', { class: 'petunjuk' }, 'Lokasi HP diperiksa sebelum kamera dibuka.'), galat,
            h('div', { class: 'aksi' }, h('button', { class: 'tombol garis', type: 'button', onclick: bukaPilihan }, 'Kembali'), lanjut));
    }

    /* Tahap 2: lokasi terkini HP. Selama belum terbaca, kamera TERKUNCI. */
    function tahapLokasi() {
        s.judul.textContent = 'Order: lokasi HP';
        const nomor = ++o.percobaan;
        o.posisi = null;

        const status = h('div', { class: 'kotak-status cari', id: 'status-lokasi', role: 'status' }, h('span', { class: 'pemuat gelap' }), ' Mencari lokasi terkini HP...');
        const kunci = h('div', { class: 'kamera-terkunci', id: 'kamera-terkunci' }, 'Kamera terkunci sampai lokasi HP terbaca.');
        const aksi = h('div', { class: 'aksi' }, h('button', { class: 'tombol garis', type: 'button', onclick: tahapTujuan }, 'Kembali'));
        isiSheet(langkah(1), h('p', { class: 'muted' }, `Tujuan: ${o.tujuan}`), status, kunci, aksi);

        ambilPosisi({ umurMaks: 0, batas: 20000 }).then((pos) => {
            if (nomor !== o.percobaan || !sheet) return;
            o.posisi = pos;
            status.className = 'kotak-status ok';
            status.replaceChildren(`Lokasi terbaca (akurasi ±${Math.round(pos.akurasi)} m). Membuka kamera...`);
            setTimeout(() => { if (nomor === o.percobaan && sheet) tahapKamera(); }, 400);
        }).catch((err) => {
            if (nomor !== o.percobaan || !sheet) return;
            status.className = 'kotak-status gagal';
            status.replaceChildren(err?.pesan || 'Lokasi tidak dapat dibaca.');
            aksi.replaceChildren(
                h('button', { class: 'tombol garis', type: 'button', onclick: tahapTujuan }, 'Kembali'),
                h('button', { class: 'tombol', id: 'coba-lagi-lokasi', type: 'button', onclick: tahapLokasi }, 'Coba Lagi'));
        });
    }

    /* Tahap 3: kamera selfie (kamera yang sudah ada) */
    async function tahapKamera() {
        s.judul.textContent = 'Order: selfie';
        o.blob = null;
        if (o.url) { URL.revokeObjectURL(o.url); o.url = null; }
        const area = h('div', { class: 'pratinjau' });
        const galat = kotakGalat();
        const aksi = h('div', { class: 'aksi' });
        isiSheet(langkah(2), area, galat, aksi);

        const batal = h('button', { class: 'tombol garis', type: 'button', onclick: () => { matikanKamera(); tahapLokasi(); } }, 'Kembali');
        const tampilGalat = (t) => { galat.textContent = t; galat.classList.remove('hidden'); };

        const jadikanBlob = (sumber, lebar, tinggi) => new Promise((resolve) => {
            const { lebar: w, tinggi: t } = ukuranTerskala(lebar, tinggi, 1024);
            const kanvas = document.createElement('canvas');
            kanvas.width = w; kanvas.height = t;
            kanvas.getContext('2d').drawImage(sumber, 0, 0, w, t);
            kanvas.toBlob((b) => resolve(b), 'image/jpeg', 0.82);
        });

        // Posisi diambil SAAT selfie diambil, bukan hanya saat kamera dibuka
        const terimaFoto = (blob) => {
            if (!blob) return tampilGalat('Gagal memproses foto. Coba lagi.');
            o.blob = blob;
            o.url = URL.createObjectURL(blob);
            tahapHasil();
        };

        const modeCadangan = (alasan) => {
            tampilGalat(alasan);
            const input = h('input', { type: 'file', accept: 'image/*', capture: 'user', class: 'hidden', id: 'foto-cadangan' });
            input.addEventListener('change', async () => {
                const file = input.files?.[0];
                if (!file) return;
                o.janjiPosisi = ambilPosisi({ umurMaks: 15000, batas: 15000 });
                try {
                    const bmp = await createImageBitmap(file);
                    terimaFoto(await jadikanBlob(bmp, bmp.width, bmp.height));
                } catch { tampilGalat('Foto tidak dapat dibaca. Coba ambil ulang.'); }
            });
            area.replaceChildren(h('div', { class: 'muted' }, 'Kamera siap dibuka.'));
            aksi.replaceChildren(batal, h('button', { class: 'tombol', type: 'button', onclick: () => input.click() }, 'Buka Kamera'), input);
        };

        if (!navigator.mediaDevices?.getUserMedia) return modeCadangan('Kamera langsung tidak tersedia di browser ini. Gunakan tombol di bawah.');
        try {
            o.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
        } catch {
            return modeCadangan('Izin kamera ditolak. Izinkan kamera untuk situs ini, atau gunakan tombol di bawah.');
        }
        if (sheet !== s) return matikanKamera();

        const video = h('video', { playsinline: true, muted: true, autoplay: true });
        video.srcObject = o.stream;
        area.replaceChildren(video, h('div', { class: 'pemandu' }));
        try { await video.play(); } catch { /* beberapa browser memulai otomatis */ }

        const ambil = h('button', { class: 'tombol', id: 'ambil-foto', type: 'button' }, 'Ambil Foto');
        ambil.onclick = async () => {
            if (!video.videoWidth) return tampilGalat('Kamera belum siap. Tunggu sebentar lalu coba lagi.');
            o.janjiPosisi = ambilPosisi({ umurMaks: 15000, batas: 15000 }); // lokasi saat selfie
            const blob = await jadikanBlob(video, video.videoWidth, video.videoHeight);
            matikanKamera();
            terimaFoto(blob);
        };
        aksi.replaceChildren(batal, ambil);
    }

    /* Tahap 4: pratinjau + kirim */
    function tahapHasil() {
        s.judul.textContent = 'Order: periksa & kirim';
        o.posisiSelfie = null;
        o.galatPosisiSelfie = null;

        const galat = kotakGalat();
        const info = h('div', { class: 'info-order', id: 'info-order', role: 'status' });
        const kirimBtn = h('button', { class: 'tombol', id: 'kirim-order', type: 'button', disabled: true }, 'Kirim Order');
        const ulangi = h('button', { class: 'tombol garis', id: 'ulangi-foto', type: 'button', onclick: tahapKamera }, 'Ulangi Foto');
        const cobaLokasi = h('button', { class: 'tombol garis hidden', id: 'coba-lagi-selfie', type: 'button' }, 'Coba Lagi Lokasi');

        const gambarInfo = () => {
            const p = o.posisiSelfie;
            info.replaceChildren(
                h('div', {}, h('span', { class: 'muted' }, 'Tujuan: '), h('strong', {}, o.tujuan)),
                h('div', {}, h('span', { class: 'muted' }, 'Lokasi HP saat selfie: '),
                    p ? h('strong', {}, `${formatKoordinat(p.latitude, p.longitude)} (±${Math.round(p.akurasi)} m)`)
                        : h('span', { class: o.galatPosisiSelfie ? 'merah' : 'muted' }, o.galatPosisiSelfie || 'Membaca lokasi...')),
                h('div', { class: 'muted kecil' }, 'Watermark foto memuat lokasi, waktu server (WIB), dan logo MTU.'));
        };

        const bacaLokasi = () => {
            o.galatPosisiSelfie = null;
            cobaLokasi.classList.add('hidden');
            kirimBtn.disabled = true;
            gambarInfo();
            const nomor = ++o.percobaan;
            const janji = o.janjiPosisi || ambilPosisi({ umurMaks: 15000, batas: 15000 });
            o.janjiPosisi = null;
            janji.then((pos) => {
                if (nomor !== o.percobaan || !sheet) return;
                o.posisiSelfie = pos;
                kirimBtn.disabled = S.mengirim;
                gambarInfo();
            }).catch((err) => {
                if (nomor !== o.percobaan || !sheet) return;
                o.galatPosisiSelfie = err?.pesan || 'Lokasi saat selfie tidak terbaca.';
                cobaLokasi.classList.remove('hidden');
                gambarInfo();
            });
        };
        cobaLokasi.onclick = () => { o.janjiPosisi = null; bacaLokasi(); };

        kirimBtn.onclick = () => kirim({
            tombol: kirimBtn,
            galat,
            jalankan: () => api.order(bangunFormOrder({
                kunci: o.kunci, tujuan: o.tujuan, foto: o.blob,
                latitude: o.posisiSelfie.latitude, longitude: o.posisiSelfie.longitude, akurasi: o.posisiSelfie.akurasi,
            })),
        });

        isiSheet(langkah(3), h('div', { class: 'pratinjau' }, h('img', { src: o.url, alt: 'Pratinjau selfie' })), info, galat,
            h('div', { class: 'aksi' }, ulangi, cobaLokasi, kirimBtn));
        bacaLokasi();
    }

    tahapTujuan();
}

/* ------------------------------------------------------------------- izin */
function alurIzin() {
    const s = bukaSheet('Izin');
    const kunci = buatKunci();

    function tahapPilih() {
        s.judul.textContent = 'Izin';
        isiSheet(
            h('div', { class: 'pilihan-daftar' },
                pilihan('izin-libur', 'izin', 'Libur', 'Langsung kirim.', () => tahapDetail('libur')),
                pilihan('izin-sakit', 'sakit', 'Sakit', 'Wajib unggah Surat Keterangan Dokter (SKD).', () => tahapDetail('sakit')),
                pilihan('izin-lain', 'lainnya', 'Lain-lain', 'Keperluan mendadak, mis. musibah. Wajib mengisi alasan.', () => tahapDetail('lainnya'))),
            h('div', { class: 'aksi' }, h('button', { class: 'tombol garis', type: 'button', onclick: bukaPilihan }, 'Kembali')),
        );
    }

    function tahapDetail(kategori) {
        const galat = kotakGalat();
        const kirimBtn = h('button', { class: 'tombol', id: 'kirim-izin', type: 'button', disabled: kategori !== 'libur' }, 'Kirim');
        const kembali = h('button', { class: 'tombol garis', type: 'button', onclick: tahapPilih }, 'Kembali');
        let isi;
        let ambilNilai;

        if (kategori === 'libur') {
            s.judul.textContent = 'Izin: Libur';
            isi = [h('p', { class: 'muted' }, 'Izin libur dicatat langsung dengan waktu dari server.')];
            ambilNilai = () => ({ kategori, kunci });
        } else if (kategori === 'sakit') {
            s.judul.textContent = 'Izin: Sakit';
            const input = h('input', { type: 'file', id: 'skd', accept: 'image/jpeg,image/png,application/pdf', class: 'hidden' });
            const nama = h('div', { class: 'muted', id: 'nama-skd' }, 'Belum ada berkas dipilih.');
            const pilih = h('button', { class: 'tombol garis', id: 'pilih-skd', type: 'button', onclick: () => input.click() }, 'Pilih foto/PDF SKD');
            input.addEventListener('change', () => {
                const berkas = input.files?.[0];
                const salah = validasiLampiran(berkas);
                galat.classList.add('hidden');
                if (salah) {
                    nama.textContent = 'Belum ada berkas dipilih.';
                    galat.textContent = salah;
                    galat.classList.remove('hidden');
                    kirimBtn.disabled = true;
                    return;
                }
                nama.textContent = `${berkas.name} (${Math.max(1, Math.round(berkas.size / 1024))} KB)`;
                kirimBtn.disabled = false;
            });
            isi = [h('p', { class: 'muted' }, 'Wajib melampirkan Surat Keterangan Dokter (SKD). Format JPG, PNG, atau PDF, maksimal 5 MB.'), pilih, nama, input];
            ambilNilai = () => ({ kategori, kunci, lampiran: input.files?.[0] });
        } else {
            s.judul.textContent = 'Izin: Lain-lain';
            const alasan = h('textarea', { id: 'alasan', rows: '4', maxlength: '1000', placeholder: 'Jelaskan keperluan mendadak Anda' });
            const hitung = h('small', { class: 'hitung', id: 'jumlah-alasan' }, '0 / 1000 karakter');
            alasan.setAttribute('aria-describedby', 'jumlah-alasan catatan-izin');
            alasan.addEventListener('input', () => {
                hitung.textContent = `${alasan.value.length} / 1000 karakter`;
                kirimBtn.disabled = alasan.value.trim().length < 5;
            });
            isi = [
                h('label', { for: 'alasan' }, 'Alasan (wajib)'), alasan, hitung,
                h('div', { class: 'info', id: 'catatan-izin' }, CATATAN_IZIN_LAIN),
            ];
            ambilNilai = () => ({ kategori, kunci, alasan: alasan.value.trim() });
        }

        kirimBtn.onclick = () => kirim({ tombol: kirimBtn, galat, jalankan: () => api.izin(bangunFormIzin(ambilNilai())) });
        isiSheet(isi, galat, h('div', { class: 'aksi' }, kembali, kirimBtn));
    }

    tahapPilih();
}

/* --------------------------------------------------------------- render */
function render() {
    const peta = { login: layarLogin, ganti: layarGanti, utama: layarUtama };
    $app.replaceChildren((peta[S.layar] || layarLogin)());
}

setInterval(() => {
    const el = document.getElementById('jam');
    if (el) el.textContent = formatJamLengkap(new Date(Date.now() + S.offset));
}, 1000);

window.addEventListener('online', () => { S.online = true; if (S.layar === 'utama') render(); });
window.addEventListener('offline', () => { S.online = false; if (S.layar === 'utama') render(); });
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); S.pasang = e; if (S.layar === 'utama' && S.tab === 'akun') render(); });

// Saat aplikasi kembali dibuka dari latar belakang: segarkan catatan hari ini dan status izin
document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState === 'visible' && S.layar === 'utama' && S.online && !S.mengirim && !sheet) {
        try { await muatRingkasan(); if (S.tab === 'beranda') render(); } catch { /* sesi ditangani saatTidakSah */ }
    }
});

/* ---------------------------------------------------------------- mulai */
async function mulai() {
    if (!simpan.ambil(KUNCI_TOKEN)) return render();
    try {
        const me = await api.me();
        S.profil = me.karyawan;
        if (me.must_change_password) {
            S.layar = 'ganti';
            return render();
        }
        await masukKeUtama();
    } catch (e) {
        if (e.status === 0) {
            // Offline saat membuka: tampilkan kerangka dengan pesan, jangan menghapus sesi
            S.layar = 'utama';
            S.online = false;
            render();
            return toast(e.message, 'err');
        }
        render(); // 401 sudah ditangani saatTidakSah; lainnya tampilkan login
    }
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/absen/sw.js', { scope: '/absen' }).catch(() => { /* PWA tetap jalan tanpa SW */ });
}

mulai();
