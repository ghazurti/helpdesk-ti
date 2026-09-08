<?php

use App\Models\User;

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('grants panel access to both roles', function () {
    $admin = User::factory()->admin()->create();
    $staf = User::factory()->stafIt()->create();

    expect($admin->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue()
        ->and($staf->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue();
});

it('denies panel access to a roleless user', function () {
    $user = User::factory()->create();
    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();
});
