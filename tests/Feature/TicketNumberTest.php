<?php
// tests/Feature/TicketNumberTest.php
use App\Models\{Ticket, User, Unit, WorkCategory};

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->user = User::factory()->stafIt()->create();
});

function baseTicket(array $o = []): array
{
    return array_merge([
        'user_id' => test()->user->id,
        'judul' => 'Perbaikan printer',
        'deskripsi' => 'Ganti roller',
        'tanggal' => '2026-02-10',
    ], $o);
}

it('assigns the first number of the year', function () {
    $t = Ticket::create(baseTicket());
    expect($t->nomor)->toBe('TKT-2026-0001');
});

it('increments within the same year', function () {
    Ticket::create(baseTicket());
    $t2 = Ticket::create(baseTicket());
    expect($t2->nomor)->toBe('TKT-2026-0002');
});

it('resets the sequence for a new year', function () {
    Ticket::create(baseTicket(['tanggal' => '2026-12-31']));
    $t = Ticket::create(baseTicket(['tanggal' => '2027-01-02']));
    expect($t->nomor)->toBe('TKT-2027-0001');
});
