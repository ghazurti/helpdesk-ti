<?php
// tests/Feature/TicketStatusLogTest.php
use App\Models\{Ticket, User};

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->user = User::factory()->stafIt()->create();
});

it('logs a status change and stamps completion', function () {
    $t = Ticket::create([
        'user_id' => $this->user->id, 'judul' => 'x', 'deskripsi' => 'y',
        'tanggal' => '2026-02-10', 'status' => 'baru',
    ]);

    $t->update(['status' => 'selesai']);

    expect($t->logs()->count())->toBe(1)
        ->and($t->logs()->first()->status_lama)->toBe('baru')
        ->and($t->logs()->first()->status_baru)->toBe('selesai')
        ->and($t->fresh()->selesai_at)->not->toBeNull();
});

it('does not log when status is unchanged', function () {
    $t = Ticket::create([
        'user_id' => $this->user->id, 'judul' => 'x', 'deskripsi' => 'y',
        'tanggal' => '2026-02-10', 'status' => 'baru',
    ]);
    $t->update(['judul' => 'diubah']);
    expect($t->logs()->count())->toBe(0);
});
