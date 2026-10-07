<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Vm\VacationmarbellaPwaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\EngController;

Route::prefix('vm')->group(function () {
    Route::post('login',                        [VacationmarbellaPwaController::class, 'login']);
    Route::post('forgot-password',              [VacationmarbellaPwaController::class, 'forgotPassword']);
    Route::post('cambiar-password',                [VacationmarbellaPwaController::class, 'cambiarPassword']);
    Route::post('perfil/firma',                    [VacationmarbellaPwaController::class, 'guardarFirma']);
    Route::get('informe',                          [VacationmarbellaPwaController::class, 'miInforme']);
    Route::get('informe/pdf',                       [VacationmarbellaPwaController::class, 'miInformePdf']);
    Route::post('informe/firmar',                  [VacationmarbellaPwaController::class, 'firmarInformeTrabajador']);
    Route::get('informe-km',                       [VacationmarbellaPwaController::class, 'miInformeKm']);
    Route::get('informe-km/pdf',                   [VacationmarbellaPwaController::class, 'miInformeKmPdf']);
    Route::post('informe-km/firmar',               [VacationmarbellaPwaController::class, 'firmarInformeKmTrabajador']);
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
    Route::post('push/subscribe', [HealthController::class, 'pushSubscribe']);
    Route::get('muscle-groups',      [HealthController::class, 'muscleGroups']);
    Route::get('exercise-log',       [HealthController::class, 'exerciseLog']);
    Route::post('exercise-log',      [HealthController::class, 'toggleExercise']);
    Route::delete('exercise-log',    [HealthController::class, 'removeExercise']);

});

// ── English flashcards PWA ────────────────────────────────────────────────────

Route::prefix('eng')->group(function () {
    Route::post('login',                    [EngController::class, 'login']);
    Route::post('logout',                   [EngController::class, 'logout']);
    Route::get('libraries',                 [EngController::class, 'libraries']);
    Route::post('libraries/{id}/share',     [EngController::class, 'shareLibrary']);
    Route::get('card',                      [EngController::class, 'card']);
    Route::get('list',                      [EngController::class, 'wordList']);
    Route::post('answer/{vocabId}',         [EngController::class, 'answer']);
    Route::get('stats',                     [EngController::class, 'stats']);
    Route::delete('progress',               [EngController::class, 'resetProgress']);
    Route::put('vocabulary/{id}',          [EngController::class, 'updateVocab']);
});
