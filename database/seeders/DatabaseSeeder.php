<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(AdminUserSeeder::class);

        // DemoSeeder (units/categories/assets/tickets sample data for local
        // walkthroughs) is intentionally NOT called here — it never runs as
        // part of the default `--seed` flow (including in production). Seed
        // it explicitly and only in local development:
        //
        //   php artisan db:seed --class=DemoSeeder
    }
}
