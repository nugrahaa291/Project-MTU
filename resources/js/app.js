// Menu samping (panel) pada layar kecil
document.addEventListener('click', (e) => {
    const tombol = e.target.closest('[data-toggle-sidebar]');
    if (!tombol) return;
    document.getElementById('sidebar')?.classList.toggle('hidden');
});

// Konfirmasi sebelum aksi berbahaya: <form data-konfirmasi="Yakin?">
document.addEventListener('submit', (e) => {
    const pesan = e.target.dataset?.konfirmasi;
    if (pesan && !window.confirm(pesan)) e.preventDefault();
});

// Konfirmasi saat uncheck checkbox sensitif: <input type="checkbox" data-confirm-uncheck="Yakin?">
document.addEventListener('change', (e) => {
    const cb = e.target;
    if (!cb.matches('input[type="checkbox"][data-confirm-uncheck]')) return;
    if (!cb.checked) {
        const pesan = cb.dataset.confirmUncheck;
        if (pesan && !window.confirm(pesan)) {
            cb.checked = true;
        }
    }
});

// Real-time AJAX search & filter: <form data-ajax-filter data-ajax-target="#ajax-results">
(() => {
    const controllers = new WeakMap();
    const timers = new WeakMap();

    async function jalankanAjaxFilter(form, customUrl = null) {
        const targetSelector = form.dataset.ajaxTarget || '#ajax-results';
        const targetEl = document.querySelector(targetSelector);
        if (!targetEl) {
            form.submit();
            return;
        }

        // Bangun URL dari form data (kecuali jika klik pagination)
        let url;
        if (customUrl) {
            url = customUrl;
        } else {
            const action = form.getAttribute('action') || window.location.pathname;
            const baseUrl = new URL(action, window.location.origin);
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [k, v] of formData.entries()) {
                if (String(v).trim() !== '') {
                    params.append(k, String(v));
                }
            }
            const qs = params.toString();
            url = baseUrl.pathname + (qs ? `?${qs}` : '');
        }

        // Batalkan request sebelumnya jika masih berjalan
        const prevController = controllers.get(form);
        if (prevController) prevController.abort();
        const controller = new AbortController();
        controllers.set(form, controller);

        targetEl.style.opacity = '0.55';
        targetEl.style.transition = 'opacity 150ms ease';

        try {
            const resp = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            const html = await resp.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextTarget = doc.querySelector(targetSelector);

            if (nextTarget) {
                targetEl.innerHTML = nextTarget.innerHTML;
            }

            // Sinkronkan elemen tambahan (misal tombol export Excel, counter, bar status filter)
            const syncSelector = form.dataset.ajaxSync;
            if (syncSelector) {
                document.querySelectorAll(syncSelector).forEach((el) => {
                    if (!el.id) return;
                    const nextSync = doc.getElementById(el.id);
                    if (nextSync) {
                        el.innerHTML = nextSync.innerHTML;
                    }
                });
            }

            window.history.replaceState({}, '', url);
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('AJAX filter error:', err);
            }
        } finally {
            if (controllers.get(form) === controller) {
                targetEl.style.opacity = '';
            }
        }
    }

    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-ajax-filter]');
        if (!form) return;
        e.preventDefault();
        clearTimeout(timers.get(form));
        jalankanAjaxFilter(form);
    });

    document.addEventListener('change', (e) => {
        const form = e.target.closest('form[data-ajax-filter]');
        if (!form) return;
        if (e.target.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) {
            clearTimeout(timers.get(form));
            jalankanAjaxFilter(form);
        }
    });

    document.addEventListener('input', (e) => {
        const form = e.target.closest('form[data-ajax-filter]');
        if (!form) return;
        if (e.target.matches('input[type="text"], input[type="search"], input[type="number"], input:not([type])')) {
            clearTimeout(timers.get(form));
            const t = setTimeout(() => jalankanAjaxFilter(form), 300);
            timers.set(form, t);
        }
    });

    // Tangani klik pagination di dalam area target AJAX
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-ajax-pagination] a[href], #ajax-results nav a[href], #containerTabel nav a[href]');
        if (!link) return;
        const container = link.closest('#ajax-results, #containerTabel');
        if (!container) return;
        const form = document.querySelector(`form[data-ajax-filter][data-ajax-target="#${container.id}"]`)
            || document.querySelector('form[data-ajax-filter]');
        if (!form) return;
        e.preventDefault();
        jalankanAjaxFilter(form, link.getAttribute('href'));
    });
})();

// Menu navigasi publik pada layar kecil
document.addEventListener('click', (e) => {
    const tombol = e.target.closest('[data-toggle-menu]');
    if (!tombol) return;
    const menu = document.getElementById(tombol.getAttribute('aria-controls'));
    const terbuka = menu.classList.toggle('hidden') === false;
    tombol.setAttribute('aria-expanded', String(terbuka));
    tombol.setAttribute('aria-label', terbuka ? 'Tutup menu' : 'Buka menu');
    tombol.querySelector('[data-ikon-buka]')?.classList.toggle('hidden', terbuka);
    tombol.querySelector('[data-ikon-tutup]')?.classList.toggle('hidden', !terbuka);
});

// ===== Unggah berkas: Lampiran / Foto, pratinjau, hapus =====
const formatUkuran = (byte) =>
    byte >= 1048576 ? (byte / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(byte / 1024)) + ' KB';

const dialogPratinjau = document.getElementById('dialog-pratinjau');
let urlPratinjau = null;
let akarAktif = null;

dialogPratinjau?.addEventListener('close', () => {
    if (urlPratinjau) URL.revokeObjectURL(urlPratinjau);
    urlPratinjau = null;
    dialogPratinjau.querySelector('[data-pratinjau-isi]').replaceChildren();
});

dialogPratinjau?.addEventListener('click', (e) => {
    if (e.target === dialogPratinjau || e.target.closest('[data-pratinjau-tutup]')) {
        dialogPratinjau.close();
    }
    if (e.target.closest('[data-pratinjau-ganti]')) {
        dialogPratinjau.close();
        akarAktif?.querySelector('[data-unggah-pilih]').click();
    }
});

const bukaPratinjau = (akar, file) => {
    if (!dialogPratinjau || !file) return;
    akarAktif = akar;
    dialogPratinjau.querySelector('[data-pratinjau-judul]').textContent = akar.dataset.label;
    dialogPratinjau.querySelector('[data-pratinjau-info]').textContent = `${file.name} · ${formatUkuran(file.size)}`;

    const isi = dialogPratinjau.querySelector('[data-pratinjau-isi]');
    isi.replaceChildren();
    urlPratinjau = URL.createObjectURL(file);

    if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = urlPratinjau;
        img.alt = file.name;
        img.className = 'mx-auto max-h-[60vh] max-w-full rounded-lg object-contain';
        isi.append(img);
    } else if (file.type === 'application/pdf') {
        const frame = document.createElement('iframe');
        frame.src = urlPratinjau;
        frame.title = file.name;
        frame.className = 'h-[60vh] w-full rounded-lg border border-gray-200';
        const tautan = document.createElement('a');
        tautan.href = urlPratinjau;
        tautan.target = '_blank';
        tautan.rel = 'noopener';
        tautan.className = 'mt-3 inline-block text-sm font-semibold text-brand hover:text-brand-dark';
        tautan.textContent = 'Buka di tab baru';
        isi.append(frame, tautan);
    } else {
        const p = document.createElement('p');
        p.className = 'py-10 text-center text-sm text-gray-500';
        p.textContent = 'Pratinjau tidak tersedia untuk format ini. Pastikan nama berkas di atas sudah benar.';
        isi.append(p);
    }

    dialogPratinjau.showModal();
};

document.querySelectorAll('[data-unggah]').forEach((akar) => {
    const input = akar.querySelector('[data-unggah-input]');
    const kameraInput = akar.querySelector('[data-unggah-kamera-input]');
    const tombolKamera = akar.querySelector('[data-unggah-kamera]');
    const kotakIsi = akar.querySelector('[data-unggah-kotak]');
    const kosong = akar.querySelector('[data-unggah-kosong]');
    const lihat = akar.querySelector('[data-unggah-lihat]');
    const hapus = akar.querySelector('[data-unggah-hapus]');
    const mini = akar.querySelector('[data-unggah-mini]');
    const nama = akar.querySelector('[data-unggah-nama]');
    const ukuran = akar.querySelector('[data-unggah-ukuran]');
    const pesan = akar.querySelector('[data-error-for]');
    let urlMini = null;

    const setPesan = (teks) => {
        pesan.textContent = teks || '';
        pesan.hidden = !teks;
        kotakIsi.toggleAttribute('data-error', !!teks);
    };

    const segarkan = () => {
        const file = input.files[0];
        if (urlMini) {
            URL.revokeObjectURL(urlMini);
            urlMini = null;
        }
        kosong.hidden = !!file;
        lihat.hidden = !file;
        hapus.hidden = !file;
        mini.replaceChildren();
        if (!file) return;

        nama.textContent = file.name;
        ukuran.textContent = `${formatUkuran(file.size)} · Ketuk untuk melihat pratinjau`;
        if (file.type.startsWith('image/')) {
            urlMini = URL.createObjectURL(file);
            const img = document.createElement('img');
            img.src = urlMini;
            img.alt = '';
            img.className = 'h-full w-full object-cover';
            mini.append(img);
        } else {
            mini.textContent = (file.name.split('.').pop() || 'file').toUpperCase().slice(0, 4);
        }
    };

    const layak = (file) => {
        const boleh = input.dataset.ekstensi.split(',');
        const ekstensi = file.name.split('.').pop().toLowerCase();
        if (!boleh.includes(ekstensi)) {
            setPesan(`Format harus ${boleh.join('/').toUpperCase()}.`);
            return false;
        }
        const batas = Number(input.dataset.max) * 1024;
        if (file.size > batas) {
            setPesan(`Ukuran maksimal ${Math.round(batas / 1048576)} MB.`);
            return false;
        }
        return true;
    };

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (file && !layak(file)) {
            input.value = '';
        } else {
            setPesan('');
        }
        segarkan();
    });

    // Foto dari kamera HP dipindahkan ke input utama agar ikut terkirim
    kameraInput?.addEventListener('change', () => {
        const file = kameraInput.files[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        kameraInput.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    akar.querySelector('[data-unggah-pilih]').addEventListener('click', () => input.click());
    tombolKamera?.addEventListener('click', () => kameraInput.click());
    hapus.addEventListener('click', () => {
        input.value = '';
        setPesan('');
        segarkan();
    });
    lihat.addEventListener('click', () => bukaPratinjau(akar, input.files[0]));

    // Tombol Foto hanya relevan di layar sentuh (HP / tablet)
    if (tombolKamera && window.matchMedia('(pointer: coarse)').matches) tombolKamera.hidden = false;

    segarkan();
});

// ===== Form bertahap + kirim lewat fetch (berkas tidak hilang saat ada error) =====
document.querySelectorAll('[data-stepper]').forEach((form) => {
    const panel = [...form.querySelectorAll('[data-step]')];
    const indikator = [...document.querySelectorAll('[data-step-indikator]')];
    const garis = document.querySelector('[data-step-garis]');
    const progres = document.querySelector('[data-step-progres]');
    const hitung = document.querySelector('[data-step-hitung]');
    const penanda = document.querySelector('[data-step-penanda]');
    const pesanForm = document.querySelector('[data-form-pesan]');
    const terakhir = panel.length;
    let sekarang = Number(form.dataset.stepAwal || 1);

    const keAtas = () => penanda?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const tampil = (no) => {
        sekarang = no;
        panel.forEach((p) => (p.hidden = Number(p.dataset.step) !== no));
        indikator.forEach((i) => {
            const n = Number(i.dataset.stepIndikator);
            i.dataset.state = n < no ? 'selesai' : n === no ? 'aktif' : 'belum';
        });
        garis?.classList.toggle('border-brand', no > 1);
        garis?.classList.toggle('border-gray-300', no <= 1);
        if (progres) progres.style.width = `${(no / terakhir) * 100}%`;
        if (hitung) hitung.textContent = `${no}/${terakhir}`;
    };

    // ---- pesan error ----
    const umum = (teks) => {
        pesanForm.textContent = teks;
        pesanForm.hidden = !teks;
    };

    const tampilError = (nama, teks) => {
        let el = form.querySelector(`[data-error-for="${nama}"]`);
        if (!el) {
            const input = form.querySelector(`[name="${nama}"]`);
            if (!input) return null;
            el = document.createElement('p');
            el.dataset.errorFor = nama;
            el.dataset.dinamis = '';
            el.className = 'mt-1 text-sm text-red-600';
            input.insertAdjacentElement('afterend', el);
            input.classList.add('border-red-500');
        }
        el.textContent = teks;
        el.hidden = false;
        el.closest('[data-unggah]')?.querySelector('[data-unggah-kotak]')?.setAttribute('data-error', '');
        el.closest('[data-kustom]')?.querySelector('[data-kustom-tombol]')?.classList.add('border-red-500');
        return el;
    };

    const bersihkanError = () => {
        umum('');
        form.querySelectorAll('[data-dinamis]').forEach((el) => el.remove());
        form.querySelectorAll('[data-error-for]').forEach((el) => {
            el.hidden = true;
            el.textContent = '';
        });
        form.querySelectorAll('.border-red-500').forEach((el) => el.classList.remove('border-red-500'));
        form.querySelectorAll('[data-unggah-kotak]').forEach((el) => el.removeAttribute('data-error'));
    };

    // Error pada satu isian hilang begitu isian itu diubah (berkas diurus komponen unggah)
    const bersihkanSatu = (e) => {
        const nama = e.target.name;
        if (!nama || e.target.type === 'file') return;
        const el = form.querySelector(`[data-error-for="${nama}"]`);
        if (!el) return;
        e.target.classList.remove('border-red-500');
        e.target.closest('[data-kustom]')?.querySelector('[data-kustom-tombol]')?.classList.remove('border-red-500');
        if ('dinamis' in el.dataset) {
            el.remove();
        } else {
            el.hidden = true;
            el.textContent = '';
        }
    };
    form.addEventListener('input', bersihkanSatu);
    form.addEventListener('change', bersihkanSatu);

    // ---- pemeriksaan ----
    // Dropdown dan tanggal buatan sendiri memakai input tersembunyi, jadi diperiksa manual
    const periksaKustom = (p) => {
        for (const akar of p.querySelectorAll('[data-kustom][data-wajib]')) {
            const nilai = akar.querySelector('[data-kustom-nilai]');
            if (nilai.value) continue;
            tampilError(nilai.name, `${akar.dataset.label} wajib diisi.`);
            akar.scrollIntoView({ behavior: 'smooth', block: 'center' });
            akar.querySelector('[data-kustom-tombol]').focus({ preventScroll: true });
            return false;
        }
        return true;
    };

    const periksaPanel = (p) => {
        for (const el of p.querySelectorAll('input:not([type=file]), select, textarea')) {
            if (!el.reportValidity()) return false;
        }
        return periksaKustom(p);
    };

    const periksaBerkas = () => {
        let pertama = null;
        form.querySelectorAll('[data-unggah-input][data-wajib]').forEach((inp) => {
            if (inp.files.length) return;
            const akar = inp.closest('[data-unggah]');
            tampilError(inp.name, `${akar.dataset.label} wajib diunggah.`);
            pertama ??= akar;
        });
        if (pertama) pertama.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return !pertama;
    };

    const lanjut = () => {
        if (!periksaPanel(panel[sekarang - 1])) return;
        tampil(Math.min(sekarang + 1, terakhir));
        keAtas();
    };

    const tampilkanError = (errors) => {
        let pertama = null;
        let langkah = Infinity;
        let tanpaKolom = null;

        Object.entries(errors).forEach(([nama, daftar]) => {
            const el = tampilError(nama, daftar[0]);
            if (!el) {
                tanpaKolom ??= daftar[0];
                return;
            }
            const no = Number(el.closest('[data-step]')?.dataset.step || 1);
            if (no < langkah) {
                langkah = no;
                pertama = el;
            }
        });

        umum(tanpaKolom || 'Mohon periksa isian yang ditandai merah.');
        if (pertama) {
            tampil(langkah); // tampilkan dulu tahapnya, baru gulir ke kolom bermasalah
            pertama.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    // ---- navigasi ----
    form.addEventListener('click', (e) => {
        if (e.target.closest('[data-step-lanjut]')) lanjut();
        if (e.target.closest('[data-step-kembali]')) {
            tampil(sekarang - 1);
            keAtas();
        }
    });

    indikator.forEach((i) =>
        i.addEventListener('click', () => {
            const tujuan = Number(i.dataset.stepKe);
            if (tujuan < sekarang) {
                tampil(tujuan);
                keAtas();
            } else if (tujuan > sekarang) {
                lanjut();
            }
        }),
    );

    // ---- kirim ----
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (sekarang < terakhir) {
            lanjut();
            return;
        }
        if (!periksaPanel(panel[sekarang - 1])) return;
        bersihkanError();
        if (!periksaBerkas()) return;

        const tombol = form.querySelector('[data-kirim]');
        const teksAwal = tombol.textContent;
        let berhasil = false;
        tombol.disabled = true;
        tombol.textContent = 'Mengirim...';

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.redirect) {
                berhasil = true;
                window.location.href = data.redirect;
            } else if (res.status === 422 && data.errors) {
                tampilkanError(data.errors);
            } else if (res.status === 413) {
                umum('Total ukuran berkas terlalu besar. Perkecil ukuran berkas lalu coba lagi.');
            } else {
                umum(data.message || 'Terjadi kesalahan. Silakan coba lagi.');
            }
        } catch {
            umum('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
        } finally {
            if (!berhasil) {
                tombol.disabled = false;
                tombol.textContent = teksAwal;
            }
        }
    });

    tampil(sekarang);
});

// ===== Dropdown & pemilih tanggal buatan sendiri (selalu terbuka ke bawah) =====
const tutupKustom = (kecuali = null) => {
    document.querySelectorAll('[data-kustom][data-terbuka]').forEach((akar) => {
        if (akar === kecuali) return;
        akar.querySelector('[data-kustom-panel]').hidden = true;
        akar.removeAttribute('data-terbuka');
        akar.querySelector('[data-kustom-tombol]').setAttribute('aria-expanded', 'false');
    });
};

const bukaKustom = (akar) => {
    tutupKustom(akar);
    const panel = akar.querySelector('[data-kustom-panel]');
    panel.hidden = false;
    akar.dataset.terbuka = '';
    akar.querySelector('[data-kustom-tombol]').setAttribute('aria-expanded', 'true');
    // Bila ruang di bawah sempit, halaman yang digulir (bukan daftar dibalik ke atas)
    panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

document.addEventListener('click', (e) => {
    // composedPath dipakai karena isi panel bisa digambar ulang saat diklik
    const jalur = e.composedPath();
    const tombol = jalur.find((n) => n.matches?.('[data-kustom-tombol]'));
    if (tombol) {
        const akar = tombol.closest('[data-kustom]');
        if (akar.hasAttribute('data-terbuka')) tutupKustom();
        else bukaKustom(akar);
        return;
    }
    if (!jalur.some((n) => n.matches?.('[data-kustom-panel]'))) tutupKustom();
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const buka = document.querySelector('[data-kustom][data-terbuka]');
    if (!buka) return;
    tutupKustom();
    buka.querySelector('[data-kustom-tombol]').focus();
});

// ---- Dropdown pilihan ----
document.querySelectorAll('[data-pilihan]').forEach((akar) => {
    const nilai = akar.querySelector('[data-kustom-nilai]');
    const teks = akar.querySelector('[data-pilihan-teks]');
    const tombol = akar.querySelector('[data-kustom-tombol]');
    const panel = akar.querySelector('[data-kustom-panel]');
    const opsi = [...panel.querySelectorAll('[role=option]')];

    const pilih = (o) => {
        opsi.forEach((x) => x.setAttribute('aria-selected', String(x === o)));
        nilai.value = o.dataset.nilai;
        teks.textContent = o.dataset.teks;
        teks.classList.remove('text-gray-400');
        nilai.dispatchEvent(new Event('change', { bubbles: true }));
        tutupKustom();
        tombol.focus();
    };

    opsi.forEach((o) => o.addEventListener('click', () => pilih(o)));

    tombol.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
        e.preventDefault();
        if (!akar.hasAttribute('data-terbuka')) bukaKustom(akar);
        (panel.querySelector('[aria-selected=true]') || opsi[0]).focus({ preventScroll: true });
    });

    panel.addEventListener('keydown', (e) => {
        const i = opsi.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            opsi[Math.min(i + 1, opsi.length - 1)].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            opsi[Math.max(i - 1, 0)].focus();
        } else if ((e.key === 'Enter' || e.key === ' ') && i >= 0) {
            e.preventDefault();
            pilih(opsi[i]);
        }
    });
});

// ---- Pemilih tanggal: kalender bulanan (tahun -> bulan -> tanggal) ----
const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const BULAN_SINGKAT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const NAMA_HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const TAHUN_PER_HALAMAN = 28; // 7 kolom x 4 baris

document.querySelectorAll('[data-tanggal]').forEach((akar) => {
    const nilai = akar.querySelector('[data-kustom-nilai]');
    const teks = akar.querySelector('[data-tanggal-teks]');
    const tombol = akar.querySelector('[data-kustom-tombol]');
    const panel = akar.querySelector('[data-kustom-panel]');
    const isi = akar.querySelector('[data-tanggal-isi]');

    const maks = akar.dataset.max ? new Date(`${akar.dataset.max}T00:00:00`) : null;
    const tahunMaks = maks ? maks.getFullYear() : new Date().getFullYear();
    const bulanMaks = maks ? maks.getMonth() : 11;
    const tahunMin = Number(akar.dataset.tahunMin) || tahunMaks - 70;

    let pilihan = null; // {t, b, h}: tanggal yang dipilih, belum diterapkan
    let tampilan = 'tahun'; // 'tahun' | 'bulan' | 'hari'
    let tNav = tahunMaks; // tahun yang sedang tampil
    let bNav = 0; // bulan yang sedang tampil (0-11)
    let halaman = 0; // halaman daftar tahun; 0 = halaman yang memuat tahun terbaru

    const dua = (n) => String(n).padStart(2, '0');
    const POLA = /^(\d{4})-(\d{2})-(\d{2})$/;

    const buat = (tag, kelas = '', isiTeks = '') => {
        const e = document.createElement(tag);
        if (kelas) e.className = kelas;
        if (isiTeks) e.textContent = isiTeks;
        return e;
    };

    const tombolKecil = (label, kelas, onClick, mati = false) => {
        const b = buat('button', kelas, label);
        b.type = 'button';
        b.disabled = mati;
        b.addEventListener('click', onClick);
        return b;
    };

    // Kotak yang sama untuk tahun, bulan, dan tanggal
    const kotak = (aktif, tambahan = '') =>
        'flex h-9 w-9 items-center justify-center rounded-lg text-sm transition disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent ' +
        (aktif ? 'bg-brand font-bold text-white' : `hover:bg-brand-soft ${tambahan}`);

    const grid = () => buat('div', 'grid grid-cols-7 justify-items-center gap-y-1');

    const segarkanTeks = () => {
        const m = POLA.exec(nilai.value);
        if (!m) {
            teks.textContent = akar.dataset.placeholder;
            teks.classList.add('text-gray-400');
            return;
        }
        teks.textContent = `${m[3]}/${m[2]}/${m[1]}`; // 17/09/1993
        teks.classList.remove('text-gray-400');
    };

    const halamanDariTahun = (t) => Math.floor((tahunMaks - t) / TAHUN_PER_HALAMAN);

    const geserBulan = (arah) => {
        bNav += arah;
        if (bNav < 0) {
            bNav = 11;
            tNav -= 1;
        }
        if (bNav > 11) {
            bNav = 0;
            tNav += 1;
        }
        gambar();
    };

    const aturUlang = () => {
        pilihan = null;
        nilai.value = '';
        segarkanTeks();
        nilai.dispatchEvent(new Event('change', { bubbles: true }));
        tNav = tahunMaks;
        bNav = 0;
        halaman = 0;
        tampilan = 'tahun';
        gambar();
    };

    const terapkan = () => {
        if (!pilihan) return;
        nilai.value = `${pilihan.t}-${dua(pilihan.b + 1)}-${dua(pilihan.h)}`;
        segarkanTeks();
        nilai.dispatchEvent(new Event('change', { bubbles: true }));
        tutupKustom();
        tombol.focus();
    };

    // Kepala seragam di semua tampilan: ‹  judul  ›
        const kepala = (judul, { bisaMundur, bisaMaju, mundur, maju, naik = null }) => {
        const panah = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xl leading-none hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent';
        const kiri = tombolKecil('‹', panah, mundur, !bisaMundur);
        const kanan = tombolKecil('›', panah, maju, !bisaMaju);
        kiri.setAttribute('aria-label', 'Sebelumnya');
        kanan.setAttribute('aria-label', 'Berikutnya');

        const tengah = naik
            ? tombolKecil(judul, 'rounded-lg px-2 py-1 text-sm font-bold hover:bg-brand-soft', naik)
            : buat('p', 'px-2 py-1 text-sm font-bold', judul);

        const baris = buat('div', 'mb-2 flex items-center justify-between gap-2');
        baris.append(kiri, tengah, kanan);
        return baris;
    };

    const tampilTahun = () => {
        const akhir = tahunMaks - halaman * TAHUN_PER_HALAMAN;
        const awal = akhir - TAHUN_PER_HALAMAN + 1;

        const k = kepala(`${Math.max(awal, tahunMin)} – ${akhir}`, {
            bisaMundur: awal > tahunMin,
            bisaMaju: halaman > 0,
            mundur: () => {
                halaman += 1;
                gambar();
            },
            maju: () => {
                halaman -= 1;
                gambar();
            },
        });

        const g = grid();
        for (let t = awal; t <= akhir; t++) {
            if (t < tahunMin) {
                g.append(buat('span'));
                continue;
            }
            g.append(
                tombolKecil(String(t), kotak(pilihan?.t === t), () => {
                    tNav = t;
                    tampilan = 'bulan';
                    gambar();
                }),
            );
        }
        return [k, g];
    };

    const tampilBulan = () => {
        const k = kepala(String(tNav), {
            bisaMundur: tNav > tahunMin,
            bisaMaju: tNav < tahunMaks,
            mundur: () => {
                tNav -= 1;
                gambar();
            },
            maju: () => {
                tNav += 1;
                gambar();
            },
            naik: () => {
                halaman = halamanDariTahun(tNav);
                tampilan = 'tahun';
                gambar();
            },
        });

        const g = grid();
        BULAN_SINGKAT.forEach((nama, i) => {
            g.append(
                tombolKecil(
                    nama,
                    kotak(pilihan?.t === tNav && pilihan?.b === i),
                    () => {
                        bNav = i;
                        tampilan = 'hari';
                        gambar();
                    },
                    tNav === tahunMaks && i > bulanMaks,
                ),
            );
        });
        return [k, g];
    };

    const tampilHari = () => {
        const k = kepala(`${NAMA_BULAN[bNav]} ${tNav}`, {
            bisaMundur: tNav > tahunMin || bNav > 0,
            bisaMaju: tNav < tahunMaks || bNav < bulanMaks,
            mundur: () => geserBulan(-1),
            maju: () => geserBulan(1),
            naik: () => {
                tampilan = 'bulan';
                gambar();
            },
        });

        const g = grid();
        NAMA_HARI.forEach((h, i) => g.append(buat('span', `py-1 text-center text-xs font-semibold ${i === 0 ? 'text-red-600' : 'text-gray-500'}`, h)));

        const awal = new Date(tNav, bNav, 1).getDay();
        for (let i = 0; i < awal; i++) g.append(buat('span'));

        const jumlah = new Date(tNav, bNav + 1, 0).getDate();
        for (let h = 1; h <= jumlah; h++) {
            const minggu = new Date(tNav, bNav, h).getDay() === 0;
            const aktif = !!pilihan && pilihan.t === tNav && pilihan.b === bNav && pilihan.h === h;
            const mati = !!maks && tNav === tahunMaks && bNav === bulanMaks && h > maks.getDate();
            g.append(
                tombolKecil(
                    String(h),
                    kotak(aktif, minggu ? 'text-red-600' : ''),
                    () => {
                        pilihan = { t: tNav, b: bNav, h };
                        gambar();
                    },
                    mati,
                ),
            );
        }
        return [k, g];
    };

    const gambar = () => {
        const [k, g] = tampilan === 'tahun' ? tampilTahun() : tampilan === 'bulan' ? tampilBulan() : tampilHari();

        const kaki = buat('div', 'mt-3 grid grid-cols-2 gap-2');
        kaki.append(
            tombolKecil('Atur ulang', 'btn btn-outline py-2', aturUlang),
            tombolKecil('Terapkan', 'btn btn-primary py-2', terapkan, !pilihan),
        );

        isi.replaceChildren(k, g, kaki);

        if (akar.hasAttribute('data-terbuka')) panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    // Setiap kali dibuka: sudah ada tanggal -> langsung ke kalender bulannya, kosong -> mulai dari tahun
    tombol.addEventListener('click', () => {
        if (akar.hasAttribute('data-terbuka')) return; // klik ini akan menutup panel
        const m = POLA.exec(nilai.value);
        pilihan = m ? { t: +m[1], b: +m[2] - 1, h: +m[3] } : null;
        if (pilihan) {
            tNav = pilihan.t;
            bNav = pilihan.b;
            tampilan = 'hari';
        } else {
            tNav = tahunMaks;
            bNav = 0;
            halaman = 0;
            tampilan = 'tahun';
        }
        gambar();
    });

    segarkanTeks();
});

// ===== Tombol "Salin kode" di halaman lamaran terkirim =====
document.addEventListener('click', async (e) => {
    const tombol = e.target.closest('[data-salin]');
    if (!tombol) return;
    const awal = tombol.textContent;
    try {
        await navigator.clipboard.writeText(tombol.dataset.salin);
        tombol.textContent = 'Tersalin ✓';
    } catch {
        tombol.textContent = 'Gagal menyalin';
    }
    setTimeout(() => (tombol.textContent = awal), 2000);
});
