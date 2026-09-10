<?php

use App\Support\Bpjs\VclaimSignature;

it('converts a unix timestamp to the BPJS-formatted timestamp', function () {
    expect(VclaimSignature::timestamp(1735689600))->toBe(1735664400);
});

it('is not affected by the application default timezone', function () {
    // Regresi: implementasi awal pakai strtotime() yang bergantung pada
    // date_default_timezone_get(), sehingga hasilnya beda di server
    // dengan APP_TIMEZONE selain UTC (proyek ini pakai Asia/Makassar).
    $sebelum = date_default_timezone_get();
    date_default_timezone_set('Asia/Makassar');

    try {
        expect(VclaimSignature::timestamp(1735689600))->toBe(1735664400);
    } finally {
        date_default_timezone_set($sebelum);
    }
});

it('builds the HMAC-SHA256 signature exactly as BPJS expects', function () {
    // Nilai emas dihitung manual dengan rumus resmi BPJS:
    // base64_encode(hash_hmac('sha256', "$consId&$tStamp", $secretKey, true))
    $signature = VclaimSignature::buat(
        consId: '12345',
        timestampBpjs: 1735664400,
        secretKey: 'rahasia-contoh',
    );

    expect($signature)->toBe('+j6wlbXQh+VcQ0fKORu80yMda3/hESttH0EWWAQqtng=');
});

it('produces a different signature when the secret key differs', function () {
    $signature = VclaimSignature::buat(
        consId: '12345',
        timestampBpjs: 1735664400,
        secretKey: 'kunci-lain',
    );

    expect($signature)->not->toBe('+j6wlbXQh+VcQ0fKORu80yMda3/hESttH0EWWAQqtng=');
});
