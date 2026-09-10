<?php

namespace App\Services;

use App\Support\Bpjs\VclaimSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Klien tipis untuk mengukur response time API VClaim BPJS Kesehatan.
 * Sengaja tidak membongkar enkripsi isi respons (LZString+AES) — untuk
 * kebutuhan cek response time, kode & pesan di `metadata` (yang memang
 * tidak terenkripsi pada respons VClaim) sudah cukup untuk tahu
 * berhasil/gagalnya panggilan.
 */
class BpjsVclaimClient
{
    /**
     * @return array{
     *     berhasil_terhubung: bool,
     *     status_http: int|null,
     *     durasi_ms: float,
     *     metadata_code: int|string|null,
     *     metadata_message: string|null,
     *     error: string|null,
     * }
     */
    public function testResponseTime(?string $endpoint = null): array
    {
        $consId = (string) config('bpjs.vclaim.cons_id');
        $secretKey = (string) config('bpjs.vclaim.secret_key');
        $timestamp = VclaimSignature::timestamp(time());

        $endpoint = $endpoint ?: (string) config('bpjs.vclaim.test_endpoint');
        $url = rtrim((string) config('bpjs.vclaim.base_url'), '/').'/'.ltrim($endpoint, '/');

        $mulai = microtime(true);

        try {
            $respons = Http::withHeaders([
                'X-cons-id' => $consId,
                'X-timestamp' => (string) $timestamp,
                'X-signature' => VclaimSignature::buat($consId, $timestamp, $secretKey),
                'user_key' => (string) config('bpjs.vclaim.user_key'),
            ])
                ->timeout((int) config('bpjs.vclaim.timeout'))
                ->get($url);

            $durasiMs = (microtime(true) - $mulai) * 1000;

            return [
                'berhasil_terhubung' => true,
                'status_http' => $respons->status(),
                'durasi_ms' => $durasiMs,
                'metadata_code' => $respons->json('metadata.code'),
                'metadata_message' => $respons->json('metadata.message'),
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            $durasiMs = (microtime(true) - $mulai) * 1000;

            return [
                'berhasil_terhubung' => false,
                'status_http' => null,
                'durasi_ms' => $durasiMs,
                'metadata_code' => null,
                'metadata_message' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
