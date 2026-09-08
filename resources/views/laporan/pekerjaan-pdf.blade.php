<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Pekerjaan IT</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        p.tanggal-cetak { margin-top: 0; color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        th { background-color: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Laporan Pekerjaan IT</h1>
    <p class="tanggal-cetak">Dicetak pada: {{ now()->format('d-m-Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Tanggal</th>
                <th>Petugas</th>
                <th>Unit</th>
                <th>Kategori</th>
                <th>Judul</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tickets as $t)
                <tr>
                    <td>{{ $t->nomor }}</td>
                    <td>{{ $t->tanggal?->format('d-m-Y') }}</td>
                    <td>{{ $t->petugas?->name }}</td>
                    <td>{{ $t->unit?->nama }}</td>
                    <td>{{ $t->kategori?->nama }}</td>
                    <td>{{ $t->judul }}</td>
                    <td>{{ $t->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
