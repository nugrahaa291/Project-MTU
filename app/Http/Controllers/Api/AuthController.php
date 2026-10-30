<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'], // contoh: "Pixel 7 - Budi"
        ]);

        $user = User::where('email', $data['email'])->first();

        // Pesan sengaja sama untuk semua kegagalan agar tidak membocorkan email mana yang terdaftar
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            return response()->json(['message' => 'Email atau password salah, atau akun dinonaktifkan.'], 401);
        }

        if ($user->role !== Role::Karyawan || ! $user->karyawan) {
            return response()->json(['message' => 'Aplikasi absensi hanya untuk akun karyawan.'], 403);
        }

        // Satu perangkat = satu token. Login ulang di perangkat yang sama mengganti token lama.
        $user->tokens()->where('name', $data['device_name'])->delete();
        $token = $user->createToken($data['device_name'], ['*'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'must_change_password' => $user->must_change_password,
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
            'must_change_password' => $request->user()->must_change_password,
            'karyawan' => $this->profil($request->user()),
        ]);
    }

    public function gantiPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:password_lama', Password::min(8)->letters()->numbers()],
        ], [
            'password_lama.current_password' => 'Password lama tidak sesuai.',
        ]);

        $user = $request->user();
        $user->password = $request->password;
        $user->forceFill(['must_change_password' => false])->save();

        // Perangkat lain harus login ulang; perangkat yang sedang dipakai tetap masuk
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password berhasil diganti.']);
    }

    private function profil(User $user): array
    {
        $k = $user->karyawan->loadMissing('jabatan');

        return [
            'nama' => $k->nama,
            'nip' => $k->nip,
            'jabatan' => $k->jabatan->nama,
            'email' => $user->email,
        ];
    }
}
