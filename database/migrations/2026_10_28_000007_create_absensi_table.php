<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->date('tanggal');

            // hadir | terlambat | izin | sakit | cuti | alpa
            $table->string('status', 20);
            $table->unsignedSmallInteger('menit_terlambat')->default(0);

            // Absen masuk
            $table->time('jam_masuk')->nullable();
            $table->decimal('lat_masuk', 10, 7)->nullable();
            $table->decimal('lng_masuk', 10, 7)->nullable();
            $table->unsignedInteger('jarak_masuk')->nullable(); // meter dari kantor
            $table->string('foto_masuk')->nullable();

            // Absen pulang
            $table->time('jam_pulang')->nullable();
            $table->decimal('lat_pulang', 10, 7)->nullable();
            $table->decimal('lng_pulang', 10, 7)->nullable();
            $table->unsignedInteger('jarak_pulang')->nullable();
            $table->string('foto_pulang')->nullable();

            $table->text('keterangan')->nullable();
            // Diisi bila HRD yang mencatat (izin/sakit/cuti/alpa manual)
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu karyawan = satu catatan per hari. Ini yang mencegah absen ganda.
            $table->unique(['karyawan_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
