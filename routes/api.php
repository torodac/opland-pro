<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VacationmarbellaPwaController;

Route::prefix('vm')->group(function () {
    Route::post('login',                        [VacationmarbellaPwaController::class, 'login']);
    Route::post('logout',                       [VacationmarbellaPwaController::class, 'logout']);
    Route::get('duraciones',                    [VacationmarbellaPwaController::class, 'duraciones']);
    Route::get('tareas/hoy',                    [VacationmarbellaPwaController::class, 'tareasHoy']);
    Route::post('tareas/{tipo}/{id}/fichar',    [VacationmarbellaPwaController::class, 'fichar']);
    Route::post('tareas/{tipo}/{id}/completar', [VacationmarbellaPwaController::class, 'completarTarea']);
    Route::post('tareas/{tipo}/{id}/foto',      [VacationmarbellaPwaController::class, 'subirFoto']);
    Route::delete('fotos/{id}',                 [VacationmarbellaPwaController::class, 'borrarFoto']);
    Route::get('fichaje/hoy',                   [VacationmarbellaPwaController::class, 'fichajeHoy']);
    Route::post('fichaje/entrada',              [VacationmarbellaPwaController::class, 'fichajeEntrada']);
    Route::post('fichaje/salida',               [VacationmarbellaPwaController::class, 'fichajeSalida']);
    Route::post('fichaje/pausa',                [VacationmarbellaPwaController::class, 'fichajePausa']);
});


// Obtener token
Route::post('token', [ApiController::class, 'token']);

// Rutas protegidas por Sanctum (header Bearer o ?api_token=...)
Route::middleware('auth.api')->group(function () {
    Route::get('{slug}', [ApiController::class, 'tables']);
    Route::get('{slug}/{tabla}', [ApiController::class, 'data']);
});
