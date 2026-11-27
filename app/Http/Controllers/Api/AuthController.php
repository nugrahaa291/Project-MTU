<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Karyawan;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** Batas salah-PIN: per (NIP + alamat IP) dan per NIP secara keseluruhan. PIN hanya 6 digit, jadi wajib dibatasi. */
    private const MAKS_PER_IP = 5;      // dalam 5 menit
    private const MAKS_PER_NIP = 20;    // dalam 1 jam

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'string', 'max:64'],
            'device_name' => ['required', 'string', 'max:100'], // contoh: "Pixel 7 - Budi"
        ]);

        $nip = trim($data['nip']);
        $kunciIp = 'login-nip-ip:'.mb_strtolower($nip).'|'.$request->ip();
        $kunciNip = 'login-nip:'.mb_strtolower($nip);

        if (RateLimiter::tooManyAttempts($kunciIp, self::MAKS_PER_IP) || RateLimiter::tooManyAttempts($kunciNip, self::MAKS_PER_NIP)) {
            $tunggu = max(RateLimiter::availableIn($kunciIp), RateLimiter::availableIn($kunciNip));

            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Coba lagi dalam '.max(1, (int) ceil($tunggu / 60)).' menit.',
                'kode' => 'terlalu_banyak_percobaan',
            ], 429);
        }

        $karyawan = Karyawan::with('user')->where('nip', $nip)->first();
        $user = $karyawan?->user;

        // Pemeriksaan hash selalu dijalankan (juga untuk NIP yang tidak ada) agar waktu jawaban tidak membocorkan NIP mana yang terdaftar
        $pinCocok = Hash::check($data['pin'], $user?->password ?? $this->hashPalsu());

        if (! $karyawan || ! $user || ! $pinCocok || ! $user->is_active || ! $karyawan->aktif || $user->role !== Role::Karyawan) {
            RateLimiter::hit($kunciIp, 300);
            RateLimiter::hit($kunciNip, 3600);

            // Pesan sengaja sama untuk semua kegagalan
            return response()->json(['message' => 'NIP atau PIN salah, atau akun dinonaktifkan.', 'kode' => 'login_gagal'], 401);
        }

        RateLimiter::clear($kunciIp);

        // Satu perangkat = satu token. Login ulang di perangkat yang sama mengganti token lama.
        $user->tokens()->where('name', $data['device_name'])->delete();
        $token = $user->createToken($data['device_name'], ['*'], now()->addDays(30));

        ActivityLog::catat($user, 'login_api', 'Login via API mobile: '.$data['device_name']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'must_change_pin' => $user->must_change_password,
            'karyawan' => $this->profil($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'must_change_pin' => $request->user()->must_change_password,
            'karyawan' => $this->profil($request->user()),
        ]);
    }

    /** Mendaftarkan PIN sendiri (wajib setelah memakai PIN sementara dari HRD). */
    public function gantiPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin_lama' => ['required', 'string', 'current_password'],
            'pin' => ['required', 'digits:'.Pin::PANJANG, 'confirmed', 'different:pin_lama', function ($atribut, $nilai, $gagal) {
                if (! Pin::valid((string) $nilai)) {
                    $gagal('PIN terlalu mudah ditebak (angka berulang atau berurutan). Pilih kombinasi lain.');
                }
            }],
        ], [
            'pin_lama.current_password' => 'PIN lama tidak sesuai.',
            'pin.digits' => 'PIN harus berupa '.Pin::PANJANG.' digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama.',
            'pin.different' => 'PIN baru harus berbeda dari PIN lama.',
        ], ['pin_lama' => 'PIN lama', 'pin' => 'PIN baru']);

        $user = $request->user();
        $user->password = $request->pin; // di-hash otomatis oleh cast 'hashed'
        $user->forceFill(['must_change_password' => false])->save();

        // Perangkat lain harus login ulang; perangkat yang sedang dipakai tetap masuk
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'PIN berhasil didaftarkan.']);
    }

    private function profil(User $user): array
    {
        $k = $user->karyawan->loadMissing('jabatan');

        return [
            'nama' => $k->nama,
            'nip' => $k->nip,
            'jabatan' => $k->jabatan->nama,
        ];
    }

    private function hashPalsu(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('tidak-pernah-cocok');
    }
}
