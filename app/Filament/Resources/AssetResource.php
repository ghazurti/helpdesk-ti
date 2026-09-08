<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource\RelationManagers;
use App\Models\Asset;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationGroup = 'Operasional';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Aset';

    protected static ?string $pluralModelLabel = 'Aset';

    public static function kondisiOptions(): array
    {
        return [
            'baik' => 'Baik',
            'rusak' => 'Rusak',
            'perbaikan' => 'Perbaikan',
            'dihapus' => 'Dihapus',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('asset_category_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('merk')
                    ->label('Merk')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('model')
                    ->label('Model')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('serial_number')
                    ->label('Nomor Seri')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\DatePicker::make('tanggal_beli')
                    ->label('Tanggal Beli'),
                Forms\Components\TextInput::make('harga')
                    ->label('Harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(null),
                Forms\Components\DatePicker::make('garansi_sampai')
                    ->label('Garansi Sampai'),
                Forms\Components\Select::make('kondisi')
                    ->label('Kondisi')
                    ->options(self::kondisiOptions())
                    ->required()
                    ->default('baik'),
                Forms\Components\Select::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('masa_manfaat_bulan')
                    ->label('Masa Manfaat (bulan)')
                    ->numeric()
                    ->default(null),
                Forms\Components\Textarea::make('catatan')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kategori.nama')
                    ->label('Kategori')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.nama')
                    ->label('Unit')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::kondisiOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'baik' => 'success',
                        'rusak' => 'danger',
                        'perbaikan' => 'warning',
                        'dihapus' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('nilai_buku')
                    ->label('Nilai Buku')
                    ->money('IDR')
                    ->state(fn (Asset $record) => $record->nilaiBuku()),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kondisi')
                    ->label('Kondisi')
                    ->options(self::kondisiOptions()),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama'),
                Tables\Filters\SelectFilter::make('asset_category_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama'),
            ])
            ->actions([
                Tables\Actions\Action::make('pindah')
                    ->label('Pindah Unit')
                    ->icon('heroicon-o-arrows-right-left')
                    ->form([
                        Forms\Components\Select::make('to_unit_id')
                            ->label('Unit Tujuan')
                            ->relationship('unit', 'nama')
                            ->required(),
                        Forms\Components\TextInput::make('keterangan')
                            ->label('Keterangan'),
                    ])
                    ->action(function (Asset $record, array $data) {
                        $record->pindahKe(
                            Unit::find($data['to_unit_id']),
                            $data['keterangan'] ?? null,
                            auth()->user(),
                        );
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MovementsRelationManager::class,
            RelationManagers\RepairsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
