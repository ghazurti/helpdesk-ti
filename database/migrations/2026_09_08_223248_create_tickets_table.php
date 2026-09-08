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
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('nomor')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();       // petugas IT
            $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('work_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->string('judul');
            $t->text('deskripsi');
            $t->text('tindakan')->nullable();
            $t->enum('prioritas', ['rendah','sedang','tinggi'])->default('sedang');
            $t->enum('status', ['baru','proses','pending','selesai'])->default('baru');
            $t->date('tanggal');
            $t->timestamp('selesai_at')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
