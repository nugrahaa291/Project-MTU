<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lowongan', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            // Jabatan yang akan disandang pelamar bila diterima (dipakai saat otomatis jadi karyawan)
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete();
            $table->string('lokasi');
            $table->unsignedSmallInteger('kuota')->default(1);
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->string('status', 20)->default('dibuka')->index(); // dibuka | ditutup
            $table->text('deskripsi')->nullable();
            $table->text('persyaratan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lowongan');
    }
};
