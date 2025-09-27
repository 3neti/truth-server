<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('precinct')
    ->middleware(['web'])->group(function () {
        Route::get('tally', function () {
            return Inertia::render('Tally');
        });
    });

