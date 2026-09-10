<?php

use App\Models\{Ticket, User};
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->user = User::factory()->stafIt()->create();
});

/**
 * Buat tiket lalu paksa created_at/selesai_at ke nilai eksak lewat query
 * builder mentah, supaya tidak ditimpa ulang oleh timestamp otomatis
 * Eloquent saat create()/save().
 */
function tiketDenganWaktu(string $createdAt, ?string $selesaiAt, string $status = 'selesai'): Ticket
{
    $ticket = Ticket::create([
        'user_id' => test()->user->id,
        'judul' => 'Perbaikan printer',
        'deskripsi' => 'Ganti roller',
        'tanggal' => substr($createdAt, 0, 10),
        'status' => 'baru',
    ]);

    DB::table('tickets')->where('id', $ticket->id)->update([
        'created_at' => $createdAt,
        'status' => $status,
        'selesai_at' => $selesaiAt,
    ]);

    return $ticket->fresh();
}

it('averages completion hours only across selesai tickets', function () {
    tiketDenganWaktu('2026-01-01 10:00:00', '2026-01-01 14:00:00'); // 4 jam
    tiketDenganWaktu('2026-01-02 08:00:00', '2026-01-02 14:00:00'); // 6 jam
    tiketDenganWaktu('2026-01-03 09:00:00', null, 'proses'); // belum selesai, diabaikan

    expect(Ticket::rataRataPenyelesaianJam())->toBe(5.0);
});

it('returns null when there are no selesai tickets', function () {
    tiketDenganWaktu('2026-01-01 10:00:00', null, 'baru');

    expect(Ticket::rataRataPenyelesaianJam())->toBeNull();
});

it('respects a pre-filtered query', function () {
    $unitA = \App\Models\Unit::factory()->create();
    $unitB = \App\Models\Unit::factory()->create();

    $t1 = tiketDenganWaktu('2026-01-01 10:00:00', '2026-01-01 12:00:00'); // 2 jam
    $t2 = tiketDenganWaktu('2026-01-01 10:00:00', '2026-01-01 20:00:00'); // 10 jam
    DB::table('tickets')->where('id', $t1->id)->update(['unit_id' => $unitA->id]);
    DB::table('tickets')->where('id', $t2->id)->update(['unit_id' => $unitB->id]);

    $rataUnitA = Ticket::rataRataPenyelesaianJam(Ticket::query()->where('unit_id', $unitA->id));

    expect($rataUnitA)->toBe(2.0);
});
