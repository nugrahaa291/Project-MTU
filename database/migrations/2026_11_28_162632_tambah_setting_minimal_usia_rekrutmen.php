<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengaturan')->insert([
            'kunci' => 'rekrutmen_minimal_usia',
            'nilai' => '20',
            'kategori' => 'rekrutmen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pengaturan')->where('kunci', 'rekrutmen_minimal_usia')->delete();
    }
};
