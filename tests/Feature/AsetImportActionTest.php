<?php

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\{Asset, User};
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->stafIt()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('imports assets from an uploaded file and reports the result', function () {
    $csv = "kode,nama,kategori\nAST-UI-1,PC Ruang Rapat,Komputer\n,Tanpa Kode,Komputer\n";

    livewire(ListAssets::class)
        ->callAction('import', data: [
            'file' => UploadedFile::fake()->createWithContent('aset.csv', $csv),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('1 aset berhasil diimport, 1 baris dilewati');

    expect(Asset::where('kode', 'AST-UI-1')->exists())->toBeTrue()
        ->and(Asset::count())->toBe(1);
});

it('removes the uploaded file after importing', function () {
    livewire(ListAssets::class)
        ->callAction('import', data: [
            'file' => UploadedFile::fake()->createWithContent('aset.csv', "kode,nama,kategori\nAST-UI-2,PC,Komputer\n"),
        ]);

    expect(Storage::disk('local')->allFiles('impor-aset'))->toBe([]);
});

it('downloads the import template', function () {
    livewire(ListAssets::class)
        ->callAction('templateImpor')
        ->assertFileDownloaded('template-import-aset.xlsx');
});
