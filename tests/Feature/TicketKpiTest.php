<?php

use App\Filament\Pages\LaporanPekerjaan;
use App\Models\{Ticket, Unit, User};
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $this->travelTo('2026-03-10 12:00:00');
});

/**
 * Tiket dengan waktu dibuat/direspons/selesai yang ditentukan langsung di
 * database, supaya tidak tertimpa pengisian otomatis di model.
 */
function tiketKpi(string $prioritas, string $dibuat, ?string $direspons = null, ?string $selesai = null, ?int $unitId = null): Ticket
{
    $ticket = Ticket::create([
        'user_id' => auth()->id(),
        'unit_id' => $unitId,
        'judul' => 'Gangguan',
        'deskripsi' => '-',
        'prioritas' => $prioritas,
        'tanggal' => substr($dibuat, 0, 10),
    ]);
    DB::table('tickets')->where('id', $ticket->id)->update([
        'created_at' => $dibuat,
        'direspons_at' => $direspons,
        'selesai_at' => $selesai,
        'status' => $selesai ? 'selesai' : ($direspons ? 'proses' : 'baru'),
    ]);

    return $ticket->fresh();
}

it('records the response time when a ticket leaves status baru', function () {
    $ticket = Ticket::factory()->create(['status' => 'baru']);
    expect($ticket->direspons_at)->toBeNull();

    $this->travel(20)->minutes();
    $ticket->update(['status' => 'proses']);
    expect($ticket->fresh()->direspons_at->toDateTimeString())->toBe('2026-03-10 12:20:00');

    $this->travel(2)->hours();
    $ticket->update(['status' => 'selesai']);
    expect($ticket->fresh()->direspons_at->toDateTimeString())->toBe('2026-03-10 12:20:00');
});

it('treats a ticket created already in progress as responded immediately', function () {
    $ticket = Ticket::factory()->create(['status' => 'proses']);

    expect($ticket->direspons_at->toDateTimeString())->toBe('2026-03-10 12:00:00');
});

it('measures response speed against the target of each priority', function () {
    tiketKpi('tinggi', '2026-03-01 08:00:00', '2026-03-01 08:10:00'); // 10 mnt ≤ 15: tepat
    tiketKpi('tinggi', '2026-03-01 08:00:00', '2026-03-01 08:20:00'); // 20 mnt > 15: lewat
    tiketKpi('sedang', '2026-03-01 08:00:00', '2026-03-01 08:30:00'); // 30 mnt = 30: tepat
    tiketKpi('rendah', '2026-03-01 08:00:00', '2026-03-01 08:50:00'); // 50 mnt ≤ 60: tepat

    $kpi = Ticket::kpi()['respons'];

    expect($kpi['tepat'])->toBe(3)
        ->and($kpi['total'])->toBe(4)
        ->and($kpi['persen'])->toBe(75.0)
        ->and($kpi['target'])->toBe(90)
        ->and($kpi['tercapai'])->toBeFalse();
});

it('counts unanswered tickets past their target as late and skips those still within it', function () {
    tiketKpi('tinggi', '2026-03-10 11:00:00');                        // belum direspons, sudah 60 mnt: lewat
    tiketKpi('tinggi', '2026-03-10 11:50:00');                        // belum direspons, baru 10 mnt: belum dihitung
    tiketKpi('sedang', '2026-03-10 08:00:00', '2026-03-10 08:05:00'); // tepat

    $kpi = Ticket::kpi()['respons'];

    expect($kpi['tepat'])->toBe(1)
        ->and($kpi['total'])->toBe(2)
        ->and($kpi['persen'])->toBe(50.0);
});

it('measures on-time completion against the target of each priority', function () {
    tiketKpi('tinggi', '2026-03-01 08:00:00', '2026-03-01 08:05:00', '2026-03-01 11:00:00'); // 3 jam ≤ 4: tepat
    tiketKpi('tinggi', '2026-03-01 08:00:00', '2026-03-01 08:05:00', '2026-03-01 13:00:00'); // 5 jam > 4: lewat
    tiketKpi('sedang', '2026-03-01 08:00:00', '2026-03-01 08:05:00', '2026-03-02 08:00:00'); // 24 jam = 24: tepat
    tiketKpi('rendah', '2026-03-09 08:00:00', '2026-03-09 08:05:00');                        // 28 jam, batas 72: belum dihitung
    tiketKpi('rendah', '2026-03-01 08:00:00', '2026-03-01 08:05:00');                        // belum selesai, lewat 72 jam: lewat

    $kpi = Ticket::kpi()['penyelesaian'];

    expect($kpi['tepat'])->toBe(2)
        ->and($kpi['total'])->toBe(4)
        ->and($kpi['persen'])->toBe(50.0)
        ->and($kpi['target'])->toBe(85)
        ->and($kpi['tercapai'])->toBeFalse();
});

it('marks a KPI as met when the percentage reaches its target', function () {
    foreach (range(1, 9) as $_) {
        tiketKpi('sedang', '2026-03-01 08:00:00', '2026-03-01 08:10:00', '2026-03-01 10:00:00');
    }
    tiketKpi('sedang', '2026-03-01 08:00:00', '2026-03-01 09:00:00', '2026-03-03 10:00:00');

    $kpi = Ticket::kpi();

    expect($kpi['respons']['persen'])->toBe(90.0)
        ->and($kpi['respons']['tercapai'])->toBeTrue()
        ->and($kpi['penyelesaian']['persen'])->toBe(90.0)
        ->and($kpi['penyelesaian']['tercapai'])->toBeTrue();
});

it('reports no percentage when there are no tickets to measure', function () {
    $kpi = Ticket::kpi();

    expect($kpi['respons']['total'])->toBe(0)
        ->and($kpi['respons']['persen'])->toBeNull()
        ->and($kpi['respons']['tercapai'])->toBeNull();
});

it('shows both KPIs on the report page, following the active filter', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $unitA = Unit::factory()->create();
    $unitB = Unit::factory()->create();
    tiketKpi('tinggi', '2026-03-01 08:00:00', '2026-03-01 08:10:00', '2026-03-01 09:00:00', $unitA->id);
    tiketKpi('tinggi', '2026-03-01 08:00:00', '2026-03-01 09:00:00', '2026-03-02 09:00:00', $unitB->id);

    livewire(LaporanPekerjaan::class)
        ->assertSee('Kecepatan respons gangguan sesuai prioritas')
        ->assertSee('Ketepatan waktu penyelesaian gangguan')
        ->assertSee('50,0%')
        ->set('unit_id', $unitA->id)
        ->assertSee('100,0%')
        ->assertSee('Tercapai');
});
