<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan kolom proyek ke tabel karyawan bila belum ada
        if (Schema::hasTable('karyawan') && ! Schema::hasColumn('karyawan', 'proyek')) {
            Schema::table('karyawan', function (Blueprint $table) {
                $table->string('proyek', 50)->nullable()->default('Operasional MTU')->after('jabatan_id');
            });
        }

        // 2. Tabel konfigurasi kebutuhan minimum driver operasional per proyek
        if (! Schema::hasTable('proyek_kebutuhan_driver')) {
            Schema::create('proyek_kebutuhan_driver', function (Blueprint $table) {
                $table->id();
                $table->string('nama_proyek', 50)->unique();
                $table->unsignedInteger('min_driver_aktif')->default(2);
                $table->string('keterangan')->nullable();
                $table->timestamps();
            });
        }

        // 3. Tabel riwayat batch import jadwal dari kantor pusat
        if (! Schema::hasTable('jadwal_import_batch')) {
            Schema::create('jadwal_import_batch', function (Blueprint $table) {
                $table->id();
                $table->string('nomor_batch', 40)->unique();
                $table->string('nama_file');
                $table->string('periode_bulan', 7); // Format YYYY-MM
                $table->unsignedSmallInteger('periode_tahun');
                $table->string('format_import', 30)->default('matriks'); // matriks | tabel_standar
                $table->unsignedInteger('total_baris')->default(0);
                $table->unsignedInteger('total_driver')->default(0);
                $table->unsignedInteger('total_kerja')->default(0);
                $table->unsignedInteger('total_libur')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 4. Tabel utama jadwal kerja dan libur driver cabang MTU
        if (! Schema::hasTable('jadwal_driver')) {
            Schema::create('jadwal_driver', function (Blueprint $table) {
                $table->id();
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->date('tanggal');
                $table->enum('status_jadwal', ['libur', 'kerja'])->default('kerja');
                $table->enum('sumber', ['import_pusat', 'manual_cabang'])->default('import_pusat');
                $table->foreignId('batch_id')->nullable()->constrained('jadwal_import_batch')->nullOnDelete();
                $table->string('proyek', 50)->nullable()->default('Operasional MTU');
                $table->foreignId('driver_pengganti_id')->nullable()->constrained('karyawan')->nullOnDelete();
                $table->enum('status_verifikasi', ['disetujui', 'konflik', 'perlu_verifikasi'])->default('disetujui');
                $table->text('alasan_pengecualian')->nullable();
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                // Satu driver hanya punya 1 status jadwal aktif pada tanggal yang sama
                $table->unique(['karyawan_id', 'tanggal'], 'uniq_karyawan_tanggal_jadwal');
                $table->index(['tanggal', 'status_jadwal'], 'idx_tanggal_status');
                $table->index(['proyek', 'tanggal'], 'idx_proyek_tanggal');
            });
        }

        // 5. Tabel riwayat audit perubahan jadwal operasional
        if (! Schema::hasTable('jadwal_riwayat_perubahan')) {
            Schema::create('jadwal_riwayat_perubahan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jadwal_id')->constrained('jadwal_driver')->cascadeOnDelete();
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->date('tanggal');
                $table->string('status_sebelumnya', 20)->nullable();
                $table->string('status_baru', 20);
                $table->foreignId('driver_pengganti_sebelumnya')->nullable()->constrained('karyawan')->nullOnDelete();
                $table->foreignId('driver_pengganti_baru')->nullable()->constrained('karyawan')->nullOnDelete();
                $table->foreignId('diubah_oleh')->constrained('users')->cascadeOnDelete();
                $table->text('alasan');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_riwayat_perubahan');
        Schema::dropIfExists('jadwal_driver');
        Schema::dropIfExists('jadwal_import_batch');
        Schema::dropIfExists('proyek_kebutuhan_driver');

        if (Schema::hasTable('karyawan') && Schema::hasColumn('karyawan', 'proyek')) {
            Schema::table('karyawan', function (Blueprint $table) {
                $table->dropColumn('proyek');
            });
        }
    }
};
