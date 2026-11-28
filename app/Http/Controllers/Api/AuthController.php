<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\LoginKaryawan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, LoginKaryawan $login): JsonResponse
    {
        // Aplikasi lama mengirim PIN pada kolom pin.
        $request->mergeIfMissing(['password' => $request->input('pin')]);
        $data = $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $user = $login->verifikasi($data['nip'], $data['password'], $request->ip());
            [$user, $token] = DB::transaction(function () use ($user, $data) {
                $akun = User::lockForUpdate()->findOrFail($user->id);
                if (! hash_equals($akun->password, $user->password) || ! $akun->is_active || ! $akun->isKaryawan() || ! $akun->karyawan?->aktif) {
                    throw ValidationException::withMessages(['nip' => 'Akun berubah. Silakan masuk kembali.'])->status(401);
                }
                $akun->tokens()->where('name', $data['device_name'])->delete();
                $token = $akun->createToken($data['device_name'], ['*'], now()->addDays(30));

                return [$akun, $token];
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'kode' => $e->status === 429 ? 'terlalu_banyak_percobaan' : 'login_gagal',
            ], $e->status);
        }

        ActivityLog::catat($user, 'login_api', 'Login via API mobile: '.$data['device_name']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'must_change_password' => $user->must_change_password,
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
            'must_change_password' => $request->user()->must_change_password,
            'must_change_pin' => $request->user()->must_change_password,
            'karyawan' => $this->profil($request->user()),
        ]);
    }

    public function gantiPassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['bail', 'required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Password maksimal 72 byte dan harus berupa teks.');
                    }
                }],
        ]);

        $sesi = $request->user();
        DB::transaction(function () use ($request, $sesi) {
            $user = User::lockForUpdate()->findOrFail($sesi->id);
            if (! hash_equals($user->password, $sesi->password)) {
                throw ValidationException::withMessages(['current_password' => 'Password sudah berubah. Silakan masuk kembali.']);
            }
            $user->password = $request->password;
            $user->forceFill(['must_change_password' => false])->save();
            $user->tokens()->where('id', '!=', $sesi->currentAccessToken()->id)->delete();
            $sesi->setRawAttributes($user->getAttributes(), true);
        });

        return response()->json(['message' => 'Password berhasil diganti.']);
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
}
