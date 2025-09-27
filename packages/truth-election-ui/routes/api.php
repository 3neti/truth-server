<?php

use TruthElection\Support\ElectionStoreInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('election-return', function (Request $request) {
    $store = app(ElectionStoreInterface::class);
    $payload = $request->query('payload', 'minimal');

    return response()->json(
        $store->getElectionReturn()?->transformFor($payload)
    );
})->name('election-return');
