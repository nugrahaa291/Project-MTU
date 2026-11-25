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
