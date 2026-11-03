<?php

use App\Filament\Resources\UnitResource\Pages\ListUnits;
use App\Models\{Unit, User};
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates many units at once, one per line', function () {
    livewire(ListUnits::class)
        ->callAction('tambahBanyak', data: ['daftar' => "IGD\n  Farmasi  \n\nRadiologi\n"])
        ->assertHasNoActionErrors()
        ->assertNotified('3 unit ditambahkan');

    expect(Unit::orderBy('nama')->pluck('nama')->all())->toBe(['Farmasi', 'IGD', 'Radiologi']);
});

it('skips names that already exist or repeat in the list, case-insensitively', function () {
    Unit::create(['nama' => 'IGD']);

    livewire(ListUnits::class)
        ->callAction('tambahBanyak', data: ['daftar' => "igd\nLaboratorium\nlaboratorium\nPoli Anak"])
        ->assertHasNoActionErrors()
        ->assertNotified('2 unit ditambahkan, 2 dilewati karena sudah ada');

    expect(Unit::count())->toBe(3)
        ->and(Unit::where('nama', 'Laboratorium')->exists())->toBeTrue()
        ->and(Unit::where('nama', 'Poli Anak')->exists())->toBeTrue();
});

it('requires at least one name', function () {
    livewire(ListUnits::class)
        ->callAction('tambahBanyak', data: ['daftar' => ''])
        ->assertHasActionErrors(['daftar' => 'required']);
});
