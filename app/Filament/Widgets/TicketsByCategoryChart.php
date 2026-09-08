<?php
namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketsByCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Tiket per Kategori';

    protected function getData(): array
    {
        $rows = Ticket::selectRaw('work_category_id, count(*) c')
            ->with('kategori')->groupBy('work_category_id')->get();
        return [
            'datasets' => [['label' => 'Tiket', 'data' => $rows->pluck('c')->all()]],
            'labels' => $rows->map(fn ($r) => $r->kategori?->nama ?? 'Lainnya')->all(),
        ];
    }

    protected function getType(): string { return 'bar'; }
}
