<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ListadoController;
use App\Http\Controllers\FichaController;
use App\Http\Controllers\ExcelController;

// Autenticación
Route::get('login', [LoginController::class, 'showLogin'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Cambio de contraseña obligatorio (primer acceso)
Route::middleware('auth')->group(function () {
    Route::get('change-password', [App\Http\Controllers\ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('change-password', [App\Http\Controllers\ChangePasswordController::class, 'update'])->name('password.change.update');
});

// Recuperación de contraseña
Route::get('forgot-password', [App\Http\Controllers\PasswordResetController::class, 'showRequest'])->name('password.request');
Route::post('forgot-password', [App\Http\Controllers\PasswordResetController::class, 'sendLink'])->name('password.send-link');
Route::get('reset-password', [App\Http\Controllers\PasswordResetController::class, 'showReset'])->name('password.reset-form');
Route::post('reset-password', [App\Http\Controllers\PasswordResetController::class, 'reset'])->name('password.reset');

// Rutas protegidas
Route::middleware('auth')->group(function () {

    // Panel de administración
    Route::prefix('config')->name('config.')->group(function () {
        // Proyectos: show reemplazado por TableController@index en /config/projects/{project}
        Route::resource('projects', App\Http\Controllers\Admin\ProjectController::class)
            ->except(['show']);
        Route::get('projects/{project}',
            [App\Http\Controllers\Admin\TableController::class, 'index'])
            ->name('projects.tables.index')->scopeBindings();

        // Tablas: CRUD sin index (el index vive en projects/{project})
        Route::patch('projects/{project}/tables/reorder',
            [App\Http\Controllers\Admin\TableController::class, 'reorder'])
            ->name('projects.tables.reorder')->scopeBindings();
        Route::resource('projects.tables', App\Http\Controllers\Admin\TableController::class)
            ->scoped(['table' => 'name'])->except(['index']);
        Route::patch('projects/{project}/tables/{table}/patch',
            [App\Http\Controllers\Admin\TableController::class, 'patch'])
            ->name('projects.tables.patch')->scopeBindings();
        Route::patch('projects/{project}/tables/{table}/tabs',
            [App\Http\Controllers\Admin\TableController::class, 'updateTabs'])
            ->name('projects.tables.tabs')->scopeBindings();

        // Excel: crear tabla desde Excel (debe ir ANTES de projects/{project}/{table})
        Route::get('projects/{project}/import-excel', [ExcelController::class, 'createFromExcelForm'])
            ->name('projects.import-excel.form');
        Route::post('projects/{project}/import-excel/preview', [ExcelController::class, 'createFromExcelPreview'])
            ->name('projects.import-excel.preview');
        Route::post('projects/{project}/import-excel/confirm', [ExcelController::class, 'createFromExcel'])
            ->name('projects.import-excel.confirm');

        // Campos: index en /config/projects/{project}/{table}, resto en /tables/{table}/fields/...
        Route::get('projects/{project}/{table}',
            [App\Http\Controllers\Admin\FieldController::class, 'index'])
            ->name('projects.tables.fields.index')->scopeBindings();
        Route::patch('projects/{project}/tables/{table}/fields/reorder',
            [App\Http\Controllers\Admin\FieldController::class, 'reorder'])
            ->name('projects.tables.fields.reorder')->scopeBindings();
        Route::patch('projects/{project}/tables/{table}/fields/{field}/patch',
            [App\Http\Controllers\Admin\FieldController::class, 'patch'])
            ->name('projects.tables.fields.patch')->scopeBindings();
        Route::resource('projects.tables.fields', App\Http\Controllers\Admin\FieldController::class)
            ->scoped(['table' => 'name', 'field' => 'name'])->except(['index']);

        Route::post('users/stop-impersonating', [App\Http\Controllers\Admin\UserController::class, 'stopImpersonating'])->name('users.stop-impersonating');
        Route::post('users/{user}/impersonate', [App\Http\Controllers\Admin\UserController::class, 'impersonate'])->name('users.impersonate');
        Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    });

    // Perfil de usuario
    Route::get('perfil', [App\Http\Controllers\PerfilController::class, 'show'])->name('perfil');
    Route::patch('perfil', [App\Http\Controllers\PerfilController::class, 'update'])->name('perfil.update');

    // Página de inicio → lista de proyectos
    Route::get('/', [ProjectController::class, 'index'])->name('proyectos');

    // Rutas dentro de un proyecto (con verificación de acceso)
    Route::prefix('{project:slug}')->middleware('project.access')->group(function () {

        Route::get('{table}', [ListadoController::class, 'index'])->name('listado');

        // Excel export/import sobre tabla existente
        Route::get('{table}/export', [ExcelController::class, 'export'])->name('excel.export');
        Route::get('{table}/import', [ExcelController::class, 'importForm'])->name('excel.import-form');
        Route::post('{table}/import/preview', [ExcelController::class, 'importPreview'])->name('excel.import-preview');
        Route::post('{table}/import/confirm', [ExcelController::class, 'import'])->name('excel.import');

        Route::get('{table}/nuevo', [FichaController::class, 'create'])->name('ficha.create');
        Route::post('{table}', [FichaController::class, 'store'])->name('ficha.store');

        Route::get('{table}/{id}', [FichaController::class, 'show'])->name('ficha');
        Route::put('{table}/{id}', [FichaController::class, 'update'])->name('ficha.update');
        Route::patch('{table}/{id}/campo', [FichaController::class, 'updateField'])->name('ficha.update-field');
        Route::patch('{table}/{id}/archivar', [FichaController::class, 'archive'])->name('ficha.archive');
        Route::patch('{table}/{id}/bloquear', [FichaController::class, 'block'])->name('ficha.block');
        Route::delete('{table}/{id}', [FichaController::class, 'destroy'])->name('ficha.destroy');
    });

});
