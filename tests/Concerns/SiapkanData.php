<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Enums\StatusLamaran;
use App\Models\Jabatan;
use App\Models\Lamaran;
use App\Models\Lowongan;
use App\Models\User;
use App\Services\KaryawanService;
use Illuminate\Http\UploadedFile;

trait SiapkanData
{
    protected function hrd(): User
    {
        return User::factory()->hrd()->create();
    }

    protected function lowongan(array $override = []): Lowongan
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Staff IT'], ['aktif' => true]);

        return Lowongan::create([
            'judul' => 'Staff IT Support',
            'jabatan_id' => $jabatan->id,
            'lokasi' => 'Karawang',
            'kuota' => 2,
            'tanggal_buka' => today()->subDay(),
            'tanggal_tutup' => today()->addDays(10),
            'status' => 'dibuka',
            ...$override,
        ]);
    }

    /** Berkas palsu dengan ISI yang valid (validasi mimes membaca isi file). */
    protected function png(string $nama = 'foto.png'): UploadedFile
    {
        $isi = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    protected function pdf(string $nama = 'cv.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
    }

    protected function dataLamaran(array $override = []): array
    {
        return [
            'nik' => '3215010101990001',
            'nama' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'no_hp' => '081234567890',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Karawang',
            'tanggal_lahir' => '1998-05-10',
            'alamat' => 'Jl. Merdeka No. 1, Karawang',
            'pendidikan' => 'S1',
            'cv' => $this->pdf(),
            'pas_foto' => $this->png('pasfoto.png'),
            'ktp' => $this->png('ktp.png'),
            ...$override,
        ];
    }

    /** Lamaran langsung di database (tanpa lewat form), dengan status tertentu. */
    protected function lamaran(Lowongan $lowongan, array $override = []): Lamaran
    {
        static $urut = 0;
        $urut++;

        return Lamaran::create([
            'kode' => 'LMR-TEST'.str_pad((string) $urut, 4, '0', STR_PAD_LEFT),
            'lowongan_id' => $lowongan->id,
            'nik' => '32150101019900'.str_pad((string) $urut, 2, '0', STR_PAD_LEFT),
            'nama' => 'Pelamar '.$urut,
            'email' => "pelamar{$urut}@example.com",
            'no_hp' => '0812345678'.str_pad((string) $urut, 2, '0', STR_PAD_LEFT),
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Bekasi',
            'tanggal_lahir' => '1999-01-01',
            'alamat' => 'Alamat',
            'pendidikan' => 'S1',
            'cv' => 'x/cv.pdf', 'pas_foto' => 'x/f.png', 'ktp' => 'x/k.png',
            'status' => StatusLamaran::Diajukan,
            ...$override,
        ]);
    }

    /** Karyawan aktif lengkap dengan akun login (password: "RahasiaBaru1"). */
    protected function karyawan(array $override = [], bool $wajibGantiPassword = false): \App\Models\Karyawan
    {
        static $n = 0;
        $n++;

        [$karyawan] = app(KaryawanService::class)->buat([
            'nama' => 'Karyawan '.$n,
            'email' => "karyawan{$n}@example.com",
            'nik' => '32150202029900'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'no_hp' => '0813000000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'jabatan_id' => Jabatan::firstOrCreate(['nama' => 'Staff IT'], ['aktif' => true])->id,
            'tanggal_masuk' => '2026-01-01',
            ...$override,
        ]);

        $karyawan->user->password = 'RahasiaBaru1';
        $karyawan->user->forceFill(['must_change_password' => $wajibGantiPassword])->save();

        return $karyawan->refresh();
    }
}
