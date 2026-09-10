<?php
namespace App\Filament\Pages;

use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

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

    #[Computed]
    public function petugasOptions(): Collection
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function unitOptions(): Collection
    {
        return Unit::orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function kategoriOptions(): Collection
    {
        return WorkCategory::orderBy('nama')->get(['id', 'nama']);
    }

    public function rataRataPenyelesaian(): string
    {
        return Ticket::formatDurasiJam(Ticket::rataRataPenyelesaianJam($this->hasilQuery()));
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
        return response()->streamDownload(fn () => print($pdf->output()), 'laporan-pekerjaan.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function exportExcel()
    {
        $tickets = $this->hasilQuery()->get();
        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map([static::class, 'csvAman'], ['Nomor','Tanggal','Petugas','Unit','Kategori','Judul','Status']));
            foreach ($tickets as $t) {
                fputcsv($out, array_map([static::class, 'csvAman'], [
                    $t->nomor, $t->tanggal?->format('Y-m-d'), $t->petugas?->name,
                    $t->unit?->nama, $t->kategori?->nama, $t->judul, $t->status,
                ]));
            }
            fclose($out);
        }, 'laporan-pekerjaan.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Prevent CSV formula injection: prefix values that start with a
     * character a spreadsheet app would interpret as a formula trigger
     * with a single quote so they are treated as plain text.
     */
    protected static function csvAman(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
