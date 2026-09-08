<?php
namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class MonthlyTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Tren Pekerjaan per Bulan';

    protected function getData(): array
    {
        $rows = Ticket::selectRaw("DATE_FORMAT(tanggal, '%Y-%m') ym, count(*) c")
            ->groupBy('ym')->orderBy('ym')->get();
        return [
            'datasets' => [['label' => 'Tiket', 'data' => $rows->pluck('c')->all()]],
            'labels' => $rows->pluck('ym')->all(),
        ];
    }

    protected function getType(): string { return 'line'; }
}
