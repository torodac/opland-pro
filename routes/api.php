<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vm\VacationmarbellaPwaController;
use App\Http\Controllers\HealthController;

Route::prefix('vm')->group(function () {
    Route::post('login',                        [VacationmarbellaPwaController::class, 'login']);
    Route::post('cambiar-password',                [VacationmarbellaPwaController::class, 'cambiarPassword']);
    Route::post('logout',                       [VacationmarbellaPwaController::class, 'logout']);
    Route::get('me',                            [VacationmarbellaPwaController::class, 'me']);
    Route::get('duraciones',                    [VacationmarbellaPwaController::class, 'duraciones']);
    Route::get('usuarios',                      [VacationmarbellaPwaController::class, 'usuarios']);
    Route::get('tareas/hoy',                    [VacationmarbellaPwaController::class, 'tareasHoy']);
    Route::post('tareas/{tipo}/{id}/imputar',    [VacationmarbellaPwaController::class, 'imputarTiempo']);
Route::patch('tareas/{tipo}/{id}/imputaciones/{imputacionId}', [VacationmarbellaPwaController::class, 'editarImputacion']);
    Route::post('tareas/{tipo}/{id}/foto',      [VacationmarbellaPwaController::class, 'subirFoto']);
    Route::post('tareas/{tipo}/{id}/reportar', [VacationmarbellaPwaController::class, 'reportarTarea']);
    Route::delete('fotos/{id}',                 [VacationmarbellaPwaController::class, 'borrarFoto']);
    Route::get('fichaje/hoy',                   [VacationmarbellaPwaController::class, 'fichajeHoy']);
    Route::post('fichaje/entrada',              [VacationmarbellaPwaController::class, 'fichajeEntrada']);
    Route::post('fichaje/salida',               [VacationmarbellaPwaController::class, 'fichajeSalida']);
    Route::post('fichaje/pausa',               [VacationmarbellaPwaController::class, 'fichajePausa']);
    Route::patch('fichaje/editar',             [VacationmarbellaPwaController::class, 'fichajeEditar']);
    Route::post('fichaje/crear',              [VacationmarbellaPwaController::class, 'fichajeCrear']);
    Route::get('vapid-public-key',              [VacationmarbellaPwaController::class, 'vapidPublicKey']);
    Route::post('push/subscribe',               [VacationmarbellaPwaController::class, 'pushSubscribe']);
    Route::post('push/unsubscribe',             [VacationmarbellaPwaController::class, 'pushUnsubscribe']);
    Route::post('tareas/crear',                  [VacationmarbellaPwaController::class, 'crearTarea']);
    Route::get('propiedades',                    [VacationmarbellaPwaController::class, 'propiedades']);
    Route::get('agenda',                         [VacationmarbellaPwaController::class, 'agendaSemana']);
    Route::get('horario-equipo',                 [VacationmarbellaPwaController::class, 'horarioEquipo']);
});


// Obtener token
Route::post('token', [ApiController::class, 'token']);

// Rutas protegidas por Sanctum (header Bearer o ?api_token=...)
Route::prefix('data')->middleware('auth.api')->group(function () {
    Route::get('{slug}', [ApiController::class, 'tables']);
    Route::get('{slug}/{tabla}', [ApiController::class, 'data']);
});


// ── Health PWA ────────────────────────────────────────────────────────────────

Route::prefix('health')->group(function () {
    Route::post('login',            [HealthController::class, 'login']);
    Route::post('logout',           [HealthController::class, 'logout']);
    Route::get('log/{date?}',       [HealthController::class, 'getLog']);
    Route::put('log/{date}',        [HealthController::class, 'upsertLog']);
    Route::get('weight/history',    [HealthController::class, 'weightHistory']);
});
