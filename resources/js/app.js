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
