<?php

use App\Filament\Pages\LaporanPekerjaan;
use App\Models\{Ticket, Unit, User};
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function tiketLaporan(?int $unitId, string $createdAt, string $selesaiAt): Ticket
{
    $ticket = Ticket::create([
        'user_id' => auth()->id(),
        'unit_id' => $unitId,
        'judul' => 'Perbaikan komputer',
        'deskripsi' => 'Rutin',
        'tanggal' => substr($createdAt, 0, 10),
        'status' => 'baru',
    ]);
    DB::table('tickets')->where('id', $ticket->id)->update([
        'created_at' => $createdAt,
        'status' => 'selesai',
        'selesai_at' => $selesaiAt,
    ]);

    return $ticket->fresh();
}

it('shows the average completion time for all report tickets', function () {
    tiketLaporan(null, '2026-01-01 10:00:00', '2026-01-01 14:00:00'); // 4 jam
    tiketLaporan(null, '2026-01-02 08:00:00', '2026-01-02 14:00:00'); // 6 jam

    livewire(LaporanPekerjaan::class)
        ->assertOk()
        ->assertSee('5,0 jam');
});

it('recomputes the average when a unit filter narrows the ticket set', function () {
    $unitA = Unit::factory()->create();
    $unitB = Unit::factory()->create();
    tiketLaporan($unitA->id, '2026-01-01 10:00:00', '2026-01-01 12:00:00'); // 2 jam
    tiketLaporan($unitB->id, '2026-01-01 10:00:00', '2026-01-01 20:00:00'); // 10 jam

    livewire(LaporanPekerjaan::class)
        ->set('unit_id', $unitA->id)
        ->assertSee('2,0 jam');
});
