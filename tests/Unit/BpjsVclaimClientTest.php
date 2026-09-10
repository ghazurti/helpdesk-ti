<?php

use App\Services\BpjsVclaimClient;
use App\Support\Bpjs\VclaimSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'bpjs.vclaim.base_url' => 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/',
        'bpjs.vclaim.cons_id' => 'cons-uji',
        'bpjs.vclaim.secret_key' => 'rahasia-uji',
        'bpjs.vclaim.user_key' => 'userkey-uji',
        'bpjs.vclaim.test_endpoint' => 'Referensi/diagnosa/A00',
        'bpjs.vclaim.timeout' => 10,
    ]);
});

it('reports a successful call with BPJS metadata and a measured duration', function () {
    Http::fake([
        '*' => Http::response(['metadata' => ['message' => 'OK', 'code' => 200], 'response' => 'terenkripsi'], 200),
    ]);

    $hasil = (new BpjsVclaimClient)->testResponseTime();

    expect($hasil['berhasil_terhubung'])->toBeTrue()
        ->and($hasil['status_http'])->toBe(200)
        ->and($hasil['metadata_code'])->toBe(200)
        ->and($hasil['metadata_message'])->toBe('OK')
        ->and($hasil['durasi_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0)
        ->and($hasil['error'])->toBeNull();
});

it('still connects successfully when BPJS reports a logical error in metadata', function () {
    Http::fake([
        '*' => Http::response(['metadata' => ['message' => 'cons_id tidak valid', 'code' => 401], 'response' => null], 200),
    ]);

    $hasil = (new BpjsVclaimClient)->testResponseTime();

    expect($hasil['berhasil_terhubung'])->toBeTrue()
        ->and($hasil['metadata_code'])->toBe(401)
        ->and($hasil['metadata_message'])->toBe('cons_id tidak valid');
});

it('reports a connection failure without a valid HTTP response', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not resolve host: apijkn.bpjs-kesehatan.go.id');
    });

    $hasil = (new BpjsVclaimClient)->testResponseTime();

    expect($hasil['berhasil_terhubung'])->toBeFalse()
        ->and($hasil['status_http'])->toBeNull()
        ->and($hasil['metadata_code'])->toBeNull()
        ->and($hasil['error'])->toContain('Could not resolve host');
});

it('uses a custom endpoint path when one is given, overriding the config default', function () {
    Http::fake(['*' => Http::response(['metadata' => ['message' => 'OK', 'code' => 200]], 200)]);

    (new BpjsVclaimClient)->testResponseTime('Rujukan/RS/1234567890');

    Http::assertSent(fn ($request) => $request->url()
        === 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Rujukan/RS/1234567890');
});

it('falls back to the configured default endpoint when none is given', function () {
    Http::fake(['*' => Http::response(['metadata' => ['message' => 'OK', 'code' => 200]], 200)]);

    (new BpjsVclaimClient)->testResponseTime();

    Http::assertSent(fn ($request) => $request->url()
        === 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Referensi/diagnosa/A00');
});

it('sends a correctly signed request to the configured test endpoint', function () {
    Http::fake(['*' => Http::response(['metadata' => ['message' => 'OK', 'code' => 200]], 200)]);

    (new BpjsVclaimClient)->testResponseTime();

    Http::assertSent(function ($request) {
        $timestamp = (int) $request->header('X-timestamp')[0];
        $signatureSeharusnya = VclaimSignature::buat('cons-uji', $timestamp, 'rahasia-uji');

        return $request->url() === 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Referensi/diagnosa/A00'
            && $request->header('X-cons-id')[0] === 'cons-uji'
            && $request->header('X-signature')[0] === $signatureSeharusnya
            && $request->header('user_key')[0] === 'userkey-uji';
    });
});
