<?php

use App\Models\User;

it('redirects guests away from the panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets a seeded user reach the dashboard', function () {
    $this->seed(\Database\Seeders\AdminUserSeeder::class);
    $user = User::where('email', 'ghazurillahi2@gmail.com')->first();
    $this->actingAs($user)->get('/admin')->assertOk();
});
