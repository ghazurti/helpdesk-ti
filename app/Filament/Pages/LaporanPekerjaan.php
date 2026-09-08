<?php
namespace App\Filament\Pages;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\Page;

class LaporanPekerjaan extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?string $title = 'Laporan Pekerjaan';
    protected static string $view = 'filament.pages.laporan-pekerjaan';

    public ?string $dari = null;
    public ?string $sampai = null;
    public ?int $user_id = null;
    public ?int $unit_id = null;
    public ?int $work_category_id = null;
    public ?string $status = null;

    public static function terapkanFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
            ->when($f['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '<=', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['unit_id'] ?? null, fn ($q, $v) => $q->where('unit_id', $v))
            ->when($f['work_category_id'] ?? null, fn ($q, $v) => $q->where('work_category_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
    }

    public function hasilQuery(): Builder
    {
        return static::terapkanFilter(
            Ticket::query()->with(['petugas','unit','kategori'])->latest('tanggal'),
            $this->only(['dari','sampai','user_id','unit_id','work_category_id','status']),
        );
    }

    public function exportPdf()
    {
        $tickets = $this->hasilQuery()->get();
        $pdf = Pdf::loadView('laporan.pekerjaan-pdf', ['tickets' => $tickets]);
        return response()->streamDownload(fn () => print($pdf->output()), 'laporan-pekerjaan.pdf');
    }

    public function exportExcel()
    {
        $tickets = $this->hasilQuery()->get();
        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nomor','Tanggal','Petugas','Unit','Kategori','Judul','Status']);
            foreach ($tickets as $t) {
                fputcsv($out, [
                    $t->nomor, $t->tanggal?->format('Y-m-d'), $t->petugas?->name,
                    $t->unit?->nama, $t->kategori?->nama, $t->judul, $t->status,
                ]);
            }
            fclose($out);
        }, 'laporan-pekerjaan.csv', ['Content-Type' => 'text/csv']);
    }
}
