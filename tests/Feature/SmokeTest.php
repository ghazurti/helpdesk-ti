<?php

it('boots and reaches the database', function () {
    expect(\Illuminate\Support\Facades\DB::connection()->getDatabaseName())
        ->toBe('helpdesk_it_test');
});

it('serves the welcome page', function () {
    $this->get('/')->assertOk();
});
