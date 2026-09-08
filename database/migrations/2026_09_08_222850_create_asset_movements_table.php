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
        Schema::create('asset_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $t->foreignId('to_unit_id')->constrained('units')->cascadeOnDelete();
            $t->date('tanggal');
            $t->string('keterangan')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
