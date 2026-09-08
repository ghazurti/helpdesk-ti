<?php
namespace App\Filament\Widgets;

use App\Models\{Ticket, Asset};
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatOverview extends Base
{
    protected function getStats(): array
    {
        return [
            Stat::make('Tiket Belum Selesai', Ticket::whereIn('status', ['baru','proses','pending'])->count()),
            Stat::make('Tiket Selesai', Ticket::where('status', 'selesai')->count()),
            Stat::make('Aset Rusak/Perbaikan', Asset::whereIn('kondisi', ['rusak','perbaikan'])->count()),
            Stat::make('Total Aset', Asset::count()),
        ];
    }
}
