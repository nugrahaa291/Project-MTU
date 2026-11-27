<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelamar', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('lamaran', function (Blueprint $table) {
            $table->foreignId('pelamar_id')->nullable()->constrained('pelamar')->restrictOnDelete();
            $table->unique(['pelamar_id', 'lowongan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lamaran', function (Blueprint $table) {
            $table->dropForeign(['pelamar_id']);
            $table->dropUnique(['pelamar_id', 'lowongan_id']);
            $table->dropColumn('pelamar_id');
        });
        Schema::dropIfExists('pelamar');
    }
};
