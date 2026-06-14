<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// Obtener token
Route::post('token', [ApiController::class, 'token']);

// Rutas protegidas por Sanctum (header Bearer o ?api_token=...)
Route::middleware('auth.api')->group(function () {
    Route::get('{slug}', [ApiController::class, 'tables']);
    Route::get('{slug}/{tabla}', [ApiController::class, 'data']);
});
