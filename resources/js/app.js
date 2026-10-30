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
