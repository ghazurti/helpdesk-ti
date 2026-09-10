<?php

use App\Models\Ticket;
use Carbon\Carbon;

function buatTiketDurasi(array $override = []): Ticket
{
    return (new Ticket)->forceFill(array_merge([
        'created_at' => Carbon::parse('2026-01-01 10:00:00'),
        'selesai_at' => null,
    ], $override));
}

it('returns null duration when the ticket is not yet selesai', function () {
    expect(buatTiketDurasi()->durasiPenyelesaianJam())->toBeNull();
});

it('computes the exact hours between created_at and selesai_at', function () {
    $tiket = buatTiketDurasi(['selesai_at' => Carbon::parse('2026-01-01 15:30:00')]);

    expect($tiket->durasiPenyelesaianJam())->toBe(5.5);
});

it('formats durations under 24 hours as jam', function () {
    expect(Ticket::formatDurasiJam(5.5))->toBe('5,5 jam');
});

it('formats durations of 24 hours or more as hari', function () {
    expect(Ticket::formatDurasiJam(24.0))->toBe('1,0 hari')
        ->and(Ticket::formatDurasiJam(50.0))->toBe('2,1 hari');
});

it('formats a null duration as a dash', function () {
    expect(Ticket::formatDurasiJam(null))->toBe('-');
});
