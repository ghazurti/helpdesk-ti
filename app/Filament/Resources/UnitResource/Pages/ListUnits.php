<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use App\Models\Unit;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tambahBanyak')
                ->label('Tambah Banyak')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->visible(fn () => auth()->user()->can('create', Unit::class))
                ->modalHeading('Tambah Banyak Unit')
                ->modalSubmitActionLabel('Simpan Semua')
                ->form([
                    Textarea::make('daftar')
                        ->label('Nama unit')
                        ->placeholder("IGD\nFarmasi\nRadiologi\nPoli Anak")
                        ->helperText('Satu unit per baris. Nama yang sudah ada otomatis dilewati.')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ada = Unit::pluck('nama')->map(fn ($n) => mb_strtolower($n))->flip();
                    $dibuat = 0;
                    $dilewati = 0;

                    foreach (preg_split('/\R/', $data['daftar']) as $nama) {
                        $nama = trim($nama);
                        if ($nama === '') {
                            continue;
                        }
                        if ($ada->has(mb_strtolower($nama))) {
                            $dilewati++;

                            continue;
                        }
                        Unit::create(['nama' => mb_substr($nama, 0, 255)]);
                        $ada[mb_strtolower($nama)] = true;
                        $dibuat++;
                    }

                    Notification::make()
                        ->title("{$dibuat} unit ditambahkan".($dilewati ? ", {$dilewati} dilewati karena sudah ada" : ''))
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
