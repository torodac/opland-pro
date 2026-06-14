<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

// Obtener token
Route::post('token', [ApiController::class, 'token']);

// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::get('{slug}', [ApiController::class, 'tables']);
    Route::get('{slug}/{tabla}', [ApiController::class, 'data']);
});
