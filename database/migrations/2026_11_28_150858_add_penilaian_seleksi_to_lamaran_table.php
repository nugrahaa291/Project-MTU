<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lamaran', function (Blueprint $table) {
            $table->json('verifikasi_berkas')->nullable();
            $table->text('alasan_seleksi')->nullable();
            $table->foreignId('diperiksa_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lolos_berkas_pada')->nullable();
            $table->date('tanggal_interview')->nullable();
            $table->string('pewawancara', 150)->nullable();
            $table->text('hasil_interview')->nullable();
            $table->string('rekomendasi_interview', 20)->nullable();
            $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dinilai_pada')->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lamaran', function (Blueprint $table) {
            foreach (['diperiksa_oleh', 'dinilai_oleh', 'diputuskan_oleh'] as $kolom) {
                $table->dropConstrainedForeignId($kolom);
            }
            $table->dropColumn(['verifikasi_berkas', 'alasan_seleksi', 'lolos_berkas_pada',
                'tanggal_interview', 'pewawancara', 'hasil_interview', 'rekomendasi_interview', 'dinilai_pada']);
        });
    }
};
