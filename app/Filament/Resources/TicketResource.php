<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Resources\TicketResource\RelationManagers;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationGroup = 'Operasional';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $modelLabel = 'Tiket';

    protected static ?string $pluralModelLabel = 'Tiket';

    public static function prioritasOptions(): array
    {
        return [
            'rendah' => 'Rendah',
            'sedang' => 'Sedang',
            'tinggi' => 'Tinggi',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'baru' => 'Baru',
            'proses' => 'Proses',
            'pending' => 'Pending',
            'selesai' => 'Selesai',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'baru' => 'gray',
            'proses' => 'info',
            'pending' => 'warning',
            'selesai' => 'success',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nomor')
                    ->label('Nomor')
                    ->disabled()
                    ->dehydrated(false)
                    ->hiddenOn('create'),
                Forms\Components\TextInput::make('judul')
                    ->label('Judul')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('user_id')
                    ->label('Petugas')
                    ->relationship('petugas', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => auth()->id()),
                Forms\Components\DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->required()
                    ->default(fn () => now()->toDateString()),
                Forms\Components\Select::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('work_category_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('asset_id')
                    ->label('Aset')
                    ->relationship('asset', 'nama')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Select::make('prioritas')
                    ->label('Prioritas')
                    ->options(self::prioritasOptions())
                    ->required()
                    ->default('sedang'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(self::statusOptions())
                    ->required()
                    ->default('baru'),
                Forms\Components\Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('tindakan')
                    ->label('Tindakan')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('attachments_paths')
                    ->label('Foto/Lampiran')
                    ->multiple()
                    ->image()
                    ->directory('ticket-attachments')
                    ->columnSpanFull()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Forms\Components\FileUpload $component, ?Ticket $record) => $component->state(
                        $record?->attachments->pluck('path')->all() ?? []
                    ))
                    ->saveRelationshipsUsing(function (Ticket $record, $state) {
                        $record->attachments()->delete();
                        foreach ((array) $state as $path) {
                            $record->attachments()->create(['path' => $path]);
                        }
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor')
                    ->label('Nomor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('judul')
                    ->label('Judul')
                    ->searchable(),
                Tables\Columns\TextColumn::make('petugas.name')
                    ->label('Petugas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.nama')
                    ->label('Unit')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kategori.nama')
                    ->label('Kategori')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColors()[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('prioritas')
                    ->label('Prioritas')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::prioritasOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'rendah' => 'gray',
                        'sedang' => 'info',
                        'tinggi' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('prioritas')
                    ->label('Prioritas')
                    ->options(self::prioritasOptions()),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama'),
                Tables\Filters\SelectFilter::make('work_category_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Petugas')
                    ->relationship('petugas', 'name'),
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_dari')
                            ->label('Tanggal Dari'),
                        Forms\Components\DatePicker::make('tanggal_sampai')
                            ->label('Tanggal Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_dari'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_sampai'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    }),
            ])
            ->actions([
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
            RelationManagers\LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
