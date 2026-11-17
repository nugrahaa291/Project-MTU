@extends('layouts.panel')
@section('judul', 'Pengaturan Sistem')

@section('content')
    <div class="max-w-3xl">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Konfigurasi Operasional Portal PT</h2>
            <p class="text-sm text-gray-500">Kelola identitas instansi dan aturan operasional absensi harian yang aman diubah melalui antarmuka pengguna.</p>
        </div>

        <form method="POST" action="{{ route('admin.pengaturan.update') }}" class="card space-y-6 p-6">
            @csrf

            <div>
                <div class="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
                    <h3 class="text-md font-bold text-gray-900">Identitas Perusahaan (Operasional Cabang)</h3>
                    <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">
                        {{ $pengaturan['status_kontak'] ?? 'Perlu Verifikasi Admin' }}
                    </span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-field name="nama_perusahaan" label="Nama Perusahaan Resmi" :value="$pengaturan['nama_perusahaan']" required />
                        <p class="text-[11px] text-gray-500 mt-1">Nama resmi entitas operasional: <strong>PT Multi Talent Universal (MTU)</strong>.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field name="lokasi_cabang" label="Unit / Wilayah Operasional Cabang" :value="$pengaturan['lokasi_cabang'] ?? 'Cabang Karawang / Operasional Wilayah'" />
                    </div>
                    <div>
                        <x-field name="email_kontak" label="Email Kontak Cabang" type="email" :value="$pengaturan['email_kontak']" required />
                        <p class="text-[11px] text-amber-600 mt-1">&bull; Data kontak belum terverifikasi penuh; lengkapi dengan kontak resmi cabang.</p>
                    </div>
                    <div>
                        <x-field name="telepon" label="Nomor Telepon Operasional" :value="$pengaturan['telepon']" required />
                        <p class="text-[11px] text-amber-600 mt-1">&bull; Perlu konfirmasi nomor operasional aktif.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field name="alamat_kantor" label="Alamat Kantor / Pool Operasional Cabang" as="textarea" rows="2" :value="$pengaturan['alamat_kantor']" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-md font-bold text-gray-900 border-b border-gray-200 pb-2 mb-4">Aturan Jam Kerja, Driver & Absensi Karyawan</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-field name="toleransi_terlambat_menit" label="Toleransi Keterlambatan (Menit)" type="number" :value="$pengaturan['toleransi_terlambat_menit']" required />
                        <p class="text-[11px] text-gray-500 mt-1">Karyawan absen melebihi batas ini akan ditandai terlambat.</p>
                    </div>
                    <div>
                        <x-field name="min_driver_aktif" label="Kebutuhan Minimum Driver Aktif per Proyek" type="number" :value="$pengaturan['min_driver_aktif'] ?? '2'" required />
                        <p class="text-[11px] text-gray-500 mt-1">Batas minimum driver aktif agar jadwal libur tidak menimbulkan bentrok operasional.</p>
                    </div>
                    <div>
                        <x-field name="jam_masuk_mulai" label="Jam Mulai Masuk Kerja" type="time" :value="$pengaturan['jam_masuk_mulai']" required />
                    </div>
                    <div>
                        <x-field name="jam_masuk_selesai" label="Batas Akhir Jam Masuk" type="time" :value="$pengaturan['jam_masuk_selesai']" required />
                    </div>
                    <div>
                        <x-field name="jam_pulang_mulai" label="Jam Mulai Pulang Kerja" type="time" :value="$pengaturan['jam_pulang_mulai']" required />
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-xs text-blue-900 leading-relaxed">
                <span class="font-bold">Keamanan Konfigurasi:</span> Kredensial sensitif seperti <code>APP_KEY</code>, token enkripsi sesi, dan kata sandi basis data MySQL dikelola eksklusif melalui berkas <code>.env</code> server dan tidak pernah diekspos melalui form web demi menjaga integritas sistem.
            </div>

            <div class="pt-2">
                <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
            </div>
        </form>
    </div>
@endsection
