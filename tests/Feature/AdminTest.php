<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Jabatan;
use App\Models\KalenderKerja;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanData;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_admin_dapat_login_dan_diarahkan_ke_admin_dashboard(): void
    {
        $admin = $this->admin(['name' => 'Admin', 'password' => 'RahasiaAdmin1']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'RahasiaAdmin1'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Utama Administrator')
            ->assertSee('Admin')
            ->assertSee('Lowongan dibuka')
            ->assertSee('Karyawan aktif');
    }

    public function test_hrd_dapat_login_dan_diarahkan_ke_hrd_dashboard(): void
    {
        $hrd = $this->hrd();

        $this->actingAs($hrd)->get('/dashboard')->assertRedirect(route('hrd.dashboard'));
        $response = $this->actingAs($hrd)->get(route('hrd.dashboard'))->assertOk();

        $response->assertSee('Dashboard HRD');
        $response->assertSee('Lowongan dibuka');
        $response->assertSee('Karyawan terdaftar');
        // Pastikan HRD dashboard tidak memiliki kartu monitoring server atau pengguna
        $response->assertDontSee('Total Pengguna');
        $response->assertDontSee('Monitoring Sistem');
    }

    public function test_admin_memiliki_akses_penuh_termasuk_karyawan_crud_lowongan_lamaran_dan_absensi(): void
    {
        $admin = $this->admin();
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Staff IT'], ['aktif' => true]);
        $karyawan = $this->karyawan();

        // 1. Admin bisa akses Lowongan & Lamaran di area admin
        $this->actingAs($admin)->get(route('admin.lowongan.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.lamaran.index'))->assertOk();

        // 2. Admin bisa akses Karyawan index & detail
        $this->actingAs($admin)->get(route('admin.karyawan.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.karyawan.show', $karyawan))->assertOk();

        // 3. Admin bisa membuka form tambah dan edit karyawan
        $this->actingAs($admin)->get(route('admin.karyawan.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.karyawan.edit', $karyawan))->assertOk();

        // 4. Admin bisa menambah karyawan
        $this->actingAs($admin)->post(route('admin.karyawan.store'), [
            'nama' => 'Karyawan Baru Admin',
            'email' => 'karyawan.admin@example.com',
            'nik' => '3215090909990001',
            'no_hp' => '081299990001',
            'jabatan_id' => $jabatan->id,
            'tanggal_masuk' => '2026-11-01',
        ])->assertSessionHas('password_sementara');

        // 5. Admin bisa akses Absensi, Kalender, Jabatan
        $this->actingAs($admin)->get(route('admin.absensi.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.absensi.rekap'))->assertOk();
        $this->actingAs($admin)->get(route('admin.jabatan.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.kalender.index'))->assertOk();
    }

    public function test_hrd_hanya_memiliki_akses_view_only_pada_karyawan(): void
    {
        $hrd = $this->hrd();
        $karyawan = $this->karyawan();
        $jabatan = Jabatan::firstOrCreate(['nama' => 'Staff IT'], ['aktif' => true]);

        // HRD bisa melihat daftar dan detail
        $this->actingAs($hrd)->get(route('hrd.karyawan.index'))->assertOk()->assertDontSee('+ Tambah Karyawan');
        $this->actingAs($hrd)->get(route('hrd.karyawan.show', $karyawan))->assertOk()->assertDontSee('Reset Password');

        // HRD ditolak backend (403) saat mencoba Tambah Karyawan
        $this->actingAs($hrd)->get(route('hrd.karyawan.create'))->assertForbidden();
        $this->actingAs($hrd)->post(route('hrd.karyawan.store'), [
            'nama' => 'Hacker HRD',
            'email' => 'hack@example.com',
            'nik' => '3215090909990099',
            'no_hp' => '081299990099',
            'jabatan_id' => $jabatan->id,
            'tanggal_masuk' => '2026-11-01',
        ])->assertForbidden();

        // HRD ditolak backend (403) saat mencoba Edit Karyawan
        $this->actingAs($hrd)->get(route('hrd.karyawan.edit', $karyawan))->assertForbidden();
        $this->actingAs($hrd)->put(route('hrd.karyawan.update', $karyawan), [
            'nama' => 'Nama Diubah HRD',
            'no_hp' => '081299990001',
            'jabatan_id' => $jabatan->id,
        ])->assertForbidden();

        // HRD ditolak backend (403) saat mencoba Reset Password Karyawan
        $this->actingAs($hrd)->post(route('hrd.karyawan.reset-password', $karyawan))->assertForbidden();
    }

    public function test_hrd_dan_karyawan_tidak_bisa_mengakses_area_admin(): void
    {
        $hrd = $this->hrd();
        $karyawan = $this->karyawan();

        // HRD mengakses admin dashboard dan manajemen sistem
        $this->actingAs($hrd)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($hrd)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($hrd)->get(route('admin.monitoring'))->assertForbidden();
        $this->actingAs($hrd)->get(route('admin.pengaturan'))->assertForbidden();

        // Karyawan mengakses admin dashboard
        $this->actingAs($karyawan->user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($karyawan->user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($karyawan->user)->get(route('hrd.dashboard'))->assertForbidden();

        // Tamu (guest) diarahkan ke login
        $this->post('/logout');
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_bisa_melihat_dan_memfilter_daftar_pengguna(): void
    {
        $admin = $this->admin(['name' => 'Super Admin', 'email' => 'super@portal-pt.test']);
        $hrd = $this->hrd();
        $karyawan = $this->karyawan(['nama' => 'Bambang Sudarsono']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Super Admin')
            ->assertSee($hrd->name)
            ->assertSee($karyawan->nama);

        // Filter role HRD
        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'hrd']))
            ->assertOk()
            ->assertSee($hrd->name)
            ->assertDontSee($karyawan->nama);

        // Pencarian nama
        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'Bambang']))
            ->assertOk()
            ->assertSee('Bambang Sudarsono')
            ->assertDontSee('super@portal-pt.test');
    }

    public function test_admin_bisa_menambah_pengguna_baru_dan_mencatat_log_aktivitas(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'HRD Baru',
            'email' => 'hrdbaru@portal-pt.test',
            'role' => 'hrd',
        ])->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('password_sementara');

        $newUser = User::where('email', 'hrdbaru@portal-pt.test')->firstOrFail();
        $this->assertSame(Role::Hrd, $newUser->role);
        $this->assertTrue($newUser->is_active);
        $this->assertTrue($newUser->must_change_password);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'buat_pengguna',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_bisa_mengubah_data_dan_peran_pengguna(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'name' => 'Staff Baru',
            'email' => 'staff@portal-pt.test',
            'role' => Role::Hrd,
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Staff HRD Senior',
            'email' => 'senior.hrd@portal-pt.test',
            'role' => 'hrd',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('Staff HRD Senior', $user->name);
        $this->assertSame('senior.hrd@portal-pt.test', $user->email);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ubah_pengguna',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_tidak_bisa_mengubah_peran_akun_sendiri(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'hrd',
        ])->assertSessionHas('error');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_admin_tidak_bisa_mengubah_peran_admin_terakhir_yang_aktif(): void
    {
        $admin1 = $this->admin();
        $admin2 = $this->admin();

        $this->actingAs($admin1)->put(route('admin.users.update', $admin2), [
            'name' => $admin2->name,
            'email' => $admin2->email,
            'role' => 'hrd',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(Role::Hrd, $admin2->fresh()->role);

        // Sekarang hanya tersisa admin1. Jika ada yang mencoba mengubah admin1 -> ditolak
        $this->actingAs($admin1)->put(route('admin.users.update', $admin1), [
            'name' => $admin1->name,
            'email' => $admin1->email,
            'role' => 'karyawan',
        ])->assertSessionHas('error');

        $this->assertSame(Role::Admin, $admin1->fresh()->role);
    }

    public function test_admin_tidak_bisa_mengubah_peran_pengguna_yang_memiliki_data_karyawan(): void
    {
        $admin = $this->admin();
        $karyawan = $this->karyawan();

        $this->actingAs($admin)->put(route('admin.users.update', $karyawan->user), [
            'name' => $karyawan->user->name,
            'email' => $karyawan->user->email,
            'role' => 'hrd',
        ])->assertSessionHas('error');

        $this->assertSame(Role::Karyawan, $karyawan->user->fresh()->role);
    }

    public function test_admin_bisa_mengaktifkan_dan_menonaktifkan_pengguna(): void
    {
        $admin = $this->admin();
        $hrd = $this->hrd();
        $hrd->createToken('token-test');

        $this->actingAs($admin)->post(route('admin.users.toggle-status', $hrd))
            ->assertRedirect();

        $this->assertFalse($hrd->fresh()->is_active);
        $this->assertSame(0, $hrd->tokens()->count());

        $this->actingAs($admin)->post(route('admin.users.toggle-status', $hrd))
            ->assertRedirect();

        $this->assertTrue($hrd->fresh()->is_active);
    }

    public function test_admin_tidak_bisa_menonaktifkan_akun_sendiri_atau_admin_aktif_terakhir(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.toggle-status', $admin))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_bisa_mereset_password_pengguna(): void
    {
        $admin = $this->admin();
        $hrd = $this->hrd();
        $hrd->createToken('token-hrd');

        $this->actingAs($admin)->post(route('admin.users.reset-password', $hrd))
            ->assertSessionHas('password_sementara');

        $user = $hrd->fresh();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(\Hash::check(session('password_sementara'), $user->password));
    }

    public function test_admin_bisa_mengelola_master_jabatan_dan_hari_libur(): void
    {
        $admin = $this->admin();

        // Jabatan
        $this->actingAs($admin)->post(route('admin.jabatan.store'), [
            'nama' => 'Staff Keuangan',
            'deskripsi' => 'Pengelola kas',
            'aktif' => '1',
        ])->assertRedirect(route('admin.jabatan.index'));

        $this->assertDatabaseHas('jabatan', ['nama' => 'Staff Keuangan']);

        // Hari libur
        $this->actingAs($admin)->post(route('admin.kalender.store'), [
            'tanggal' => '2026-12-25',
            'nama' => 'Hari Natal',
        ])->assertRedirect();

        $this->assertDatabaseHas('kalender_kerja', ['tanggal' => '2026-12-25 00:00:00']);
    }

    public function test_menu_monitoring_dinonaktifkan_dan_admin_bisa_mengubah_pengaturan_sistem(): void
    {
        $admin = $this->admin();

        // Menu Monitoring Sistem dinonaktifkan aman dan dialihkan ke dashboard
        $this->actingAs($admin)->get(route('admin.monitoring'))
            ->assertRedirect(route('admin.dashboard'));

        // Dashboard tidak menampilkan panel aktivitas administratif riil atau tombol monitoring
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Monitoring Sistem')
            ->assertDontSee('Aktivitas Administratif Riil');

        $this->actingAs($admin)->post(route('admin.pengaturan.update'), [
            'nama_perusahaan' => 'PT MTU Indonesia',
            'email_kontak' => 'info@mtu.test',
            'telepon' => '021-998877',
            'alamat_kantor' => 'Jakarta Timur',
            'toleransi_terlambat_menit' => 20,
            'jam_masuk_mulai' => '07:30',
            'jam_masuk_selesai' => '08:45',
            'jam_pulang_mulai' => '17:15',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('PT MTU Indonesia', Pengaturan::ambil('nama_perusahaan'));
        $this->assertSame('20', Pengaturan::ambil('toleransi_terlambat_menit'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ubah_pengaturan',
            'user_id' => $admin->id,
        ]);
    }
}
