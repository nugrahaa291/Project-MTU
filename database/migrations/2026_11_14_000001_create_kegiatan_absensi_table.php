<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur absensi baru (Standby / Order / Izin) disimpan di tabel BARU.
     * Tabel `absensi` lama (masuk/pulang) tidak diubah sama sekali, sehingga seluruh riwayat lama tetap utuh.
     */
    public function up(): void
    {
        Schema::create('kegiatan_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();

            // Kunci unik dari aplikasi untuk SATU kali penekanan tombol Kirim. Kirim ulang (klik ganda,
            // sinyal putus lalu coba lagi) membawa kunci yang sama sehingga tidak membuat catatan ganda.
            $table->string('kunci', 64);

            $table->string('jenis', 20);                  // standby | order | izin
            $table->string('kategori_izin', 20)->nullable(); // libur | sakit | lainnya
            $table->date('tanggal');                      // tanggal WIB menurut jam SERVER
            $table->dateTime('dicatat_pada');             // waktu resmi = jam SERVER
            $table->string('status', 20)->default('tercatat'); // tercatat | menunggu | disetujui | ditolak

            // Khusus Order: tujuan dan lokasi aktual HP disimpan sebagai DUA data terpisah
            $table->string('tujuan', 255)->nullable();
            $table->decimal('lat_aktual', 10, 7)->nullable();
            $table->decimal('lng_aktual', 10, 7)->nullable();
            $table->unsignedInteger('akurasi_meter')->nullable();
            $table->string('nama_lokasi', 255)->nullable();      // hasil geocoding bila tersedia
            $table->unsignedInteger('jarak_kantor_meter')->nullable(); // informasi saja, bukan syarat
            $table->string('foto')->nullable();                   // selfie ber-watermark (disk privat)

            // Khusus Izin
            $table->text('alasan')->nullable();
            $table->string('lampiran')->nullable();               // SKD (disk privat)
            $table->foreignId('pengganti_id')->nullable()->constrained('karyawan')->nullOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diputuskan_pada')->nullable();
            $table->text('alasan_penolakan')->nullable();

            $table->timestamps();

            $table->unique(['karyawan_id', 'kunci']);
            $table->index(['karyawan_id', 'tanggal']);
            $table->index('tanggal');
            $table->index(['jenis', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_absensi');
    }
};
