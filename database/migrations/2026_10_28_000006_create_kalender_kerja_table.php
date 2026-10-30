<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya menyimpan hari LIBUR tambahan (libur nasional/cuti bersama).
        // Akhir pekan diatur lewat config/absensi.php (hari_kerja).
        Schema::create('kalender_kerja', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('nama');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_kerja');
    }
};
