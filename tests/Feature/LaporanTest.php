<?php
// tests/Feature/LaporanTest.php
use App\Models\{User, Ticket, Unit};
use App\Filament\Pages\LaporanPekerjaan;
use Illuminate\Database\Eloquent\Builder;

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('filters tickets by date range', function () {
    $u = User::factory()->stafIt()->create();
    Ticket::factory()->create(['user_id' => $u->id, 'tanggal' => '2026-01-10']);
    Ticket::factory()->create(['user_id' => $u->id, 'tanggal' => '2026-03-10']);

    $q = LaporanPekerjaan::terapkanFilter(
        Ticket::query(),
        ['dari' => '2026-02-01', 'sampai' => '2026-03-31'],
    );

    expect($q->count())->toBe(1);
});

it('filters by unit and status together', function () {
    $u = User::factory()->stafIt()->create();
    $unit = Unit::factory()->create();
    Ticket::factory()->create(['user_id' => $u->id, 'unit_id' => $unit->id, 'status' => 'selesai']);
    Ticket::factory()->create(['user_id' => $u->id, 'unit_id' => $unit->id, 'status' => 'baru']);

    $q = LaporanPekerjaan::terapkanFilter(
        Ticket::query(),
        ['unit_id' => $unit->id, 'status' => 'selesai'],
    );
    expect($q->count())->toBe(1);
});
