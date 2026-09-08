<?php

use App\Models\{Asset, Unit, User};

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('moves an asset and records history', function () {
    $igd = Unit::factory()->create(['nama' => 'IGD']);
    $poli = Unit::factory()->create(['nama' => 'Poli']);
    $user = User::factory()->stafIt()->create();
    $asset = Asset::factory()->create(['unit_id' => $igd->id]);

    $move = $asset->pindahKe($poli, 'Relokasi', $user);

    expect($asset->fresh()->unit_id)->toBe($poli->id)
        ->and($move->from_unit_id)->toBe($igd->id)
        ->and($move->to_unit_id)->toBe($poli->id)
        ->and($asset->movements()->count())->toBe(1);
});
