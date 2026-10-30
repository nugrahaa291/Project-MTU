<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Asal lamaran (null bila karyawan ditambahkan manual). unique = satu lamaran hanya jadi satu karyawan
            $table->foreignId('lamaran_id')->nullable()->unique()->constrained('lamaran')->nullOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete();

            $table->string('nip', 20)->nullable()->unique();
            $table->string('nik', 16)->unique();
            $table->string('nama');
            $table->string('no_hp', 15);
            $table->text('alamat')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
