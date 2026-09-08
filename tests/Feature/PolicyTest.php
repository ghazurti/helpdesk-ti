<?php

use App\Models\{User, Asset, Ticket, Unit};

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('lets admin delete but forbids staf_it', function () {
    $admin = User::factory()->admin()->create();
    $staf  = User::factory()->stafIt()->create();
    $asset = Asset::factory()->create();

    expect($admin->can('delete', $asset))->toBeTrue()
        ->and($staf->can('delete', $asset))->toBeFalse();
});

it('hides master data from staf_it', function () {
    $staf = User::factory()->stafIt()->create();
    expect($staf->can('viewAny', Unit::class))->toBeFalse();
});

it('allows staf_it to create tickets', function () {
    $staf = User::factory()->stafIt()->create();
    expect($staf->can('create', Ticket::class))->toBeTrue();
});
