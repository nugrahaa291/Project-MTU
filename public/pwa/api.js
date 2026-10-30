// Klien tipis untuk API absensi (Laravel Sanctum, token Bearer).
export class ApiError extends Error {
    constructor(status, data) {
        const pertama = data?.errors ? Object.values(data.errors).flat()[0] : null;
        super(pertama || data?.message || `Terjadi kesalahan (${status}).`);
        this.status = status;
        this.kode = data?.kode ?? null;
        this.errors = data?.errors ?? null;
    }
}

export function buatApi({ fetchFn = (...a) => fetch(...a), ambilToken, saatTidakSah, base = '/api' }) {
    async function minta(method, path, { json, form } = {}) {
        const headers = { Accept: 'application/json' };
        const token = ambilToken();
        if (token) headers.Authorization = `Bearer ${token}`;

        let body;
        if (json) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(json);
        } else if (form) {
            body = form; // Content-Type (multipart + boundary) diatur browser sendiri
        }

        let res;
        try {
            res = await fetchFn(base + path, { method, headers, body });
        } catch {
            throw new ApiError(0, { message: 'Tidak dapat terhubung ke server. Periksa koneksi internet.', kode: 'jaringan' });
        }

        let data = null;
        try {
            data = await res.json();
        } catch {
            /* balasan bukan JSON */
        }

        if (!res.ok) {
            // Token kedaluwarsa/dicabut. Login yang salah (belum punya token) tidak ikut memicu ini.
            if (res.status === 401 && token) saatTidakSah?.();
            throw new ApiError(res.status, data);
        }
        return data;
    }

    return {
        login: (email, password, deviceName) => minta('POST', '/login', { json: { email, password, device_name: deviceName } }),
        logout: () => minta('POST', '/logout'),
        me: () => minta('GET', '/me'),
        gantiPassword: (payload) => minta('PUT', '/password', { json: payload }),
        hariIni: () => minta('GET', '/absensi/hari-ini'),
        masuk: (form) => minta('POST', '/absensi/masuk', { form }),
        pulang: (form) => minta('POST', '/absensi/pulang', { form }),
        riwayat: (bulan) => minta('GET', `/absensi?bulan=${encodeURIComponent(bulan)}`),
    };
}
