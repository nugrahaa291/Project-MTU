<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\Pelamar;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginKaryawan
{
    public function verifikasi(string $nip, string $password, string $ip): User
    {
        $nip = trim($nip);
        $kunciNip = 'login-nip:'.mb_strtolower($nip);
        $kunciIp = 'login-nip-ip:'.mb_strtolower($nip).'|'.$ip;
        if (RateLimiter::tooManyAttempts($kunciIp, 5) || RateLimiter::tooManyAttempts($kunciNip, 20)) {
            $detik = max(RateLimiter::availableIn($kunciIp), RateLimiter::availableIn($kunciNip));
            throw ValidationException::withMessages(['nip' => 'Terlalu banyak percobaan. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit.'])->status(429);
        }

        $karyawan = Karyawan::with('user')->where('nip', $nip)->first();
        $user = $karyawan?->user;
        static $hashPalsu;
        $cocok = strlen($password) <= 72 && ! str_contains($password, "\0") && Hash::check($password, $user?->password ?? ($hashPalsu ??= Hash::make('tidak-pernah-cocok')));
        if (! $cocok || ! $user?->isKaryawan() || ! $user->is_active || ! $karyawan->aktif) {
            RateLimiter::hit($kunciIp, 300);
            RateLimiter::hit($kunciNip, 3600);
            throw ValidationException::withMessages(['nip' => 'NIP atau password salah, atau akun dinonaktifkan.'])->status(401);
        }

        RateLimiter::clear($kunciIp);
        if (! $user->must_change_password && preg_match('/^[0-9]{6}$/D', $password)) {
            User::whereKey($user->id)->where('password', $user->password)->update(['must_change_password' => true]);
            $user->must_change_password = true;
        }
        $user->setRelation('karyawan', $karyawan);

        return $user;
    }

    public function profilPelamar(User $user, ?Pelamar $pemilik): Pelamar
    {
        return DB::transaction(function () use ($user, $pemilik) {
            $akun = User::lockForUpdate()->findOrFail($user->id);
            if (! hash_equals($akun->password, $user->password) || ! $akun->is_active || $akun->must_change_password || ! $akun->isKaryawan() || ! $akun->karyawan?->aktif) {
                throw ValidationException::withMessages(['nip' => 'Akun berubah. Silakan masuk kembali.']);
            }
            $terhubung = Pelamar::where('user_id', $akun->id)->first();
            if ($terhubung) {
                if ($pemilik && ! $pemilik->is($terhubung)) {
                    throw ValidationException::withMessages(['nip' => 'NIP sudah terhubung dengan akun pelamar lain.']);
                }

                return $terhubung->setRelation('user', $akun);
            }

            $asal = $akun->karyawan->lamaran?->pelamar_id;
            if ($pemilik) {
                $pelamar = Pelamar::lockForUpdate()->findOrFail($pemilik->id);
                if ($pelamar->user_id || ($asal && $asal !== $pelamar->id)) {
                    throw ValidationException::withMessages(['nip' => 'Gunakan akun pelamar asal untuk menghubungkan NIP ini.']);
                }
            } else {
                $email = Str::lower($akun->email);
                if ($asal || Pelamar::where('email', $email)->exists()) {
                    throw ValidationException::withMessages(['nip' => 'Masuk dahulu dengan email atau Google akun pelamar lama. Di Lamaran Saya, pilih Hubungkan akun karyawan.']);
                }
                $pelamar = new Pelamar(['nama' => $akun->name, 'email' => $email]);
            }

            $pelamar->forceFill(['user_id' => $akun->id, 'password' => null])->save();

            return $pelamar->setRelation('user', $akun);
        });
    }
}
