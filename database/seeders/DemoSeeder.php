<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds realistic sample data for local demos/walkthroughs: units, work
 * categories, asset categories, assets, and tickets spread across the
 * current year with mixed statuses.
 *
 * Intentionally NOT called from DatabaseSeeder's default run — invoke it
 * explicitly:
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Roles must exist before we can assign staf_it below.
        $this->callOnce(RoleSeeder::class);

        // --- Units ---------------------------------------------------
        $unitNames = ['IGD', 'Poli', 'Farmasi', 'Laboratorium', 'Radiologi', 'Administrasi'];
        $units = collect($unitNames)->mapWithKeys(function (string $nama) {
            return [$nama => Unit::firstOrCreate(
                ['nama' => $nama],
                ['keterangan' => "Unit {$nama}"],
            )];
        });

        // --- Work categories ------------------------------------------
        $workCategoryNames = ['Hardware', 'Jaringan', 'Software', 'SIMRS', 'Printer'];
        $workCategories = collect($workCategoryNames)->mapWithKeys(function (string $nama) {
            return [$nama => WorkCategory::firstOrCreate(['nama' => $nama])];
        });

        // --- Asset categories -------------------------------------------
        $assetCategoryNames = ['Komputer', 'Printer', 'Switch', 'UPS'];
        $assetCategories = collect($assetCategoryNames)->mapWithKeys(function (string $nama) {
            return [$nama => AssetCategory::firstOrCreate(['nama' => $nama])];
        });

        // --- Petugas IT (staf_it users) --------------------------------
        $petugasNames = [
            'Budi Santoso' => 'budi.santoso@helpdesk.local',
            'Siti Rahma' => 'siti.rahma@helpdesk.local',
        ];
        $petugas = collect($petugasNames)->map(function (string $email, string $name) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            if (! $user->hasRole('staf_it')) {
                $user->assignRole('staf_it');
            }

            return $user;
        })->values();

        // --- Assets (~15 across units/categories) -----------------------
        $assetKondisi = ['baik', 'baik', 'baik', 'rusak', 'perbaikan'];
        $unitList = $units->values();
        $assetCategoryList = $assetCategories->values();

        $assets = collect();
        for ($i = 1; $i <= 15; $i++) {
            $unit = $unitList[($i - 1) % $unitList->count()];
            $category = $assetCategoryList[($i - 1) % $assetCategoryList->count()];

            $assets->push(Asset::firstOrCreate(
                ['kode' => sprintf('AST-DEMO-%03d', $i)],
                [
                    'nama' => $category->nama.' '.$unit->nama,
                    'asset_category_id' => $category->id,
                    'merk' => fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Epson', 'Canon', 'Cisco', 'APC']),
                    'model' => fake()->bothify('??-####'),
                    'serial_number' => strtoupper(fake()->bothify('SN#########')),
                    'tanggal_beli' => fake()->dateTimeBetween('-4 years', '-3 months'),
                    'harga' => fake()->randomFloat(2, 1_500_000, 45_000_000),
                    'garansi_sampai' => fake()->dateTimeBetween('now', '+2 years'),
                    'kondisi' => $assetKondisi[($i - 1) % count($assetKondisi)],
                    'unit_id' => $unit->id,
                    'masa_manfaat_bulan' => fake()->numberBetween(24, 96),
                    'catatan' => null,
                ],
            ));
        }

        // --- Tickets (~30 across the current year, mixed statuses) ------
        $statuses = ['baru', 'proses', 'pending', 'selesai', 'selesai'];
        $prioritas = ['rendah', 'sedang', 'tinggi'];
        $judulByCategory = [
            'Hardware' => ['Komputer tidak menyala', 'Monitor bergaris', 'Mouse tidak berfungsi', 'Keyboard error'],
            'Jaringan' => ['Koneksi internet putus', 'WiFi lemah di ruangan', 'IP conflict', 'Kabel LAN putus'],
            'Software' => ['Aplikasi sering crash', 'Instal ulang software', 'Update sistem operasi', 'Lisensi kadaluarsa'],
            'SIMRS' => ['SIMRS tidak bisa login', 'Data pasien tidak tersimpan', 'SIMRS lambat', 'Antrian SIMRS error'],
            'Printer' => ['Printer macet', 'Tinta printer habis', 'Printer tidak terdeteksi', 'Hasil cetak buram'],
        ];

        $currentYear = now()->year;
        $workCategoryList = $workCategories->values();

        for ($i = 1; $i <= 30; $i++) {
            $category = $workCategoryList[($i - 1) % $workCategoryList->count()];
            $unit = $unitList[($i - 1) % $unitList->count()];
            $status = $statuses[($i - 1) % count($statuses)];
            $officer = $petugas[($i - 1) % $petugas->count()];
            $judulOptions = $judulByCategory[$category->nama] ?? ['Kendala IT'];
            $judul = $judulOptions[($i - 1) % count($judulOptions)];

            // Spread tanggal across the current year (Jan 1 .. today).
            $dayOfYear = (int) floor(($i - 1) * (now()->dayOfYear - 1) / 29);
            $tanggal = now()->copy()->startOfYear()->addDays($dayOfYear);

            // Only attach an asset to some tickets, and only from the same unit.
            $assetForTicket = null;
            if ($i % 2 === 0) {
                $assetForTicket = $assets->firstWhere('unit_id', $unit->id);
            }

            $ticket = Ticket::create([
                'user_id' => $officer->id,
                'unit_id' => $unit->id,
                'work_category_id' => $category->id,
                'asset_id' => $assetForTicket?->id,
                'judul' => $judul,
                'deskripsi' => "Laporan kendala: {$judul} di unit {$unit->nama}.",
                'tindakan' => $status === 'selesai' ? 'Sudah ditangani dan diperbaiki oleh petugas IT.' : null,
                'prioritas' => $prioritas[($i - 1) % count($prioritas)],
                'status' => 'baru',
                'tanggal' => $tanggal->toDateString(),
            ]);

            if ($status !== 'baru') {
                $ticket->status = $status;
                $ticket->save();
            }
        }
    }
}
