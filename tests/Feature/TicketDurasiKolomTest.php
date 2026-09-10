<?php

use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('shows the formatted completion duration for a finished ticket', function () {
    $ticket = Ticket::create([
        'user_id' => auth()->id(),
        'judul' => 'Ganti toner printer',
        'deskripsi' => 'Toner habis',
        'tanggal' => now()->toDateString(),
        'status' => 'baru',
    ]);
    DB::table('tickets')->where('id', $ticket->id)->update([
        'created_at' => '2026-01-01 10:00:00',
        'status' => 'selesai',
        'selesai_at' => '2026-01-01 15:30:00',
    ]);

    livewire(ListTickets::class)
        ->assertOk()
        ->assertSee('5,5 jam');
});

it('shows a dash for a ticket that is not finished yet', function () {
    Ticket::create([
        'user_id' => auth()->id(),
        'judul' => 'Cek jaringan lantai 2',
        'deskripsi' => 'Wifi lambat',
        'tanggal' => now()->toDateString(),
        'status' => 'proses',
    ]);

    livewire(ListTickets::class)
        ->assertOk()
        ->assertSee('Durasi Penyelesaian');
});
