<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\KaryawanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('karyawan')
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $kata)->orWhere('email', 'like', $kata));
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', $request->role);
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_active', $request->status === '1');
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $passwordSementara = ! empty($data['password'])
            ? $data['password']
            : KaryawanService::passwordSementara();

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $passwordSementara,
        ]);

        $user->forceFill([
            'role' => Role::from($data['role']),
            'is_active' => true,
            'must_change_password' => true,
        ])->save();

        ActivityLog::catat(
            $request->user(),
            'buat_pengguna',
            "Menambahkan pengguna baru: {$user->name} ({$user->role->label()})"
        );

        return redirect()->route('admin.users.index')
            ->with('success', "Akun {$user->name} berhasil dibuat.")
            ->with('password_sementara', $passwordSementara);
    }

    public function edit(User $user): View
    {
        $user->load('karyawan');

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $newRole = Role::from($data['role']);

        // Cegah admin mengubah peran dirinya sendiri
        if ($request->user()->id === $user->id && $newRole !== Role::Admin) {
            return back()->withInput()->with('error', 'Anda tidak dapat mengubah peran akun Administrator Anda sendiri.');
        }

        // Cegah mengubah role pengguna yang terikat profil karyawan
        if ($user->karyawan()->exists() && $newRole !== Role::Karyawan) {
            return back()->withInput()->with('error', 'Pengguna ini memiliki profil karyawan aktif. Peran tidak dapat diubah.');
        }

        // Cegah mengubah peran jika akun ini adalah Admin aktif terakhir
        if ($user->isAdmin() && $newRole !== Role::Admin) {
            $adminLainAktif = User::where('role', Role::Admin)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($adminLainAktif < 1) {
                return back()->withInput()->with('error', 'Tidak dapat mengubah peran Administrator terakhir yang aktif.');
            }
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $user->forceFill(['role' => $newRole])->save();

        if ($user->karyawan) {
            $user->karyawan->update(['nama' => $data['name']]);
        }

        ActivityLog::catat(
            $request->user(),
            'ubah_pengguna',
            "Memperbarui data akun {$user->name} (Peran: {$user->role->label()})"
        );

        return redirect()->route('admin.users.index')->with('success', "Data akun {$user->name} berhasil diperbarui.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // Cegah admin menonaktifkan akun sendiri
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        // Cegah menonaktifkan Admin aktif terakhir
        if ($user->isAdmin() && $user->is_active) {
            $adminAktif = User::where('role', Role::Admin)->where('is_active', true)->count();
            if ($adminAktif <= 1) {
                return back()->with('error', 'Tidak dapat menonaktifkan akun Administrator terakhir yang aktif.');
            }
        }

        $statusBaru = ! $user->is_active;

        if ($user->karyawan) {
            app(KaryawanService::class)->setAktif($user->karyawan, $statusBaru);
        } else {
            $user->forceFill(['is_active' => $statusBaru])->save();
            if (! $statusBaru) {
                $user->tokens()->delete();
            }
        }

        $aksiTeks = $statusBaru ? 'mengaktifkan' : 'menonaktifkan';
        ActivityLog::catat(
            $request->user(),
            $statusBaru ? 'aktifkan_pengguna' : 'nonaktifkan_pengguna',
            ucfirst($aksiTeks)." akun pengguna {$user->name}"
        );

        return back()->with('success', "Berhasil {$aksiTeks} akun {$user->name}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $passwordSementara = KaryawanService::passwordSementara();

        $user->password = $passwordSementara;
        $user->forceFill(['must_change_password' => true])->save();
        $user->tokens()->delete();

        ActivityLog::catat(
            $request->user(),
            'reset_password',
            "Mereset password pengguna {$user->name}"
        );

        return back()
            ->with('success', "Password untuk {$user->name} berhasil direset.")
            ->with('password_sementara', $passwordSementara);
    }
}
