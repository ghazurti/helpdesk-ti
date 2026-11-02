<?php

namespace App\Support;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Unit;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Import massal aset dari file .xlsx atau .csv (sinkron, tanpa antrian).
 *
 * Baris pertama dibaca sebagai header (urutan bebas, huruf besar/kecil
 * bebas). Kategori & unit dicocokkan berdasarkan nama dan dibuat otomatis
 * bila belum ada. Baris tidak valid dilewati dengan alasan, baris valid
 * tetap masuk.
 */
class AsetImport
{
    public const KOLOM = [
        'kode', 'nama', 'kategori', 'merk', 'model', 'serial_number',
        'tanggal_beli', 'harga', 'garansi_sampai', 'kondisi', 'unit',
        'masa_manfaat_bulan', 'catatan',
    ];

    private const WAJIB = ['kode', 'nama', 'kategori'];

    /**
     * @return array{berhasil: int, dilewati: list<array{baris: int, alasan: string}>}
     */
    public function impor(string $path): array
    {
        $reader = $this->bukaReader($path);
        $berhasil = 0;
        $dilewati = [];
        $kodeDiFile = [];
        $header = null;
        $nomorBaris = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $nomorBaris++;
                    $sel = $row->toArray();

                    if ($header === null) {
                        $header = $this->petakanHeader($sel);
                        $kurang = array_diff(self::WAJIB, array_keys($header));
                        if ($kurang) {
                            return ['berhasil' => 0, 'dilewati' => [[
                                'baris' => 1,
                                'alasan' => 'kolom wajib tidak ada di header: '.implode(', ', $kurang),
                            ]]];
                        }
                        continue;
                    }

                    if ($this->barisKosong($sel)) {
                        continue;
                    }

                    $data = [];
                    foreach ($header as $kolom => $indeks) {
                        $data[$kolom] = $this->bersihkan($sel[$indeks] ?? null);
                    }

                    $alasan = $this->simpanBaris($data, $kodeDiFile, $nomorBaris);
                    if ($alasan === null) {
                        $berhasil++;
                    } else {
                        $dilewati[] = ['baris' => $nomorBaris, 'alasan' => $alasan];
                    }
                }
                break; // hanya sheet pertama
            }
        } finally {
            $reader->close();
        }

        return ['berhasil' => $berhasil, 'dilewati' => $dilewati];
    }

    /**
     * Template: sheet 1 "Data Aset" hanya berisi header (siap diisi),
     * sheet 2 "Petunjuk" berisi penjelasan & contoh tiap kolom. Importer
     * hanya membaca sheet pertama, jadi contoh tidak ikut terimpor.
     */
    public static function tulisTemplate(string $path): void
    {
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Data Aset');
        $writer->addRow(Row::fromValues(self::KOLOM));

        $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');
        foreach ([
            ['Kolom', 'Wajib?', 'Keterangan', 'Contoh'],
            ['kode', 'Ya', 'Kode unik aset, tidak boleh sama', 'AST-0001'],
            ['nama', 'Ya', 'Nama perangkat', 'PC Pendaftaran'],
            ['kategori', 'Ya', 'Dibuat otomatis jika belum ada', 'Komputer'],
            ['merk', 'Tidak', '', 'Lenovo'],
            ['model', 'Tidak', '', 'ThinkCentre M70'],
            ['serial_number', 'Tidak', '', 'SN123456'],
            ['tanggal_beli', 'Tidak', 'Format 2024-01-31 atau 31/01/2024', '2024-01-31'],
            ['harga', 'Tidak', 'Rupiah, boleh pakai titik ribuan', '7.500.000'],
            ['garansi_sampai', 'Tidak', 'Format sama seperti tanggal_beli', '2027-01-31'],
            ['kondisi', 'Tidak', 'baik / rusak / perbaikan / dihapus (kosong = baik)', 'baik'],
            ['unit', 'Tidak', 'Lokasi aset, dibuat otomatis jika belum ada', 'Poli Anak'],
            ['masa_manfaat_bulan', 'Tidak', 'Angka bulan, untuk hitung penyusutan', '48'],
            ['catatan', 'Tidak', '', ''],
        ] as $baris) {
            $writer->addRow(Row::fromValues($baris));
        }

        $writer->close();
    }

    private function bukaReader(string $path): XlsxReader|CsvReader
    {
        $awal = (string) file_get_contents($path, false, null, 0, 4);

        if ($awal === "PK\x03\x04") { // .xlsx adalah arsip zip
            $opsi = new XlsxOptions();
            $opsi->SHOULD_PRESERVE_EMPTY_ROWS = true; // agar nomor baris = nomor baris Excel
            $reader = new XlsxReader($opsi);
        } else {
            $opsi = new CsvOptions();
            $opsi->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $opsi->FIELD_DELIMITER = $this->tebakPemisah($path);
            $reader = new CsvReader($opsi);
        }

        $reader->open($path);

        return $reader;
    }

    /** Excel berbahasa Indonesia menyimpan CSV dengan pemisah ";". */
    private function tebakPemisah(string $path): string
    {
        $handle = fopen($path, 'r');
        $barisPertama = (string) fgets($handle);
        fclose($handle);

        return substr_count($barisPertama, ';') > substr_count($barisPertama, ',') ? ';' : ',';
    }

    /** @return array<string, int> nama kolom => indeks sel */
    private function petakanHeader(array $sel): array
    {
        $peta = [];
        foreach ($sel as $indeks => $judul) {
            $kolom = str_replace("\u{FEFF}", '', (string) $judul); // BOM dari Excel
            $kolom = (string) preg_replace('/[\s\-]+/', '_', strtolower(trim($kolom)));
            if (in_array($kolom, self::KOLOM, true) && ! isset($peta[$kolom])) {
                $peta[$kolom] = $indeks;
            }
        }

        return $peta;
    }

    private function barisKosong(array $sel): bool
    {
        foreach ($sel as $nilai) {
            if ($this->bersihkan($nilai) !== null) {
                return false;
            }
        }

        return true;
    }

    private function bersihkan(mixed $nilai): mixed
    {
        if (is_string($nilai)) {
            $nilai = trim($nilai);

            return $nilai === '' ? null : $nilai;
        }

        return $nilai;
    }

    /**
     * Validasi & simpan satu baris. Mengembalikan null bila berhasil,
     * atau alasan (string) bila baris dilewati.
     *
     * @param  array<string, string>  $kodeDiFile  kode => nomor baris yang sudah terimpor dari file ini
     */
    private function simpanBaris(array $data, array &$kodeDiFile, int $nomorBaris): ?string
    {
        $kode = isset($data['kode']) ? (string) $data['kode'] : null;

        if ($kode === null) {
            return 'kode kosong';
        }
        if (! isset($data['nama'])) {
            return 'nama kosong';
        }
        if (! isset($data['kategori'])) {
            return 'kategori kosong';
        }
        if (isset($kodeDiFile[strtolower($kode)])) {
            return "kode {$kode} dobel di file (sudah dipakai baris {$kodeDiFile[strtolower($kode)]})";
        }
        if (Asset::where('kode', $kode)->exists()) {
            return "kode {$kode} sudah ada di database";
        }

        $kondisi = strtolower((string) ($data['kondisi'] ?? 'baik'));
        $pilihanKondisi = array_keys(AssetResource::kondisiOptions());
        if (! in_array($kondisi, $pilihanKondisi, true)) {
            return "kondisi '{$data['kondisi']}' tidak valid (pilihan: ".implode(', ', $pilihanKondisi).')';
        }

        $tanggal = [];
        foreach (['tanggal_beli', 'garansi_sampai'] as $kolom) {
            if (! isset($data[$kolom])) {
                $tanggal[$kolom] = null;
                continue;
            }
            $tanggal[$kolom] = $this->bacaTanggal($data[$kolom]);
            if ($tanggal[$kolom] === null) {
                return "{$kolom} '".$this->teks($data[$kolom])."' bukan tanggal yang valid";
            }
        }

        $harga = null;
        if (isset($data['harga'])) {
            $harga = $this->bacaHarga($data['harga']);
            if ($harga === null) {
                return "harga '".$this->teks($data['harga'])."' bukan angka yang valid";
            }
        }

        $masaManfaat = null;
        if (isset($data['masa_manfaat_bulan'])) {
            $masaManfaat = filter_var($data['masa_manfaat_bulan'], FILTER_VALIDATE_INT);
            if ($masaManfaat === false && is_float($data['masa_manfaat_bulan']) && floor($data['masa_manfaat_bulan']) == $data['masa_manfaat_bulan']) {
                $masaManfaat = (int) $data['masa_manfaat_bulan'];
            }
            if ($masaManfaat === false || $masaManfaat < 0) {
                return "masa_manfaat_bulan '".$this->teks($data['masa_manfaat_bulan'])."' harus angka bulat";
            }
        }

        DB::transaction(function () use ($data, $kode, $kondisi, $tanggal, $harga, $masaManfaat) {
            Asset::create([
                'kode' => $kode,
                'nama' => (string) $data['nama'],
                'asset_category_id' => $this->cariAtauBuat(AssetCategory::class, (string) $data['kategori'])->id,
                'merk' => $this->teksAtauNull($data['merk'] ?? null),
                'model' => $this->teksAtauNull($data['model'] ?? null),
                'serial_number' => $this->teksAtauNull($data['serial_number'] ?? null),
                'tanggal_beli' => $tanggal['tanggal_beli'],
                'harga' => $harga,
                'garansi_sampai' => $tanggal['garansi_sampai'],
                'kondisi' => $kondisi,
                'unit_id' => isset($data['unit']) ? $this->cariAtauBuat(Unit::class, (string) $data['unit'])->id : null,
                'masa_manfaat_bulan' => $masaManfaat,
                'catatan' => $this->teksAtauNull($data['catatan'] ?? null),
            ]);
        });

        $kodeDiFile[strtolower($kode)] = $nomorBaris;

        return null;
    }

    /** @param class-string<AssetCategory|Unit> $model */
    private function cariAtauBuat(string $model, string $nama): AssetCategory|Unit
    {
        return $model::whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->first()
            ?? $model::create(['nama' => $nama]);
    }

    private function bacaTanggal(mixed $nilai): ?Carbon
    {
        if ($nilai instanceof DateTimeInterface) {
            return Carbon::instance($nilai)->startOfDay();
        }

        if (is_int($nilai) || is_float($nilai)) { // nomor seri tanggal Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $nilai);
        }

        $teks = (string) $nilai;
        foreach (['Y-m-d', 'Y-n-j', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y/m/d'] as $format) {
            try {
                $tanggal = Carbon::createFromFormat('!'.$format, $teks);
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                continue; // format lain dicoba
            }
            // Cek bolak-balik menolak tanggal "melimpah" seperti 31/02/2024.
            if ($tanggal !== false && $tanggal->format($format) === $teks) {
                return $tanggal;
            }
        }

        return null;
    }

    private function bacaHarga(mixed $nilai): ?float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        $teks = (string) preg_replace('/^rp\.?\s*/i', '', trim((string) $nilai));
        $teks = str_replace(' ', '', $teks);

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $teks)) { // 12.500.000 atau 12.500.000,50
            return (float) str_replace(',', '.', str_replace('.', '', $teks));
        }
        if (preg_match('/^\d+,\d+$/', $teks)) { // 1500,50
            return (float) str_replace(',', '.', $teks);
        }

        return is_numeric($teks) ? (float) $teks : null;
    }

    private function teks(mixed $nilai): string
    {
        return $nilai instanceof DateTimeInterface ? $nilai->format('Y-m-d') : (string) $nilai;
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        return $nilai === null ? null : $this->teks($nilai);
    }
}
