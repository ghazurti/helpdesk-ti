<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('direspons_at')->nullable()->after('status');
        });

        // Isi tiket lama: pakai log pertama saat status keluar dari "baru";
        // tiket yang sejak dibuat sudah diproses dianggap direspons saat dibuat.
        DB::table('tickets')
            ->where('status', '<>', 'baru')
            ->update(['direspons_at' => DB::raw(
                "COALESCE((SELECT MIN(l.created_at) FROM ticket_logs l WHERE l.ticket_id = tickets.id AND l.status_lama = 'baru'), tickets.created_at)"
            )]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('direspons_at');
        });
    }
};
