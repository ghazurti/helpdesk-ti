<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Guarantee the `admin` role exists even when this seeder is run
        // directly (e.g. in tests) without going through DatabaseSeeder's
        // ordering, which also calls RoleSeeder before this one.
        $this->callOnce(RoleSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'admin@helpdesk.test'],
            ['name' => 'Administrator', 'password' => Hash::make(env('ADMIN_PASSWORD', 'password'))],
        );

        $user->assignRole('admin');
    }
}
