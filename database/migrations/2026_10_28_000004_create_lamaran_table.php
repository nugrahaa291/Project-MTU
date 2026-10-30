<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lamaran', function (Blueprint $table) {
            $table->id();
            // Kode publik untuk pelamar mengecek status (tanpa perlu login)
            $table->string('kode', 20)->unique();
            // Lowongan tidak boleh dihapus selama masih punya lamaran
            $table->foreignId('lowongan_id')->constrained('lowongan')->restrictOnDelete();

            // Data pelamar (disimpan per lamaran agar tidak saling menimpa)
            $table->string('nik', 16);
            $table->string('nama');
            $table->string('email');
            $table->string('no_hp', 15);
            $table->string('jenis_kelamin', 1); // L | P
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->text('alamat');
            $table->string('pendidikan', 30);

            // Berkas: disimpan di disk privat, hanya HRD yang bisa mengunduh
            $table->string('cv');
            $table->string('pas_foto');
            $table->string('ktp');
            $table->string('ijazah')->nullable();

            // diajukan -> seleksi -> diterima | ditolak
            $table->string('status', 20)->default('diajukan')->index();
            $table->text('catatan')->nullable(); // catatan internal HRD
            $table->timestamp('diputuskan_pada')->nullable();
            $table->timestamps();

            // Satu NIK hanya boleh melamar satu kali per lowongan
            $table->unique(['lowongan_id', 'nik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lamaran');
    }
};
