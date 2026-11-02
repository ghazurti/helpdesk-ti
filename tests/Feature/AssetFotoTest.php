<?php

use App\Filament\Resources\AssetResource\Pages\CreateAsset;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\{Asset, AssetCategory, Unit, User};
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('stores an uploaded asset photo on the public disk', function () {
    $kategori = AssetCategory::factory()->create();
    $unit = Unit::factory()->create();

    livewire(CreateAsset::class)
        ->fillForm([
            'kode' => 'AST-FOTO-1',
            'nama' => 'Laptop Poli Anak',
            'asset_category_id' => $kategori->id,
            'unit_id' => $unit->id,
            'kondisi' => 'baik',
            'foto' => UploadedFile::fake()->image('laptop.jpg'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $asset = Asset::where('kode', 'AST-FOTO-1')->first();

    expect($asset->foto)->not->toBeNull()
        ->and($asset->foto)->toStartWith('aset-foto/');
    Storage::disk('public')->assertExists($asset->foto);
});

it('allows an asset without a photo', function () {
    $asset = Asset::factory()->create(['foto' => null]);

    expect($asset->fresh()->foto)->toBeNull();
});

it('shows the photo column on the asset list', function () {
    Asset::factory()->create();

    livewire(ListAssets::class)
        ->assertOk()
        ->assertTableColumnExists('foto');
});
