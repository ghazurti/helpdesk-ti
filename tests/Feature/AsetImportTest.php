<?php

use App\Models\{Asset, AssetCategory, Unit};
use App\Support\AsetImport;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

function fileImpor(string $nama, string $isi): string
{
    $path = sys_get_temp_dir().'/'.uniqid('impor-', true).'-'.$nama;
    file_put_contents($path, $isi);

    return $path;
}

function xlsxImpor(array $baris): string
{
    $path = sys_get_temp_dir().'/'.uniqid('impor-', true).'.xlsx';
    $writer = new XlsxWriter();
    $writer->openToFile($path);
    foreach ($baris as $nilai) {
        $writer->addRow(Row::fromValues($nilai));
    }
    $writer->close();

    return $path;
}

it('imports valid CSV rows and auto-creates missing kategori and unit', function () {
    $csv = "kode,nama,kategori,merk,unit,kondisi,harga,tanggal_beli,masa_manfaat_bulan\n"
        ."AST-IMP-1,PC Pendaftaran,Komputer,Lenovo,Loket,baik,7500000,2024-01-15,48\n"
        ."AST-IMP-2,Printer Farmasi,Printer,Epson,Farmasi,rusak,,,\n";

    $hasil = (new AsetImport)->impor(fileImpor('aset.csv', $csv));

    expect($hasil['berhasil'])->toBe(2)
        ->and($hasil['dilewati'])->toBe([]);

    $pc = Asset::where('kode', 'AST-IMP-1')->with(['kategori', 'unit'])->first();
    expect($pc->nama)->toBe('PC Pendaftaran')
        ->and($pc->kategori->nama)->toBe('Komputer')
        ->and($pc->unit->nama)->toBe('Loket')
        ->and($pc->merk)->toBe('Lenovo')
        ->and((float) $pc->harga)->toBe(7500000.0)
        ->and($pc->tanggal_beli->toDateString())->toBe('2024-01-15')
        ->and($pc->masa_manfaat_bulan)->toBe(48);

    expect(Asset::where('kode', 'AST-IMP-2')->value('kondisi'))->toBe('rusak');
    expect(AssetCategory::where('nama', 'Printer')->exists())->toBeTrue()
        ->and(Unit::where('nama', 'Farmasi')->exists())->toBeTrue();
});

it('reuses existing kategori and unit matched by name, case-insensitively', function () {
    $kategori = AssetCategory::factory()->create(['nama' => 'Komputer']);
    $unit = Unit::factory()->create(['nama' => 'IGD']);

    $csv = "kode,nama,kategori,unit\nAST-IMP-3,PC Triase,komputer,igd\n";
    (new AsetImport)->impor(fileImpor('aset.csv', $csv));

    $asset = Asset::where('kode', 'AST-IMP-3')->first();
    expect($asset->asset_category_id)->toBe($kategori->id)
        ->and($asset->unit_id)->toBe($unit->id)
        ->and(AssetCategory::count())->toBe(1);
});

it('reads semicolon-separated CSV exported by Indonesian-locale Excel', function () {
    $csv = "kode;nama;kategori\nAST-IMP-4;Switch Lantai 2;Jaringan\n";

    $hasil = (new AsetImport)->impor(fileImpor('aset.csv', $csv));

    expect($hasil['berhasil'])->toBe(1)
        ->and(Asset::where('kode', 'AST-IMP-4')->value('nama'))->toBe('Switch Lantai 2');
});

it('accepts columns in any order and header names in any case', function () {
    $csv = "Kategori,NAMA, Kode \nUPS,UPS Server,AST-IMP-5\n";

    $hasil = (new AsetImport)->impor(fileImpor('aset.csv', $csv));

    expect($hasil['berhasil'])->toBe(1)
        ->and(Asset::where('kode', 'AST-IMP-5')->value('nama'))->toBe('UPS Server');
});

it('imports xlsx files including real date cells', function () {
    $path = xlsxImpor([
        ['kode', 'nama', 'kategori', 'tanggal_beli', 'garansi_sampai'],
        ['AST-IMP-6', 'Laptop IT', 'Komputer', new DateTimeImmutable('2025-03-01'), '2027-03-01'],
    ]);

    $hasil = (new AsetImport)->impor($path);

    $asset = Asset::where('kode', 'AST-IMP-6')->first();
    expect($hasil['berhasil'])->toBe(1)
        ->and($asset->tanggal_beli->toDateString())->toBe('2025-03-01')
        ->and($asset->garansi_sampai->toDateString())->toBe('2027-03-01');
});

it('skips invalid rows with a reason and still imports the valid ones', function () {
    Asset::factory()->create(['kode' => 'AST-SUDAH-ADA']);

    $csv = "kode,nama,kategori,kondisi,tanggal_beli\n"
        ."AST-OK-1,PC Baik,Komputer,baik,\n"              // baris 2: valid
        .",Tanpa Kode,Komputer,,\n"                        // baris 3: kode kosong
        ."AST-SUDAH-ADA,Duplikat DB,Komputer,,\n"          // baris 4: kode sudah ada
        ."AST-OK-1,Duplikat File,Komputer,,\n"             // baris 5: kode dobel di file
        ."AST-X-1,Kondisi Aneh,Komputer,hancur,\n"         // baris 6: kondisi tidak valid
        ."AST-X-2,,Komputer,,\n"                           // baris 7: nama kosong
        ."AST-X-3,Tanpa Kategori,,,\n"                     // baris 8: kategori kosong
        ."AST-X-4,Tanggal Rusak,Komputer,,bukan-tanggal\n" // baris 9: tanggal tidak valid
        ."\n";                                             // baris kosong diabaikan

    $hasil = (new AsetImport)->impor(fileImpor('aset.csv', $csv));

    expect($hasil['berhasil'])->toBe(1)
        ->and(collect($hasil['dilewati'])->pluck('baris')->all())->toBe([3, 4, 5, 6, 7, 8, 9])
        ->and($hasil['dilewati'][0]['alasan'])->toContain('kode')
        ->and($hasil['dilewati'][1]['alasan'])->toContain('sudah ada')
        ->and($hasil['dilewati'][3]['alasan'])->toContain('kondisi');

    expect(Asset::where('kode', 'AST-OK-1')->value('nama'))->toBe('PC Baik')
        ->and(Asset::whereIn('kode', ['AST-X-1', 'AST-X-2', 'AST-X-3', 'AST-X-4'])->count())->toBe(0);
});

it('parses Indonesian-formatted prices and dates', function () {
    $csv = "kode;nama;kategori;harga;tanggal_beli\n"
        ."AST-IMP-7;PC Kasir;Komputer;Rp 12.500.000;15/01/2024\n";

    (new AsetImport)->impor(fileImpor('aset.csv', $csv));

    $asset = Asset::where('kode', 'AST-IMP-7')->first();
    expect((float) $asset->harga)->toBe(12500000.0)
        ->and($asset->tanggal_beli->toDateString())->toBe('2024-01-15');
});

it('writes a template whose header row lists every importable column', function () {
    $path = sys_get_temp_dir().'/'.uniqid('template-', true).'.xlsx';

    AsetImport::tulisTemplate($path);

    $reader = new XlsxReader();
    $reader->open($path);
    $header = null;
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $header = $row->toArray();
            break;
        }
        break;
    }
    $reader->close();

    expect($header)->toBe(AsetImport::KOLOM);
});
