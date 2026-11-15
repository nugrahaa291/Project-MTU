<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\ActivityLog;
use App\Models\Karyawan;
use App\Models\Lamaran;
use App\Models\Lowongan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        // Status database
        $dbStatus = 'Terputus';
        $dbVersi = '-';
        try {
            $pdo = DB::connection()->getPdo();
            $dbStatus = 'Terhubung';
            $dbVersi = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $e) {
            $dbStatus = 'Gagal: '.$e->getMessage();
        }

        // Ruang penyimpanan
        $freeSpace = @disk_free_space(base_path());
        $totalSpace = @disk_total_space(base_path());
        $diskInfo = [
            'free' => $freeSpace ? round($freeSpace / (1024 * 1024 * 1024), 2).' GB' : 'N/A',
            'total' => $totalSpace ? round($totalSpace / (1024 * 1024 * 1024), 2).' GB' : 'N/A',
            'persen_terpakai' => ($freeSpace && $totalSpace) ? round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1) : 0,
        ];

        // Statistik operasional
        $ringkasan = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => config('app.env'),
            'db_status' => $dbStatus,
            'db_version' => $dbVersi,
            'waktu_server' => now()->format('d M Y H:i:s T'),
            'total_pengguna' => User::count(),
            'total_admin' => User::where('role', Role::Admin)->count(),
            'total_hrd' => User::where('role', Role::Hrd)->count(),
            'total_karyawan' => User::where('role', Role::Karyawan)->count(),
            'user_aktif' => User::where('is_active', true)->count(),
            'user_nonaktif' => User::where('is_active', false)->count(),
            'total_absensi' => Absensi::count(),
            'total_lamaran' => Lamaran::count(),
            'total_lowongan' => Lowongan::count(),
            'total_token_aktif' => DB::table('personal_access_tokens')->count(),
            'total_sesi' => DB::table('sessions')->count(),
        ];

        // Log aktivitas riil
        $logs = ActivityLog::with('user')
            ->when($request->filled('aksi'), fn ($q) => $q->where('action', $request->aksi))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monitoring', compact('ringkasan', 'diskInfo', 'logs'));
    }
}
