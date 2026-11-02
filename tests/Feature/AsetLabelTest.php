<?php

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\{Asset, Unit, User};
use App\Support\AsetQr;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('encodes the asset edit page URL in the QR', function () {
    $asset = Asset::factory()->create();

    expect(AsetQr::urlUntuk($asset))
        ->toBe(AssetResource::getUrl('edit', ['record' => $asset]));
});

it('prints a label with QR, kode, nama and unit for each selected asset', function () {
    $this->actingAs(User::factory()->stafIt()->create());
    $igd = Unit::factory()->create(['nama' => 'IGD']);
    $a = Asset::factory()->create(['kode' => 'AST-LBL-1', 'nama' => 'PC Triase', 'unit_id' => $igd->id]);
    $b = Asset::factory()->create(['kode' => 'AST-LBL-2', 'nama' => 'Printer Loket']);

    $this->get(route('filament.admin.aset.label', ['ids' => "{$a->id},{$b->id}"]))
        ->assertOk()
        ->assertSee('AST-LBL-1')
        ->assertSee('PC Triase')
        ->assertSee('IGD')
        ->assertSee('AST-LBL-2')
        ->assertSee('Printer Loket')
        ->assertSee('<svg', false);
});

it('returns 404 when no valid asset is selected', function () {
    $this->actingAs(User::factory()->stafIt()->create());

    $this->get(route('filament.admin.aset.label', ['ids' => '999999']))
        ->assertNotFound();
});

it('requires login to print labels', function () {
    $asset = Asset::factory()->create();

    $this->get(route('filament.admin.aset.label', ['ids' => $asset->id]))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('bulk print redirects to the label page for exactly the selected assets', function () {
    $this->actingAs(User::factory()->admin()->create());
    $dipilih = Asset::factory()->count(2)->create();
    Asset::factory()->create(); // tidak dipilih

    livewire(ListAssets::class)
        ->callTableBulkAction('cetakLabel', $dipilih)
        ->assertRedirect(route('filament.admin.aset.label', [
            'ids' => $dipilih->pluck('id')->implode(','),
        ]));
});

it('offers a per-row label action and a bulk print action on the asset list', function () {
    $this->actingAs(User::factory()->admin()->create());
    Asset::factory()->create();

    livewire(ListAssets::class)
        ->assertTableActionExists('label')
        ->assertTableBulkActionExists('cetakLabel');
});
