<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\RekrutmenException;
use App\Models\Karyawan;
use App\Models\Lamaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KaryawanService
{
    /**
     * Membuat akun login (User) + data Karyawan sekaligus.
     * Dibungkus transaksi: kalau salah satu gagal, dua-duanya dibatalkan.
     *
     * @param  array{nama:string,email:string,nik:string,no_hp:string,alamat?:?string,jabatan_id:int,tanggal_masuk:string,lamaran_id?:?int}  $data
     * @return array{0: Karyawan, 1: string}  [karyawan, passwordSementara]
     */
    public function buat(array $data): array
    {
        if (Karyawan::where('nik', $data['nik'])->exists()) {
            throw new RekrutmenException('NIK '.$data['nik'].' sudah terdaftar sebagai karyawan.');
        }

        return DB::transaction(function () use ($data) {
            $passwordSementara = self::passwordSementara();

            $user = new User([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => $passwordSementara, // di-hash otomatis oleh cast 'hashed'
            ]);
            // Kolom sensitif diisi lewat forceFill dari kode kita, bukan dari input pengguna
            $user->forceFill([
                'role' => Role::Karyawan,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $karyawan = Karyawan::create([
                'user_id' => $user->id,
                'lamaran_id' => $data['lamaran_id'] ?? null,
                'jabatan_id' => $data['jabatan_id'],
                'nik' => $data['nik'],
                'nama' => $data['nama'],
                'no_hp' => $data['no_hp'],
                'alamat' => $data['alamat'] ?? null,
                'tanggal_masuk' => $data['tanggal_masuk'],
                'aktif' => true,
            ]);

            // NIP = tahun+bulan masuk + nomor urut dari id, contoh: 26100007
            $karyawan->update([
                'nip' => now()->format('ym').str_pad((string) $karyawan->id, 4, '0', STR_PAD_LEFT),
            ]);

            return [$karyawan, $passwordSementara];
        });
    }

    /** Jembatan Lamaran (Hired) -> Karyawan. */
    public function buatDariLamaran(Lamaran $lamaran, string $email, string $tanggalMasuk): array
    {
        $lamaran->loadMissing('lowongan');

        return $this->buat([
            'nama' => $lamaran->nama,
            'email' => $email,
            'nik' => $lamaran->nik,
            'no_hp' => $lamaran->no_hp,
            'alamat' => $lamaran->alamat,
            'jabatan_id' => $lamaran->lowongan->jabatan_id,
            'tanggal_masuk' => $tanggalMasuk,
            'lamaran_id' => $lamaran->id,
        ]);
    }

    /** Membuat password sementara baru (misalnya karyawan lupa password). */
    public function resetPassword(Karyawan $karyawan): string
    {
        $password = self::passwordSementara();

        $user = $karyawan->user;
        $user->password = $password;
        $user->forceFill(['must_change_password' => true])->save();

        // Cabut semua token API: perangkat lama harus login ulang
        $user->tokens()->delete();

        return $password;
    }

    /** Mengaktifkan / menonaktifkan karyawan beserta akun loginnya. */
    public function setAktif(Karyawan $karyawan, bool $aktif): void
    {
        DB::transaction(function () use ($karyawan, $aktif) {
            $karyawan->update([
                'aktif' => $aktif,
                'tanggal_keluar' => $aktif ? null : ($karyawan->tanggal_keluar ?? today()),
            ]);

            $karyawan->user->forceFill(['is_active' => $aktif])->save();

            if (! $aktif) {
                $karyawan->user->tokens()->delete(); // putus akses mobile seketika
            }
        });
    }

    public static function passwordSementara(): string
    {
        // Tanpa karakter yang mirip (0/O, 1/l/I) supaya mudah dibaca dan disalin
        $huruf = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $hasil = '';
        for ($i = 0; $i < 10; $i++) {
            $hasil .= $huruf[random_int(0, strlen($huruf) - 1)];
        }

        return $hasil;
    }
}
