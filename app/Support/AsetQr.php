<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Pembuat QR code untuk label aset. Output SVG (tanpa GD/imagick) yang
 * bisa langsung disisipkan ke HTML halaman cetak label.
 */
class AsetQr
{
    /**
     * Isi QR: alamat halaman aset di panel. Scan dengan HP yang sudah login
     * langsung membuka data aset (butuh login, tidak membuka data ke publik).
     */
    public static function urlUntuk(\App\Models\Asset $asset): string
    {
        return \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $asset]);
    }

    public static function svg(string $isi, int $ukuran = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($ukuran), new SvgImageBackEnd()));

        // Buang prolog XML di awal output agar aman disisipkan inline di HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString($isi)));
    }
}
