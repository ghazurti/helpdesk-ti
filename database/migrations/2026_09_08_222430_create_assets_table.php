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
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->foreignId('asset_category_id')->constrained()->cascadeOnDelete();
            $t->string('merk')->nullable();
            $t->string('model')->nullable();
            $t->string('serial_number')->nullable();
            $t->date('tanggal_beli')->nullable();
            $t->decimal('harga', 15, 2)->nullable();
            $t->date('garansi_sampai')->nullable();
            $t->enum('kondisi', ['baik','rusak','perbaikan','dihapus'])->default('baik');
            $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('masa_manfaat_bulan')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
