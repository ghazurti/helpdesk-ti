<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        return [
            'kode' => 'AST-'.++$counter,
            'nama' => $this->faker->words(3, true),
            'asset_category_id' => \App\Models\AssetCategory::factory(),
            'merk' => $this->faker->company(),
            'model' => $this->faker->word(),
            'serial_number' => $this->faker->uuid(),
            'tanggal_beli' => $this->faker->dateTime(),
            'harga' => $this->faker->randomFloat(2, 1000000, 100000000),
            'garansi_sampai' => $this->faker->dateTimeBetween('+1 year', '+5 years'),
            'kondisi' => 'baik',
            'unit_id' => \App\Models\Unit::factory(),
            'masa_manfaat_bulan' => $this->faker->numberBetween(12, 120),
            'catatan' => $this->faker->sentence(),
        ];
    }
}
