// Aplikasi absensi (PWA). Vanilla JS tanpa library: tidak ada skrip pihak ketiga yang bisa mengakses token.
import { buatApi } from './api.js';
import {
    aksiTersedia, bangunFormAbsen, bulanSekarang, formatJam, formatJamLengkap, formatJarak, formatTanggalPanjang,
    formatTanggalPendek, geserBulan, hitungOffset, namaBulan, namaPerangkat, pesanGeolokasi, ringkasHariIni,
    statusRadius, ukuranTerskala, warnaStatus,
} from './lib.js';

const KUNCI_TOKEN = 'pt.token';
const KUNCI_PERANGKAT = 'pt.perangkat';

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
    setTimeout(() => el.remove(), 4500);
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
    hariIni: null,
    offset: 0,
    posisi: null,
    galatPosisi: null,
    watchId: null,
    bulan: null,
    riwayat: null,
    online: navigator.onLine,
    pasang: null,
    notice: '',
    sibuk: false,
};

const api = buatApi({
    ambilToken: () => simpan.ambil(KUNCI_TOKEN),
    saatTidakSah: () => keluarLokal('Sesi Anda berakhir. Silakan masuk kembali.'),
});

const $app = document.getElementById('app');

/* ------------------------------------------------------------- lokasi GPS */
function mulaiLokasi() {
    if (S.watchId !== null) return;
    if (!('geolocation' in navigator)) {
        S.galatPosisi = 'Browser ini tidak mendukung lokasi.';
        return;
    }
    S.watchId = navigator.geolocation.watchPosition(
        (p) => {
            S.posisi = { latitude: p.coords.latitude, longitude: p.coords.longitude, akurasi: p.coords.accuracy };
            S.galatPosisi = null;
            segarkanLokasi();
        },
        (e) => {
            S.galatPosisi = pesanGeolokasi(e.code);
            segarkanLokasi();
        },
        { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    );
}

function hentikanLokasi() {
    if (S.watchId !== null && 'geolocation' in navigator) navigator.geolocation.clearWatch(S.watchId);
    S.watchId = null;
}

/** Posisi terbaru saat tombol kirim ditekan (bukan posisi lama dari cache). */
function ambilPosisiSegar() {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) return reject(new Error('Browser ini tidak mendukung lokasi.'));
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, akurasi: p.coords.accuracy }),
            (e) => reject(new Error(pesanGeolokasi(e.code))),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 },
        );
    });
}

/* --------------------------------------------------------------- data */
async function muatHariIni() {
    S.hariIni = await api.hariIni();
    S.offset = hitungOffset(S.hariIni.waktu_server, Date.now());
}

async function muatRiwayat() {
    S.bulan ||= bulanSekarang(new Date(Date.now() + S.offset));
    S.riwayat = null;
    render();
    try {
        S.riwayat = await api.riwayat(S.bulan);
    } catch (e) {
        toast(e.message, 'err');
        S.riwayat = { ringkasan: {}, data: [] };
    }
    if (S.layar === 'utama' && S.tab === 'riwayat') render();
}

function keluarLokal(pesan = '') {
    simpan.hapus(KUNCI_TOKEN);
    hentikanLokasi();
    Object.assign(S, { layar: 'login', tab: 'beranda', profil: null, hariIni: null, posisi: null, riwayat: null, bulan: null, notice: pesan });
    render();
}

async function masukKeUtama() {
    S.layar = 'utama';
    S.tab = 'beranda';
    try {
        await muatHariIni();
    } catch (e) {
        if (e.kode === 'wajib_ganti_password') return (S.layar = 'ganti'), render();
        if (e.kode === 'akun_nonaktif') return keluarLokal(e.message);
        toast(e.message, 'err');
    }
    mulaiLokasi();
    render();
}

/* ------------------------------------------------------------- layar: login */
function layarLogin() {
    const galat = h('div', { class: 'galat hidden', role: 'alert' });
    const email = h('input', { type: 'email', id: 'email', autocomplete: 'username', required: true, inputmode: 'email' });
    const sandi = h('input', { type: 'password', id: 'sandi', autocomplete: 'current-password', required: true });
    const tombol = h('button', { class: 'tombol', type: 'submit' }, 'Masuk');

    const form = h('form', {
        novalidate: true,
        onsubmit: async (ev) => {
            ev.preventDefault();
            galat.classList.add('hidden');
            if (!email.value || !sandi.value) {
                galat.textContent = 'Email dan password wajib diisi.';
                return galat.classList.remove('hidden');
            }
            tombol.disabled = true;
            tombol.textContent = 'Memeriksa...';
            try {
                const res = await api.login(email.value.trim(), sandi.value, namaPerangkat(navigator.userAgent, idPerangkat()));
                simpan.set(KUNCI_TOKEN, res.token);
                S.profil = res.karyawan;
                S.notice = '';
                if (res.must_change_password) {
                    S.layar = 'ganti';
                    return render();
                }
                await masukKeUtama();
            } catch (e) {
                galat.textContent = e.message;
                galat.classList.remove('hidden');
                tombol.disabled = false;
                tombol.textContent = 'Masuk';
            }
        },
    }, h('label', { for: 'email' }, 'Email'), email, h('label', { for: 'sandi' }, 'Password'), sandi, tombol, galat);

    return h('div', { class: 'layar-gelap' },
        h('div', { class: 'merek' }, 'Absensi', h('span', {}, '.')),
        h('div', { class: 'kartu' },
            h('h1', {}, 'Masuk'),
            h('p', { class: 'muted' }, 'Gunakan akun karyawan yang diberikan HRD.'),
            S.notice ? h('div', { class: 'info' }, S.notice) : null,
            form),
    );
}

/* ------------------------------------------------------ layar: ganti password */
function layarGanti() {
    const galat = h('div', { class: 'galat hidden', role: 'alert' });
    const lama = h('input', { type: 'password', id: 'lama', autocomplete: 'current-password', required: true });
    const baru = h('input', { type: 'password', id: 'baru', autocomplete: 'new-password', required: true });
    const ulang = h('input', { type: 'password', id: 'ulang', autocomplete: 'new-password', required: true });
    const tombol = h('button', { class: 'tombol', type: 'submit' }, 'Simpan Password');

    const form = h('form', {
        novalidate: true,
        onsubmit: async (ev) => {
            ev.preventDefault();
            galat.classList.add('hidden');
            if (baru.value !== ulang.value) {
                galat.textContent = 'Konfirmasi password tidak sama.';
                return galat.classList.remove('hidden');
            }
            tombol.disabled = true;
            try {
                await api.gantiPassword({ password_lama: lama.value, password: baru.value, password_confirmation: ulang.value });
                toast('Password berhasil diganti.', 'ok');
                await masukKeUtama();
            } catch (e) {
                galat.textContent = e.message;
                galat.classList.remove('hidden');
                tombol.disabled = false;
            }
        },
    },
    h('label', { for: 'lama' }, 'Password saat ini'), lama,
    h('label', { for: 'baru' }, 'Password baru'), baru,
    h('p', { class: 'muted' }, 'Minimal 8 karakter, mengandung huruf dan angka.'),
    h('label', { for: 'ulang' }, 'Ulangi password baru'), ulang,
    h('div', { class: 'galat-wadah' }), tombol, galat);

    return h('div', { class: 'layar-gelap' },
        h('div', { class: 'merek' }, 'Absensi', h('span', {}, '.')),
        h('div', { class: 'kartu' },
            h('h1', {}, 'Ganti Password'),
            h('p', { class: 'muted' }, 'Anda memakai password sementara. Buat password baru sebelum melanjutkan.'),
            form,
            h('button', { class: 'tombol garis kecil', type: 'button', onclick: () => keluarLokal() }, 'Keluar')),
    );
}

/* ----------------------------------------------------------- layar: utama */
function kartuLokasi() {
    const kantor = S.hariIni?.kantor;
    const st = S.posisi && kantor ? statusRadius(S.posisi, kantor) : null;
    const kelas = st ? (st.dalam ? 'dalam' : 'luar') : '';

    return h('div', { class: 'kartu', id: 'kartu-lokasi' },
        h('div', { class: 'muted' }, h('span', { class: `titik ${kelas}` }), kantor ? `Jarak ke ${kantor.nama}` : 'Lokasi'),
        st
            ? h('div', {},
                h('div', { class: 'lokasi-baris' },
                    h('span', { class: `lokasi-angka ${kelas}` }, formatJarak(st.jarak)),
                    h('span', { class: 'muted' }, `Akurasi ±${st.akurasi} m`)),
                h('div', { class: 'muted' }, st.dalam
                    ? `Di dalam area absen (batas ${kantor.radius_meter} m)`
                    : `Di luar area absen (batas ${kantor.radius_meter} m)`))
            : h('div', { class: 'muted' }, S.galatPosisi || 'Mencari lokasi Anda...'),
        S.galatPosisi && st ? h('div', { class: 'galat' }, S.galatPosisi) : null,
        !st && S.galatPosisi ? h('div', { class: 'galat' }, S.galatPosisi) : null,
        h('div', { class: 'aksi-kecil' }, h('button', { class: 'tombol garis kecil', type: 'button', onclick: () => { hentikanLokasi(); S.posisi = null; S.galatPosisi = null; mulaiLokasi(); segarkanLokasi(); } }, 'Perbarui lokasi')),
    );
}

function tombolAksi() {
    const aksi = aksiTersedia(S.hariIni);
    const kantor = S.hariIni?.kantor;
    const st = S.posisi && kantor ? statusRadius(S.posisi, kantor) : null;

    let teks = 'Tidak ada absensi yang perlu dilakukan';
    let nonaktif = true;
    if (aksi) {
        teks = aksi === 'masuk' ? 'Absen Masuk' : 'Absen Pulang';
        if (!S.online) teks = 'Tidak ada koneksi';
        else if (!st) teks += ' (menunggu lokasi...)';
        else if (st.pastiLuar) teks = `Di luar area absen (${formatJarak(st.jarak)})`;
        else nonaktif = false;
    } else if (S.hariIni?.absensi?.jam_pulang) {
        teks = 'Absensi hari ini selesai';
    } else if (S.hariIni && !S.hariIni.hari_kerja) {
        teks = 'Hari ini libur';
    }

    return h('button', {
        class: `tombol besar ${aksi === 'pulang' ? 'gelap' : ''}`, id: 'tombol-aksi', type: 'button',
        disabled: nonaktif || S.sibuk, onclick: () => bukaKamera(aksi),
    }, teks);
}

/** Memperbarui kartu lokasi & tombol saja (tanpa menggambar ulang seluruh layar). */
function segarkanLokasi() {
    if (S.layar !== 'utama' || S.tab !== 'beranda') return;
    document.getElementById('kartu-lokasi')?.replaceWith(kartuLokasi());
    document.getElementById('tombol-aksi')?.replaceWith(tombolAksi());
}

function tabBeranda() {
    const r = ringkasHariIni(S.hariIni);
    const sekarang = new Date(Date.now() + S.offset);

    return h('div', {},
        h('div', { class: 'kartu' },
            h('div', { class: 'tgl' }, formatTanggalPanjang(sekarang)),
            h('div', { class: 'jam', id: 'jam' }, formatJamLengkap(sekarang)),
            h('div', { class: 'muted' }, 'Waktu WIB (mengikuti server)')),
        h('div', { class: 'kartu' },
            h('span', { class: `lencana ${r.warna}` }, 'Hari ini'),
            h('div', { class: 'status-judul' }, r.judul),
            h('div', { class: 'muted' }, r.rinci)),
        kartuLokasi(),
        h('div', { class: 'tombol-wadah' }, tombolAksi()),
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

    const chip = Object.entries(S.riwayat.ringkasan || {}).map(([st, n]) => h('span', { class: `lencana ${warnaStatus(st)}` }, `${st} ${n}`));
    const baris = (S.riwayat.data || []).map((a) => h('div', { class: 'item-riwayat' },
        h('div', {},
            h('div', { class: 't' }, formatTanggalPendek(a.tanggal)),
            h('div', { class: 'j' }, a.jam_masuk
                ? `Masuk ${formatJam(a.jam_masuk)} · Pulang ${formatJam(a.jam_pulang)}${a.menit_terlambat ? ` · telat ${a.menit_terlambat} mnt` : ''}`
                : (a.keterangan || 'Dicatat HRD'))),
        h('span', { class: `lencana ${warnaStatus(a.status)}` }, a.status_label)));

    return h('div', {}, nav, chip.length ? h('div', { class: 'ringkas' }, chip) : null,
        h('div', { class: 'kartu' }, baris.length ? baris : h('div', { class: 'muted' }, 'Belum ada data pada bulan ini.')));
}

function tabAkun() {
    const p = S.profil;
    const ios = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    const terpasang = window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone;

    return h('div', {},
        h('div', { class: 'kartu' },
            h('h2', {}, p?.nama || '-'),
            h('div', { class: 'muted' }, `${p?.jabatan || ''} · NIP ${p?.nip || '-'}`),
            h('div', { class: 'muted' }, p?.email || '')),
        !terpasang && S.pasang
            ? h('div', { class: 'kartu' }, h('strong', {}, 'Pasang aplikasi'), h('p', { class: 'muted' }, 'Tambahkan ke layar utama agar terbuka seperti aplikasi biasa.'),
                h('button', { class: 'tombol', type: 'button', onclick: async () => { S.pasang.prompt(); await S.pasang.userChoice; S.pasang = null; render(); } }, 'Pasang di layar utama'))
            : null,
        !terpasang && ios
            ? h('div', { class: 'kartu' }, h('strong', {}, 'Pasang di iPhone'), h('p', { class: 'muted' }, 'Ketuk tombol Bagikan di Safari, lalu pilih "Tambah ke Layar Utama".'))
            : null,
        h('div', { class: 'kartu' },
            h('button', { class: 'tombol garis', type: 'button', onclick: () => { S.layar = 'ganti'; render(); } }, 'Ganti password'),
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
        h('div', { class: 'konten' }, isi),
        h('nav', { class: 'nav' }, tab('beranda', '⌂', 'Beranda'), tab('riwayat', '☰', 'Riwayat'), tab('akun', '☺', 'Akun')),
    );
}

/* ------------------------------------------------------------ kamera selfie */
let kamera = null; // { stream, blob, url, aksi }

function tutupKamera() {
    if (kamera?.stream) kamera.stream.getTracks().forEach((t) => t.stop());
    if (kamera?.url) URL.revokeObjectURL(kamera.url);
    document.getElementById('sheet-kamera')?.remove();
    kamera = null;
}

async function bukaKamera(aksi) {
    if (!aksi || S.sibuk) return;
    tutupKamera();
    kamera = { stream: null, blob: null, url: null, aksi };

    const judul = h('h2', {}, aksi === 'masuk' ? 'Selfie Absen Masuk' : 'Selfie Absen Pulang');
    const area = h('div', { class: 'pratinjau' });
    const pesan = h('div', { class: 'galat hidden', role: 'alert' });
    const aksiBaris = h('div', { class: 'aksi' });
    const sheet = h('div', { class: 'sheet', id: 'sheet-kamera', role: 'dialog', 'aria-modal': 'true' }, h('div', { class: 'panel' }, judul, area, pesan, aksiBaris));
    document.body.append(sheet);

    const tampilkanGalat = (t) => { pesan.textContent = t; pesan.classList.remove('hidden'); };
    const batal = h('button', { class: 'tombol garis', type: 'button', onclick: tutupKamera }, 'Batal');

    // Mode foto hasil: Ulangi / Kirim
    const tampilkanHasil = () => {
        area.replaceChildren(h('img', { src: kamera.url, alt: 'Pratinjau selfie' }));
        const kirimBtn = h('button', { class: 'tombol', type: 'button' }, aksi === 'masuk' ? 'Kirim Absen Masuk' : 'Kirim Absen Pulang');
        kirimBtn.onclick = async () => {
            pesan.classList.add('hidden');
            kirimBtn.disabled = true;
            S.sibuk = true;
            kirimBtn.replaceChildren(h('span', { class: 'pemuat' }));
            try {
                const pos = await ambilPosisiSegar();
                const form = bangunFormAbsen({ foto: kamera.blob, latitude: pos.latitude, longitude: pos.longitude, akurasi: pos.akurasi });
                const res = aksi === 'masuk' ? await api.masuk(form) : await api.pulang(form);
                S.sibuk = false;
                tutupKamera();
                toast(res.message, 'ok');
                await muatHariIni();
                render();
            } catch (e) {
                S.sibuk = false;
                if (e.kode === 'wajib_ganti_password') { tutupKamera(); S.layar = 'ganti'; return render(); }
                if (e.kode === 'akun_nonaktif') { tutupKamera(); return keluarLokal(e.message); }
                tampilkanGalat(e.message);
                kirimBtn.disabled = false;
                kirimBtn.textContent = 'Coba Kirim Lagi';
                if (['sudah_absen', 'sudah_pulang', 'belum_masuk', 'bukan_hari_kerja'].includes(e.kode)) {
                    try { await muatHariIni(); } catch { /* abaikan */ }
                }
            }
        };
        aksiBaris.replaceChildren(
            h('button', { class: 'tombol garis', type: 'button', onclick: () => { URL.revokeObjectURL(kamera.url); kamera.url = null; kamera.blob = null; mulaiKamera(); } }, 'Ulangi'),
            kirimBtn);
    };

    const jadikanBlob = (sumber, lebar, tinggi) => new Promise((resolve) => {
        const { lebar: w, tinggi: t } = ukuranTerskala(lebar, tinggi, 1024);
        const kanvas = document.createElement('canvas');
        kanvas.width = w; kanvas.height = t;
        kanvas.getContext('2d').drawImage(sumber, 0, 0, w, t);
        kanvas.toBlob((b) => resolve(b), 'image/jpeg', 0.82);
    });

    const terimaBlob = (blob) => {
        if (!blob) return tampilkanGalat('Gagal memproses foto. Coba lagi.');
        kamera.blob = blob;
        kamera.url = URL.createObjectURL(blob);
        tampilkanHasil();
    };

    // Cadangan: kamera bawaan lewat <input capture> bila getUserMedia tidak tersedia / ditolak
    const modeCadangan = (alasan) => {
        if (alasan) tampilkanGalat(alasan);
        const input = h('input', { type: 'file', accept: 'image/*', capture: 'user', class: 'hidden', id: 'foto-cadangan' });
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;
            try {
                const bmp = await createImageBitmap(file);
                terimaBlob(await jadikanBlob(bmp, bmp.width, bmp.height));
            } catch { tampilkanGalat('Foto tidak dapat dibaca. Coba ambil ulang.'); }
        });
        area.replaceChildren(h('div', { class: 'muted' }, 'Kamera siap dibuka.'));
        aksiBaris.replaceChildren(batal, h('button', { class: 'tombol', type: 'button', onclick: () => input.click() }, 'Buka Kamera'), input);
    };

    async function mulaiKamera() {
        pesan.classList.add('hidden');
        if (!navigator.mediaDevices?.getUserMedia) return modeCadangan('Kamera langsung tidak tersedia di browser ini. Gunakan tombol di bawah.');
        try {
            kamera.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
        } catch {
            return modeCadangan('Izin kamera ditolak. Izinkan kamera untuk situs ini, atau gunakan tombol di bawah.');
        }
        const video = h('video', { playsinline: true, muted: true, autoplay: true });
        video.srcObject = kamera.stream;
        area.replaceChildren(video, h('div', { class: 'pemandu' }));
        try { await video.play(); } catch { /* beberapa browser memulai otomatis */ }

        const ambil = h('button', { class: 'tombol', type: 'button' }, 'Ambil Foto');
        ambil.onclick = async () => {
            if (!video.videoWidth) return tampilkanGalat('Kamera belum siap. Tunggu sebentar lalu coba lagi.');
            const blob = await jadikanBlob(video, video.videoWidth, video.videoHeight);
            kamera.stream.getTracks().forEach((t) => t.stop());
            kamera.stream = null;
            terimaBlob(blob);
        };
        aksiBaris.replaceChildren(batal, ambil);
    }

    await mulaiKamera();
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

window.addEventListener('online', () => { S.online = true; document.getElementById('banner-offline')?.classList.add('hidden'); segarkanLokasi(); });
window.addEventListener('offline', () => { S.online = false; document.getElementById('banner-offline')?.classList.remove('hidden'); segarkanLokasi(); });
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); S.pasang = e; if (S.layar === 'utama' && S.tab === 'akun') render(); });

// Saat aplikasi kembali dibuka dari latar belakang: segarkan status hari ini
document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState === 'visible' && S.layar === 'utama' && S.online && !S.sibuk) {
        try { await muatHariIni(); segarkanLokasi(); if (S.tab === 'beranda') render(); } catch { /* sesi ditangani saatTidakSah */ }
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
