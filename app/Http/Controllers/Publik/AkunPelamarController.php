<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Lamaran;
use App\Models\Pelamar;
use App\Services\LoginKaryawan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AkunPelamarController extends Controller
{
    public function form(Request $request): View|RedirectResponse
    {
        if ($request->user('pelamar')) {
            return redirect()->route('pelamar.riwayat');
        }

        return view('pelamar.masuk', ['daftar' => $request->routeIs('pelamar.daftar')]);
    }

    public function daftar(Request $request): RedirectResponse
    {
        abort_if($request->user('pelamar'), 403);
        $request->merge(['email' => Str::lower(trim($request->string('email')))]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:pelamar,email'],
            'password' => ['required', 'confirmed', Password::min(8), 'max:72'],
        ]);
        try {
            $pelamar = Pelamar::create($data);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['email' => 'Email sudah digunakan.']);
        }
        Auth::guard('pelamar')->login($pelamar);
        
        // Auto-link lamaran lama berdasarkan email
        $this->autoLinkLamaran($pelamar);

        return $this->selesai($request);
    }

    public function masuk(Request $request, LoginKaryawan $login): RedirectResponse
    {
        abort_if($request->user('pelamar'), 403);
        
        $data = $request->validate([
            'identifier' => 'required|string|max:150',
            'password' => 'required|string'
        ]);
        
        $identifier = trim($data['identifier']);
        
        // Cek apakah input berupa email atau NIP
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            // Login dengan email (pelamar atau karyawan yang punya profil pelamar)
            $email = Str::lower($identifier);
            $profil = Pelamar::where('email', $email)->first();
            
            if ($profil?->user_id) {
                // Pelamar yang terhubung dengan karyawan
                $nip = $profil->user?->karyawan?->nip;
                if (! $nip) {
                    return back()->withErrors(['identifier' => 'Akun karyawan tidak aktif.']);
                }
                try {
                    $user = $login->verifikasi($nip, $data['password'], $request->ip());
                } catch (ValidationException $e) {
                    return back()->withErrors(['identifier' => $e->errors()['nip'][0]])->onlyInput('identifier');
                }
                if ($user->id !== $profil->user_id || $user->must_change_password) {
                    return back()->withErrors(['identifier' => 'Perbarui password melalui halaman Absen.']);
                }
                Auth::guard('pelamar')->login($profil->setRelation('user', $user));
            } elseif (! Auth::guard('pelamar')->attempt(['email' => $email, 'password' => $data['password'], 'user_id' => null])) {
                return back()->withErrors(['identifier' => 'Email atau password tidak cocok.'])->onlyInput('identifier');
            }
        } else {
            // Login dengan NIP (karyawan)
            try {
                $user = $login->verifikasi($identifier, $data['password'], $request->ip());
            } catch (ValidationException $e) {
                return back()->withErrors(['identifier' => 'NIP atau password tidak cocok.'])->onlyInput('identifier');
            }
            
            if ($user->must_change_password) {
                return back()->withErrors(['identifier' => 'Masuk ke halaman Absen dengan NIP dan password saat ini, lalu buat password baru.']);
            }
            
            try {
                $pelamar = $login->profilPelamar($user, null);
            } catch (UniqueConstraintViolationException) {
                return back()->withErrors(['identifier' => 'Profil sudah terdaftar. Silakan masuk kembali.']);
            }
            
            Auth::guard('pelamar')->login($pelamar);
        }

        return $this->selesai($request);
    }

    public function hubungkanKaryawan(Request $request, LoginKaryawan $login): RedirectResponse
    {
        return $this->gunakanAkunKaryawan($request, $login, $request->user('pelamar'));
    }

    private function gunakanAkunKaryawan(Request $request, LoginKaryawan $login, ?Pelamar $pemilik): RedirectResponse
    {
        $data = $request->validate(['nip' => 'required|string|max:30', 'password' => 'required|string|max:255']);
        $user = $login->verifikasi($data['nip'], $data['password'], $request->ip());
        if ($user->must_change_password) {
            return back()->withErrors(['nip' => 'Masuk ke halaman Absen dengan NIP dan password saat ini, lalu buat password baru.']);
        }
        try {
            $pelamar = $login->profilPelamar($user, $pemilik);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['nip' => 'Profil sudah terdaftar. Silakan masuk kembali.']);
        }
        Auth::guard('pelamar')->login($pelamar);
        if ($pemilik) {
            $request->session()->flash('success', 'Akun terhubung. Gunakan password karyawan untuk kedua portal.');
        }

        return $this->selesai($request);
    }

    protected function selesai(Request $request): RedirectResponse
    {
        $pelamar = $request->user('pelamar');
        if ($pelamar->user_id) {
            $sidik = $pelamar->sidikPasswordKaryawan();
            if (! $sidik) {
                Auth::guard('pelamar')->logout();

                return redirect()->route('pelamar.login')->with('error', 'Periksa status dan password akun karyawan melalui halaman Absen.');
            }
            $request->session()->put('pelamar.sidik', $sidik);
        }
        $request->session()->regenerate();
        
        // Auto-link lamaran lama berdasarkan email (untuk login pertama kali)
        $this->autoLinkLamaran($pelamar);
        
        // Redirect ke halaman lowongan, bukan langsung ke form lamaran
        return redirect()->route('lowongan.index');
    }

    /**
     * Auto-link lamaran lama yang belum terhubung berdasarkan email
     */
    protected function autoLinkLamaran(Pelamar $pelamar): void
    {
        try {
            Lamaran::whereNull('pelamar_id')
                ->where('email', $pelamar->email)
                ->update(['pelamar_id' => $pelamar->id]);
        } catch (\Exception $e) {
            // Silent fail - jangan ganggu proses login/daftar
            // Log bisa ditambahkan di sini kalau perlu
        }
    }

    public function riwayat(Request $request): View
    {
        return view('pelamar.riwayat', [
            'pelamar' => $request->user('pelamar'),
            'daftar' => $request->user('pelamar')->lamaran()->with('lowongan')->latest()->paginate(12),
        ]);
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::guard('pelamar')->logout();
        $request->session()->forget('pelamar');
        $request->session()->regenerate(true);

        return redirect()->route('beranda');
    }
}
