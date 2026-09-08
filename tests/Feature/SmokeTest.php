<?php

it('boots and reaches the database', function () {
    expect(\Illuminate\Support\Facades\DB::connection()->getDatabaseName())
        ->toBe('helpdesk_it_test');
});

it('redirects the root to the admin panel', function () {
    $this->get('/')->assertRedirect('/admin');
});
