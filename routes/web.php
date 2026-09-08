<?php

use Illuminate\Support\Facades\Route;

// Root langsung mengarah ke panel Helpdesk (Filament) — tidak ada halaman publik.
Route::get('/', function () {
    return redirect('/admin');
});
