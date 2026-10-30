<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'hrd' = pengelola sistem, 'karyawan' = pegawai yang absen lewat aplikasi mobile
            $table->string('role', 20)->default('karyawan')->after('password')->index();
            // Akun nonaktif tidak bisa login (web maupun API)
            $table->boolean('is_active')->default(true)->after('role');
            // true = wajib ganti password sementara sebelum memakai sistem
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']); // indeks harus dilepas dulu sebelum kolomnya dihapus
            $table->dropColumn(['role', 'is_active', 'must_change_password']);
        });
    }
};
