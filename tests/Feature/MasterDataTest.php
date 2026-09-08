<?php

use App\Models\{Unit, WorkCategory, AssetCategory, User};
use Filament\Facades\Filament;
use function Pest\Livewire\livewire;

it('creates a unit', function () {
    $u = Unit::create(['nama' => 'IGD', 'keterangan' => 'Instalasi Gawat Darurat']);
    expect($u->fresh()->nama)->toBe('IGD');
});

it('enforces unique unit name', function () {
    Unit::create(['nama' => 'Farmasi']);
    Unit::create(['nama' => 'Farmasi']);
})->throws(\Illuminate\Database\QueryException::class);

it('creates work and asset categories', function () {
    expect(WorkCategory::create(['nama' => 'Jaringan'])->exists)->toBeTrue()
        ->and(AssetCategory::create(['nama' => 'Komputer'])->exists)->toBeTrue();
});

it('renders the unit list for an admin', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    livewire(\App\Filament\Resources\UnitResource\Pages\ListUnits::class)
        ->assertOk();
});
