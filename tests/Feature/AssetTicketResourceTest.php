<?php

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkCategory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates a ticket through the panel and auto-numbers it', function () {
    $unit = Unit::factory()->create();
    $kat = WorkCategory::factory()->create();
    livewire(CreateTicket::class)
        ->fillForm([
            'user_id' => auth()->id(),
            'judul' => 'Instalasi ulang Windows',
            'deskripsi' => 'PC front office',
            'tanggal' => now()->toDateString(),
            'unit_id' => $unit->id,
            'work_category_id' => $kat->id,
            'status' => 'baru',
            'prioritas' => 'sedang',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Ticket::first()->nomor)
        ->toStartWith('TKT-'.now()->format('Y').'-');
});

it('lists assets', function () {
    Asset::factory()->count(3)->create();
    livewire(ListAssets::class)
        ->assertOk()->assertCanSeeTableRecords(Asset::all());
});
