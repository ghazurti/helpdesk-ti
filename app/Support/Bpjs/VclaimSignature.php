<?php

namespace App\Support\Bpjs;

/**
 * Pembuat timestamp & signature otentikasi untuk API VClaim BPJS Kesehatan,
 * mengikuti format resmi yang didokumentasikan BPJS (dipakai juga oleh SDK
 * resmi mereka). Murni fungsi kalkulasi, tidak melakukan panggilan HTTP.
 */
class VclaimSignature
{
    /**
     * BPJS mensyaratkan timestamp = unix time dikurangi offset tetap 7 jam
     * (25200 detik) — rumus resmi dari dokumentasi BPJS. Sengaja pakai
     * angka tetap, BUKAN strtotime('1970-01-01 07:00:00'): strtotime()
     * menafsirkan jam dinding itu memakai timezone default PHP yang
     * sedang aktif, jadi hasilnya berubah-ubah tergantung APP_TIMEZONE
     * server — di server dengan timezone selain UTC, signature yang
     * dikirim ke BPJS jadi salah tanpa pesan error yang jelas.
     */
    public static function timestamp(int $unixNow): int
    {
        return $unixNow - 25200;
    }

    public static function buat(string $consId, int $timestampBpjs, string $secretKey): string
    {
        return base64_encode(hash_hmac('sha256', $consId.'&'.$timestampBpjs, $secretKey, true));
    }
}
