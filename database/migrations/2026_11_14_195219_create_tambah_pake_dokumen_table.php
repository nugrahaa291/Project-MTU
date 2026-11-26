<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $kolomBaru = ['sim', 'kk', 'skck', 'domisili', 'paklaring', 'akte_lahir', 'buku_nikah', 'surat_lamaran'];

    public function up(): void
    {
        Schema::table('lowongan', function (Blueprint $table) {
            $table->string('paket_dokumen', 20)->default('umum');
        });

        Schema::table('lamaran', function (Blueprint $table) {
            $table->string('cv')->nullable()->change(); // lamaran driver tidak memakai CV
            foreach ($this->kolomBaru as $kolom) {
                $table->string($kolom)->nullable();
            }
        });

        // Lowongan driver yang sudah ada: tebak paketnya dari judul
        DB::table('lowongan')->where('judul', 'like', '%trailer%')->update(['paket_dokumen' => 'trailer']);
        DB::table('lowongan')->where('judul', 'like', '%wingbox%')->update(['paket_dokumen' => 'wingbox']);
        DB::table('lowongan')->where(fn ($q) => $q->where('judul', 'like', '%cdd%')->orWhere('judul', 'like', '%fuso%'))
            ->update(['paket_dokumen' => 'cdd_fuso']);
    }

    public function down(): void
    {
        Schema::table('lamaran', function (Blueprint $table) {
            $table->dropColumn($this->kolomBaru);
        });

        Schema::table('lowongan', function (Blueprint $table) {
            $table->dropColumn('paket_dokumen');
        });
    }
};