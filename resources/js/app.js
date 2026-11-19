// Menu samping (panel) pada layar kecil
document.addEventListener('click', (e) => {
    const tombol = e.target.closest('[data-toggle-sidebar]');
    if (!tombol) return;
    const tertutup = document.getElementById('sidebar').classList.toggle('hidden');
    tombol.setAttribute('aria-expanded', String(!tertutup));
});

// Konfirmasi sebelum aksi berbahaya: <form data-konfirmasi="Yakin?">
document.addEventListener('submit', (e) => {
    const pesan = e.target.dataset?.konfirmasi;
    const pengganti = e.target.elements.pengganti_id?.selectedOptions[0]?.textContent;
    if (pesan && !window.confirm(pengganti ? pesan + '\n' + pengganti : pesan)) return e.preventDefault();
    if (e.defaultPrevented || e.target.method.toLowerCase() !== 'post' || !e.submitter || e.submitter.name) return;
    const tombol = e.submitter;
    tombol.dataset.teksAwal = tombol.textContent;
    tombol.textContent = 'Memproses…';
    tombol.disabled = true;
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-teks-awal]').forEach(tombol => {
        tombol.textContent = tombol.dataset.teksAwal;
        tombol.disabled = false;
        delete tombol.dataset.teksAwal;
    });
});

document.querySelectorAll('.table-wrap').forEach(wadah => {
    const petunjuk = document.createElement('p');
    petunjuk.className = 'mb-1 text-xs text-gray-600';
    petunjuk.textContent = 'Geser untuk melihat semua kolom →';
    wadah.before(petunjuk);
    new ResizeObserver(() => {
        petunjuk.hidden = wadah.scrollWidth <= wadah.clientWidth;
        wadah.tabIndex = petunjuk.hidden ? -1 : 0;
    }).observe(wadah);
});

const lamaran = document.querySelector('[data-lamaran]');
if (lamaran) {
    const wajib = [...lamaran.querySelectorAll('[required]')];
    const meter = lamaran.querySelector('progress');
    meter.max = wajib.length;
    const perbarui = () => {
        meter.value = wajib.filter(kolom => kolom.value.trim() && kolom.validity.valid).length;
        lamaran.querySelector('[data-jumlah]').textContent = `${meter.value} dari ${meter.max} isian wajib lengkap`;
    };
    lamaran.addEventListener('input', perbarui);
    window.addEventListener('pageshow', perbarui);
    perbarui();
    lamaran.querySelector('[data-kemajuan]').hidden = false;
    lamaran.querySelectorAll('input[type=file]').forEach(input => {
        const info = document.createElement('p');
        info.className = 'mt-1 min-h-5 break-all text-sm text-ink-soft';
        info.setAttribute('role', 'status');
        input.after(info);
        input.addEventListener('change', () => {
            const file = input.files[0];
            info.textContent = file ? `${file.name} · ${Math.max(1, Math.ceil(file.size / 1024))} KB` : '';
        });
    });
}

document.querySelector('[data-salin]')?.addEventListener('click', async () => {
    const kode = document.getElementById('kode-lamaran');
    const status = document.querySelector('[data-status-salin]');
    try {
        await navigator.clipboard.writeText(kode.textContent.trim());
        status.textContent = 'Kode tersalin.';
    } catch {
        const rentang = document.createRange();
        rentang.selectNodeContents(kode);
        const pilihan = window.getSelection();
        pilihan.removeAllRanges();
        pilihan.addRange(rentang);
        status.textContent = 'Kode dipilih. Gunakan perintah Salin di perangkat Anda.';
    }
});
