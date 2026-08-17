<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'game');

Route::prefix('api/game')->group(function () {
    Route::get('/', [GameController::class, 'show']);
    Route::post('/sip', [GameController::class, 'sip']);
    Route::post('/upgrade', [GameController::class, 'upgrade']);
    Route::post('/action', [GameController::class, 'action']);
    Route::post('/monetize', [GameController::class, 'monetize']);
    Route::post('/reset', [GameController::class, 'reset']);
});
