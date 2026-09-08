<?php

use App\Models\Asset;
use Carbon\Carbon;

function buatAset(array $override = []): Asset
{
    return (new Asset)->forceFill(array_merge([
        'harga' => 12_000_000,
        'tanggal_beli' => Carbon::parse('2024-01-01'),
        'masa_manfaat_bulan' => 48, // Rp250.000/bulan
    ], $override));
}

it('returns full price before any month passes', function () {
    expect(buatAset()->nilaiBuku(Carbon::parse('2024-01-01')))->toBe(12000000.00);
});

it('depreciates straight-line after 12 months', function () {
    // 12 * 250.000 = 3.000.000 accumulated
    expect(buatAset()->nilaiBuku(Carbon::parse('2025-01-01')))->toBe(9000000.00);
});

it('never goes below zero past useful life', function () {
    expect(buatAset()->nilaiBuku(Carbon::parse('2035-01-01')))->toBe(0.0);
});

it('returns price when depreciation inputs are missing', function () {
    expect(buatAset(['masa_manfaat_bulan' => null])->nilaiBuku())->toBe(12000000.00);
});
