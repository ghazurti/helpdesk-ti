<?php

namespace Database\Factories;

use App\Models\AssetMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMovement>
 */
class AssetMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => \App\Models\Asset::factory(),
            'from_unit_id' => \App\Models\Unit::factory(),
            'to_unit_id' => \App\Models\Unit::factory(),
            'tanggal' => $this->faker->date(),
            'keterangan' => $this->faker->sentence(),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
