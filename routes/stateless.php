<?php

use App\Http\Controllers\AuthController;

// Stateless API routes - no session, no CSRF
Route::group(['prefix' => 'api', 'middleware' => ['throttle:api']], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
}); 