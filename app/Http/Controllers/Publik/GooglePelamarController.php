<?php

namespace App\Http\Controllers\Publik;

use App\Models\Pelamar;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GooglePelamarController extends AkunPelamarController
{
    public function mulai(Request $request): RedirectResponse
    {
        $config = config('services.google');
        if (! $config['client_id'] || ! $config['client_secret'] || ! $config['redirect']) {
            return back()->with('error', 'Login Google belum tersedia. Silakan gunakan email dan password.');
        }
        $state = Str::random(64);
        $verifier = Str::random(80);
        $request->session()->put('pelamar.google', [
            'state' => $state, 'verifier' => $verifier, 'expires' => now()->addMinutes(10)->timestamp,
            'account' => $request->user('pelamar')?->id,
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $config['client_id'], 'redirect_uri' => $config['redirect'],
            'scope' => 'openid email profile', 'response_type' => 'code', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('pelamar.google');
        $current = $request->user('pelamar');
        $error = fn (string $message) => redirect()->route($current ? 'pelamar.riwayat' : 'pelamar.login')->with('error', $message);
        if (! $flow || $flow['expires'] < now()->timestamp || $flow['account'] !== $current?->id
            || ! hash_equals($flow['state'], (string) $request->string('state')) || ! $request->filled('code')) {
            return $error('Login Google dibatalkan atau sesi kedaluwarsa. Silakan coba lagi.');
        }
        $config = config('services.google');
        try {
            $token = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect'], 'grant_type' => 'authorization_code',
                'code' => $request->string('code')->toString(), 'code_verifier' => $flow['verifier'],
            ]);
            if (! $token->successful() || ! $token->json('access_token')) {
                return $error('Google gagal mengonfirmasi login. Silakan coba lagi.');
            }
            $info = Http::withToken($token->json('access_token'))->timeout(10)->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (ConnectionException) {
            return $error('Google belum bisa dihubungi. Silakan coba lagi.');
        }
        $email = Str::lower((string) $info->json('email'));
        $sub = $info->json('sub');
        if (! $info->successful() || $info->json('email_verified') !== true || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($sub) || $sub === '') {
            return $error('Google belum mengonfirmasi identitas dan email akun ini.');
        }
        try {
            if ($current) {
                if ($current->email !== $email || ($current->google_id && $current->google_id !== $sub)) {
                    return $error('Pilih akun Google dengan email yang sesuai dengan akun pelamar Anda.');
                }
                $current->forceFill(['google_id' => $sub])->save();

                return redirect()->route('pelamar.riwayat')->with('success', 'Akun Google berhasil ditautkan.');
            }
            $pelamar = Pelamar::where('google_id', $sub)->first();
            if (! $pelamar) {
                if (Pelamar::where('email', $email)->exists()) {
                    return $error('Masuk dengan password Anda, lalu pilih Hubungkan Google di Lamaran Saya.');
                }
                $pelamar = new Pelamar(['nama' => Str::limit($info->json('name') ?: $email, 100, ''), 'email' => $email]);
                $pelamar->forceFill(['google_id' => $sub])->save();
            }
        } catch (UniqueConstraintViolationException) {
            return $error('Akun sudah terdaftar. Silakan masuk kembali.');
        }
        Auth::guard('pelamar')->login($pelamar);

        return $this->selesai($request);
    }
}
