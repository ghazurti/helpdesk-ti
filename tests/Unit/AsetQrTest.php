<?php

use App\Support\AsetQr;

it('renders inline SVG markup without an XML prolog', function () {
    $svg = AsetQr::svg('https://contoh.test/admin/assets/1/edit');

    expect($svg)->toStartWith('<svg')
        ->and($svg)->not->toContain('<?xml');
});

it('renders a different QR for different content', function () {
    expect(AsetQr::svg('isi-satu'))->not->toBe(AsetQr::svg('isi-dua'));
});

it('renders the same QR for the same content', function () {
    expect(AsetQr::svg('isi-sama'))->toBe(AsetQr::svg('isi-sama'));
});
