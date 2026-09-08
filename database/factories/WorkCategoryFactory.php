<?php

namespace Database\Factories;

use App\Models\WorkCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkCategory>
 */
class WorkCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
        ];
    }
}
