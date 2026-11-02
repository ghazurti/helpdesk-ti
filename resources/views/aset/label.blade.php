<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Aset ({{ $assets->count() }})</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; }
        .toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
        .toolbar button, .toolbar a {
            padding: 8px 14px; border: 1px solid #999; border-radius: 6px; background: #f3f3f3;
            color: #000; font-size: 14px; text-decoration: none; cursor: pointer;
        }
        .toolbar button { background: #f59e0b; border-color: #d97706; color: #fff; font-weight: bold; }
        .lembar { display: grid; grid-template-columns: repeat(auto-fill, minmax(6.2cm, 1fr)); gap: 4mm; }
        .label {
            display: flex; align-items: center; gap: 3mm; padding: 2.5mm;
            border: 1px dashed #777; border-radius: 2mm; break-inside: avoid; page-break-inside: avoid;
        }
        .label .qr { width: 24mm; height: 24mm; flex-shrink: 0; }
        .label .qr svg { width: 100%; height: 100%; display: block; }
        .label .info { min-width: 0; line-height: 1.25; }
        .label .instansi { font-size: 7pt; color: #444; text-transform: uppercase; letter-spacing: .3px; }
        .label .kode { font-size: 11pt; font-weight: bold; word-break: break-all; }
        .label .nama { font-size: 9pt; }
        .label .unit { font-size: 8pt; color: #333; }
        @page { size: A4; margin: 10mm; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak Label</button>
        <a href="{{ \App\Filament\Resources\AssetResource::getUrl('index') }}">Kembali ke Daftar Aset</a>
    </div>

    <div class="lembar">
        @foreach ($assets as $asset)
            <div class="label">
                <div class="qr">{!! \App\Support\AsetQr::svg(\App\Support\AsetQr::urlUntuk($asset)) !!}</div>
                <div class="info">
                    <div class="instansi">{{ config('app.name') }}</div>
                    <div class="kode">{{ $asset->kode }}</div>
                    <div class="nama">{{ $asset->nama }}</div>
                    <div class="unit">{{ $asset->unit?->nama ?? '-' }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
