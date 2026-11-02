<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Support\AsetImport;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Exception\OpenSpoutException;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('templateImpor')
                ->label('Unduh Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()->can('create', Asset::class))
                ->action(function () {
                    $path = sys_get_temp_dir().'/'.Str::uuid().'.xlsx';
                    AsetImport::tulisTemplate($path);

                    return response()->download($path, 'template-import-aset.xlsx')->deleteFileAfterSend();
                }),
            Actions\Action::make('import')
                ->label('Import Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()->can('create', Asset::class))
                ->modalHeading('Import Aset dari Excel')
                ->modalSubmitActionLabel('Import')
                ->form([
                    FileUpload::make('file')
                        ->label('File .xlsx atau .csv')
                        ->disk('local')
                        ->directory('impor-aset')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(5120)
                        ->required()
                        ->helperText('Pakai format dari tombol "Unduh Template". Kode yang sudah ada akan dilewati, tidak ditimpa.'),
                ])
                ->action(function (array $data) {
                    $disk = Storage::disk('local');

                    try {
                        $hasil = (new AsetImport)->impor($disk->path($data['file']));
                    } catch (OpenSpoutException) {
                        Notification::make()
                            ->title('File tidak bisa dibaca')
                            ->body('Pastikan file berformat .xlsx atau .csv yang valid.')
                            ->danger()
                            ->send();

                        return;
                    } finally {
                        $disk->delete($data['file']);
                    }

                    static::kirimRingkasan($hasil);
                }),
            Actions\CreateAction::make(),
        ];
    }

    /** @param array{berhasil: int, dilewati: list<array{baris: int, alasan: string}>} $hasil */
    private static function kirimRingkasan(array $hasil): void
    {
        $jumlahLewat = count($hasil['dilewati']);
        $notifikasi = Notification::make()
            ->title("{$hasil['berhasil']} aset berhasil diimport".($jumlahLewat ? ", {$jumlahLewat} baris dilewati" : ''));

        if ($jumlahLewat === 0) {
            $notifikasi->success()->send();

            return;
        }

        $rincian = collect($hasil['dilewati'])
            ->take(10)
            ->map(fn ($l) => "Baris {$l['baris']}: {$l['alasan']}")
            ->implode("\n");
        if ($jumlahLewat > 10) {
            $rincian .= "\n…dan ".($jumlahLewat - 10).' baris lainnya.';
        }

        $notifikasi
            ->body($rincian)
            ->persistent();

        $hasil['berhasil'] > 0 ? $notifikasi->warning() : $notifikasi->danger();
        $notifikasi->send();
    }
}
