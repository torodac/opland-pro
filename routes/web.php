<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ListadoController;
use App\Http\Controllers\FichaController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\Vm\CalendarioReservasController;
use App\Http\Controllers\Vm\PlanificadorLimpiezaController;
use App\Http\Controllers\Vm\InformeImputacionesController;
use App\Http\Controllers\Vm\HorarioController;
use App\Http\Controllers\Vm\TareaController;
use App\Http\Controllers\Vm\VmUsuarioController;
use App\Http\Controllers\Vm\DashboardController;
use App\Http\Controllers\Vm\PropiedadesController;
use App\Http\Controllers\Vm\PygController;
use App\Http\Controllers\Vm\LiquidacionController;
use App\Http\Controllers\Vm\KmController;
use App\Http\Controllers\Vm\NovacionesController;

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

        // ── Gestión de usuarios: solo role admin global ──
                // -- Stop impersonating: fuera de role.admin (activo es el impersonado) --
        Route::post('users/stop-impersonating', [App\Http\Controllers\Admin\UserController::class, 'stopImpersonating'])->name('users.stop-impersonating');

        Route::middleware('role.admin')->group(function () {
                        Route::post('users/{user}/impersonate', [App\Http\Controllers\Admin\UserController::class, 'impersonate'])->name('users.impersonate');
            Route::resource('users', App\Http\Controllers\Admin\UserController::class);
        });

        // ── Administrar proyecto: role admin o admin_{slug} ──
        Route::middleware('role.project-admin')->group(function () {
            // Proyectos: show reemplazado por TableController@index en /config/projects/{project}
            Route::resource('projects', App\Http\Controllers\Admin\ProjectController::class)
                ->except(['show']);
            Route::get('projects/{project}',
                [App\Http\Controllers\Admin\TableController::class, 'index'])
                ->name('projects.tables.index')->scopeBindings();

            // Tablas
            Route::patch('projects/{project}/tables/reorder',
                [App\Http\Controllers\Admin\TableController::class, 'reorder'])
                ->name('projects.tables.reorder')->scopeBindings();
            Route::patch('projects/{project}/modulo-order',
                [App\Http\Controllers\Admin\TableController::class, 'reorderModulos'])
                ->name('projects.tables.modulo-order')->scopeBindings();
            Route::delete('projects/{project}/modulo/{modulo}',
                [App\Http\Controllers\Admin\TableController::class, 'deleteModulo'])
                ->name('projects.tables.delete-modulo')->scopeBindings();
            Route::patch('projects/{project}/modulo/{modulo}/rename',
                [App\Http\Controllers\Admin\TableController::class, 'renameModulo'])
                ->name('projects.tables.rename-modulo')->scopeBindings();
            Route::patch('projects/{project}/tables/{table}/set-modulo',
                [App\Http\Controllers\Admin\TableController::class, 'setModulo'])
                ->name('projects.tables.set-modulo')->scopeBindings();
            Route::resource('projects.tables', App\Http\Controllers\Admin\TableController::class)
                ->scoped(['table' => 'name'])->except(['index']);
            Route::patch('projects/{project}/tables/{table}/patch',
                [App\Http\Controllers\Admin\TableController::class, 'patch'])
                ->name('projects.tables.patch')->scopeBindings();
            Route::post('projects/{project}/tables/{table}/clone',
                [App\Http\Controllers\Admin\FieldController::class, 'cloneTable'])
                ->name('projects.tables.clone')->scopeBindings()->middleware('role.admin');
            Route::patch('projects/{project}/tables/{table}/tabs',
                [App\Http\Controllers\Admin\TableController::class, 'updateTabs'])
                ->name('projects.tables.tabs')->scopeBindings();

            // Excel: crear tabla desde Excel
            Route::get('projects/{project}/import-excel', [ExcelController::class, 'createFromExcelForm'])
                ->name('projects.import-excel.form');
            Route::post('projects/{project}/import-excel/preview', [ExcelController::class, 'createFromExcelPreview'])
                ->name('projects.import-excel.preview');
            Route::post('projects/{project}/import-excel/validate', [ExcelController::class, 'createFromExcelValidate'])
                ->name('projects.import-excel.validate');
            Route::post('projects/{project}/import-excel/confirm', [ExcelController::class, 'createFromExcel'])
                ->name('projects.import-excel.confirm');

            // Campos
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
        });
    });

    // Perfil de usuario
    Route::get('perfil', [App\Http\Controllers\PerfilController::class, 'show'])->name('perfil');
    Route::patch('perfil', [App\Http\Controllers\PerfilController::class, 'update'])->name('perfil.update');

    // Página de inicio → lista de proyectos
    Route::get('/', [ProjectController::class, 'index'])->name('proyectos');

    // Rutas dentro de un proyecto (con verificación de acceso)
    Route::prefix('{project:slug}')->middleware('project.access')->group(function () {

        Route::get('calendario-reservas', [CalendarioReservasController::class, 'index'])->name('calendario-reservas');

        Route::get('informe-imputaciones', [InformeImputacionesController::class, 'index'])->name('informe-imputaciones');
        Route::get('informe-imputaciones/pdf', [InformeImputacionesController::class, 'pdf'])->name('informe-imputaciones.pdf');
        Route::get('informe-imputaciones/pdf-todos', [InformeImputacionesController::class, 'pdfTodos'])->name('informe-imputaciones.pdf-todos');

        Route::get('km',                   [KmController::class, 'index'])->name('km');
        Route::get('km/informe',           [KmController::class, 'informe'])->name('km.informe');
        Route::get('km/informe/pdf',       [KmController::class, 'informePdf'])->name('km.informe.pdf');
        Route::get('km/informe/pdf-todos', [KmController::class, 'informePdfTodos'])->name('km.informe.pdf-todos');

        Route::get('novaciones',                  [NovacionesController::class, 'index'])->name('novaciones');
        Route::get('novaciones/importes',         [NovacionesController::class, 'importes'])->name('novaciones.importes');
        Route::post('novaciones/toggle-importe',  [NovacionesController::class, 'toggleImporte'])->name('novaciones.toggle');
        Route::post('novaciones/update-importe',  [NovacionesController::class, 'updateImporte'])->name('novaciones.update-importe');
        Route::post('novaciones/comision-bancos', [NovacionesController::class, 'saveComisionBancos'])->name('novaciones.comision-bancos');
        Route::post('novaciones/guardar',         [NovacionesController::class, 'guardar'])->name('novaciones.guardar');
        Route::get('novaciones/pdf',              [NovacionesController::class, 'pdf'])->name('novaciones.pdf');
        Route::get('novaciones/gastos',           [NovacionesController::class, 'gastos'])->name('novaciones.gastos');
        Route::post('novaciones/gastos',          [NovacionesController::class, 'saveGastos'])->name('novaciones.gastos.save');
        Route::post('novaciones/update-tarea',    [NovacionesController::class, 'updateTarea'])->name('novaciones.update-tarea');
        Route::post('novaciones/create-tarea',    [NovacionesController::class, 'createTarea'])->name('novaciones.create-tarea');

        Route::get('horario/planificar', [HorarioController::class, 'index'])->name('horario');
        Route::get('horario', [HorarioController::class, 'listado'])->name('horario.listado');
        Route::post('horario', [HorarioController::class, 'store'])->name('horario.store');
        Route::delete('horario', [HorarioController::class, 'destroy'])->name('horario.destroy');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('vm.dashboard');
        Route::post('dashboard/validar-conciliacion', [DashboardController::class, 'validarConciliacion'])->name('vm.dashboard.validar');
        Route::post('dashboard/validar-fichaje', [DashboardController::class, 'validarFichaje'])->name('vm.dashboard.validar-fichaje');
        Route::get('dashboard/fichaje-hoy',      [DashboardController::class, 'fichajeHoy'])->name('vm.dashboard.fichaje-hoy');
        Route::post('dashboard/fichaje-entrada', [DashboardController::class, 'fichajeEntrada'])->name('vm.dashboard.fichaje-entrada');
        Route::post('dashboard/fichaje-pausa',   [DashboardController::class, 'fichajePausa'])->name('vm.dashboard.fichaje-pausa');
        Route::post('dashboard/fichaje-salida',  [DashboardController::class, 'fichajeSalida'])->name('vm.dashboard.fichaje-salida');

        Route::get('vm_usuarios/{id}', fn(\App\Models\Project $project, $id) => redirect()->route('vm.usuario', [$project->slug, $id]))->where('project', 'vm');
        Route::middleware('table.access:usuarios')->group(function () {
            Route::get('usuarios/{id}', fn(\App\Models\Project $project, int $id) => app(\App\Http\Controllers\FichaController::class)->show($project, 'usuarios', $id))->where(['project' => 'vm', 'id' => '[0-9]+'])->name('vm.usuario');
            Route::get('usuarios_form/{id}', [VmUsuarioController::class, 'show'])->where(['project' => 'vm', 'id' => '[0-9]+'])->name('vm.usuario_form');
            Route::get('usuarios/{id}/ficha', fn(\App\Models\Project $project, $id) => redirect()->route('ficha', [$project->slug, 'vm_usuarios', $id]))->where('project', 'vm')->name('vm.usuario.ficha');
            Route::patch('vm_usuarios/{id}/ficha', [VmUsuarioController::class, 'update'])->where('project', 'vm')->name('vm.usuario.update');
            Route::post('vm_usuarios/{id}/contratos', [VmUsuarioController::class, 'storeContrato'])->where('project', 'vm')->name('vm.contrato.store');
            Route::patch('vm_usuarios/{id}/contratos/{contratoId}', [VmUsuarioController::class, 'updateContrato'])->where('project', 'vm')->name('vm.contrato.update');
            Route::post('vm_usuarios/{id}/bonus', [VmUsuarioController::class, 'storeBonus'])->where('project', 'vm')->name('vm.bonus.store');
            Route::patch('vm_usuarios/{id}/bonus/{bonusId}', [VmUsuarioController::class, 'updateBonus'])->where('project', 'vm')->name('vm.bonus.update');
            Route::delete('vm_usuarios/{id}/bonus/{bonusId}', [VmUsuarioController::class, 'deleteBonus'])->where('project', 'vm')->name('vm.bonus.delete');
            Route::post('vm_usuarios/{id}/ausencias', [VmUsuarioController::class, 'storeAusencia'])->where('project', 'vm')->name('vm.ausencia.store');
            Route::patch('vm_usuarios/{id}/ausencias/{ausId}', [VmUsuarioController::class, 'updateAusencia'])->where('project', 'vm')->name('vm.ausencia.update');
            Route::delete('vm_usuarios/{id}/ausencias/{ausId}', [VmUsuarioController::class, 'deleteAusencia'])->where('project', 'vm')->name('vm.ausencia.delete');
            Route::post('vm_usuarios/{id}/nominas', [VmUsuarioController::class, 'storeNomina'])->where('project', 'vm')->name('vm.nomina.store');
        });

        Route::get('tareas_{tipo}_list', [\App\Http\Controllers\Vm\TareaController::class, 'index'])->name('vm.tarea.list')->where(['tipo' => 'limpieza|mantenimiento|piscina']);
        Route::post('tareas_{tipo}_list', [\App\Http\Controllers\Vm\TareaController::class, 'store'])->name('vm.tarea.store')->where(['tipo' => 'limpieza|mantenimiento|piscina']);
        Route::get('tareas_{tipo}_form/{id}', [\App\Http\Controllers\Vm\TareaController::class, 'show'])

            ->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+'])
            ->middleware('table.access:tareas_{tipo}')
            ->name('vm.tarea');
        Route::put('tareas_{tipo}_form/{id}', [\App\Http\Controllers\Vm\TareaController::class, 'update'])
            ->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+'])
            ->name('vm.tarea.update');

        Route::middleware('table.access:fichaje')->group(function () {
            Route::get('fichajes/{id}',      [\App\Http\Controllers\Vm\FichajeController::class, 'show'])->where(['id' => '[0-9]+'])->name('vm.fichaje');
            Route::get('fichaje_form/{id}',  [\App\Http\Controllers\Vm\FichajeController::class, 'show'])->where(['id' => '[0-9]+'])->name('vm.fichaje_form');
            Route::patch('fichajes/{id}',    [\App\Http\Controllers\Vm\FichajeController::class, 'update'])->where(['id' => '[0-9]+'])->name('vm.fichaje.update');
        });
        Route::get('tareas_limpieza/planificar', [PlanificadorLimpiezaController::class, 'index'])->name('planificador-limpieza');
        Route::patch('tareas_{tipo}_form/{id}/asignados', [\App\Http\Controllers\Vm\TareaController::class, 'updateAsignados'])
            ->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+'])
            ->name('vm.tarea.asignados');
        Route::post('tareas_{tipo}_form/{id}/imputaciones', [\App\Http\Controllers\Vm\TareaController::class, 'storeImputacion'])
            ->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+'])
            ->name('vm.tarea.imputacion.store');
        Route::patch('tareas_{tipo}_form/{id}/imputaciones/{impId}', [\App\Http\Controllers\Vm\TareaController::class, 'updateImputacion'])
            ->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+', 'impId' => '[0-9]+'])
            ->name('vm.tarea.imputacion.update');
        Route::delete('tareas_{tipo}_form/{id}/imputaciones/{impId}', [\App\Http\Controllers\Vm\TareaController::class, 'deleteImputacion'])
            ->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+', 'impId' => '[0-9]+'])
            ->name('vm.tarea.imputacion.delete');
        Route::post('tareas_{tipo}_form/{id}/fotos', [\App\Http\Controllers\Vm\TareaController::class, 'storeFoto'])->name('vm.tarea.foto.store')->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+']);
        Route::delete('tareas_{tipo}_form/{id}/fotos/{fotoId}', [\App\Http\Controllers\Vm\TareaController::class, 'deleteFoto'])->name('vm.tarea.foto.delete')->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+', 'fotoId' => '[0-9]+']);
        Route::patch('tareas_{tipo}_form/{id}/fotos/{fotoId}', [\App\Http\Controllers\Vm\TareaController::class, 'renameFoto'])->name('vm.tarea.foto.rename')->where(['tipo' => 'limpieza|mantenimiento|piscina', 'id' => '[0-9]+', 'fotoId' => '[0-9]+']);
        Route::patch('tareas_limpieza/{id}/asignar', [PlanificadorLimpiezaController::class, 'assign'])->name('planificador-limpieza.asignar');
        Route::patch('tareas/{tipo}/{id}/borrar',  [TareaController::class, 'toggleBorrar'])->name('vm.tarea.borrar')->where(['tipo' => 'limpieza|mantenimiento|piscina']);
        Route::patch('tareas/{tipo}/{id}/ocultar', [TareaController::class, 'toggleOcultar'])->name('vm.tarea.ocultar')->where(['tipo' => 'limpieza|mantenimiento|piscina']);
        Route::patch('tareas_limpieza/{id}/replanificar', [PlanificadorLimpiezaController::class, 'replanificar'])->name('planificador-limpieza.replanificar');

        Route::get('pyg', [PygController::class, 'index'])->name('vm.pyg');
        Route::post('pyg/import', [PygController::class, 'import'])->name('vm.pyg.import');
        Route::delete('pyg/{periodo}', [PygController::class, 'deletePeriodo'])->name('vm.pyg.delete');

        Route::get('liquidacion', [LiquidacionController::class, 'index'])->name('vm.liquidacion');
        Route::get('liquidacion/pdf', [LiquidacionController::class, 'pdf'])->name('vm.liquidacion.pdf');
        Route::post('liquidacion/{reserva}/toggle', [LiquidacionController::class, 'toggleLiquidado'])->name('vm.liquidacion.toggle');

        Route::get('{table}', [ListadoController::class, 'index'])->name('listado');
        Route::post('{table}/upload-doc', [FichaController::class, 'uploadDoc'])->name('listado.upload-doc');

        // Excel export: disponible para todos
        Route::get('{table}/export', [ExcelController::class, 'export'])->name('excel.export');
        Route::post('propiedades/sync-icnea', [PropiedadesController::class, 'syncIcnea'])->name('propiedades.sync-icnea');

        // Excel import: solo admin del proyecto
        Route::middleware('role.project-admin')->group(function () {
            Route::get('{table}/import', [ExcelController::class, 'importForm'])->name('excel.import-form');
            Route::get('{table}/import/template', [ExcelController::class, 'importTemplate'])->name('excel.import-template');
            Route::post('{table}/import/preview', [ExcelController::class, 'importPreview'])->name('excel.import-preview');
            Route::post('{table}/import/confirm', [ExcelController::class, 'import'])->name('excel.import');
        });

        Route::get('{table}/nuevo', [FichaController::class, 'create'])->name('ficha.create');
        Route::post('{table}', [FichaController::class, 'store'])->name('ficha.store');

        Route::get('{table}/{id}', [FichaController::class, 'show'])->name('ficha');
        Route::put('{table}/{id}', [FichaController::class, 'update'])->name('ficha.update');
        Route::patch('{table}/{id}/campo', [FichaController::class, 'updateField'])->name('ficha.update-field');
        Route::patch('{table}/{id}/archivar', [FichaController::class, 'archive'])->name('ficha.archive');
        Route::patch('{table}/{id}/borrar', [FichaController::class, 'borrar'])->name('ficha.borrar');
        Route::patch('{table}/{id}/bloquear', [FichaController::class, 'block'])->name('ficha.block');
        Route::post('{table}/{id}/reset-password', [FichaController::class, 'resetPassword'])->name('ficha.reset-password');
        Route::delete('{table}/{id}', [FichaController::class, 'eliminar'])->name('ficha.eliminar');
    });

});
