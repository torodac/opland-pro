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
use App\Http\Controllers\Vm\AusenciaController;
use App\Http\Controllers\Vm\DashboardController;
use App\Http\Controllers\Vm\PropiedadesController;
use App\Http\Controllers\Vm\PygController;
use App\Http\Controllers\Vm\LiquidacionController;
use App\Http\Controllers\Vm\KmController;
use App\Http\Controllers\Vm\NovacionesController;
use App\Http\Controllers\Vm\InformeFinancieroController;
use App\Http\Controllers\Vm\InformeOperativoController;
use App\Http\Controllers\Vmf\FacturasUploadController;

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

    // Generar/rotar token de API de un proyecto (para integraciones tipo Power BI)
    Route::middleware('role.project-admin')->group(function () {
        Route::get('api/generateToken/{project}', [ProjectController::class, 'generateToken'])
            ->name('projects.generate-token');
    });

    // Perfil de usuario
    Route::get('perfil', [App\Http\Controllers\PerfilController::class, 'show'])->name('perfil');
    Route::patch('perfil', [App\Http\Controllers\PerfilController::class, 'update'])->name('perfil.update');

    // Página de inicio → lista de proyectos
    Route::get('/', [ProjectController::class, 'index'])->name('proyectos');

    // Rutas dentro de un proyecto (con verificación de acceso)
    Route::prefix('{project:slug}')->middleware('project.access')->group(function () {

        // Rutas exclusivas de VacationMarbella (dashboard, tareas, fichajes, pyg, liquidacion, novaciones, km, horario, calendario, informe-imputaciones)
        Route::middleware('vm.only')->group(function () {

        Route::get('calendario-reservas', [CalendarioReservasController::class, 'index'])->name('calendario-reservas');

        Route::get('informe-financiero', [InformeFinancieroController::class, 'index'])->name('informe-financiero');
        Route::get('informe-operativo', [InformeOperativoController::class, 'index'])->name('informe-operativo');

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
        Route::post('novaciones/sincronizar',     [NovacionesController::class, 'sincronizar'])->name('novaciones.sincronizar');
        Route::get('novaciones/pdf',              [NovacionesController::class, 'pdf'])->name('novaciones.pdf');
        Route::get('novaciones/documento/{id}',   [NovacionesController::class, 'verDocumento'])->name('novaciones.ver-documento');
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
        Route::post('dashboard/validar-tarea',   [DashboardController::class, 'validarTarea'])->name('vm.dashboard.validar-tarea');
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

        Route::middleware('table.access:ausencias')->group(function () {
            Route::get('ausencias_form', [AusenciaController::class, 'index'])->where('project', 'vm')->name('vm.ausencias_form');
            Route::post('ausencias_form', [AusenciaController::class, 'store'])->where('project', 'vm')->name('vm.ausencias_form.store');
            Route::patch('ausencias_form/{ausId}', [AusenciaController::class, 'update'])->where('project', 'vm')->name('vm.ausencias_form.update');
            Route::delete('ausencias_form/{ausId}', [AusenciaController::class, 'destroy'])->where('project', 'vm')->name('vm.ausencias_form.delete');
        });

        Route::get('fotos_list', [\App\Http\Controllers\Vm\FotosGaleriaController::class, 'index'])->where('project', 'vm')->name('vm.fotos-list');

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

        Route::get('pyg_form', [PygController::class, 'index'])->name('vm.pyg_form');
        Route::post('pyg_form/import', [PygController::class, 'import'])->name('vm.pyg_form.import');
        Route::delete('pyg_form/{periodo}', [PygController::class, 'deletePeriodo'])->name('vm.pyg_form.delete');

        Route::get('liquidacion', [LiquidacionController::class, 'index'])->name('vm.liquidacion');
        Route::get('liquidacion/pdf', [LiquidacionController::class, 'pdf'])->name('vm.liquidacion.pdf');
        Route::post('liquidacion/{reserva}/toggle', [LiquidacionController::class, 'toggleLiquidado'])->name('vm.liquidacion.toggle');

        }); // fin vm.only

        Route::middleware('vmf.only')->group(function () {
            Route::get('facturas_form', [FacturasUploadController::class, 'index'])->name('vmf.facturas_form');
        }); // fin vmf.only

        Route::middleware('opland.only')->group(function () {
            Route::get('conciliacion', [\App\Http\Controllers\Opland\ConciliacionController::class, 'index'])->name('opland.conciliacion');
            Route::post('conciliacion/vincular', [\App\Http\Controllers\Opland\ConciliacionController::class, 'vincular'])->name('opland.conciliacion.vincular');
            Route::post('conciliacion/desvincular', [\App\Http\Controllers\Opland\ConciliacionController::class, 'desvincular'])->name('opland.conciliacion.desvincular');
            Route::post('conciliacion/crear-desde-banco', [\App\Http\Controllers\Opland\ConciliacionController::class, 'crearDesdeBanco'])->name('opland.conciliacion.crear-desde-banco');

            Route::get('facturas_form/nuevo', [\App\Http\Controllers\Opland\FacturaFormController::class, 'nueva'])->name('opland.factura_form.nueva');
            Route::delete('facturas_form/imputaciones/{imputacion}', [\App\Http\Controllers\Opland\FacturaFormController::class, 'detachImputacion'])->where('imputacion', '[0-9]+')->name('opland.factura_form.detach-imp');
            Route::get('facturas_form/{factura}', [\App\Http\Controllers\Opland\FacturaFormController::class, 'show'])->where('factura', '[0-9]+')->name('opland.factura_form.show');
            Route::get('facturas_form/{factura}/estado', [\App\Http\Controllers\Opland\FacturaFormController::class, 'estado'])->where('factura', '[0-9]+')->name('opland.factura_form.estado');
            Route::get('facturas_form/{factura}/pdf', [\App\Http\Controllers\Opland\FacturaFormController::class, 'pdf'])->where('factura', '[0-9]+')->name('opland.factura_form.pdf');
            Route::patch('facturas_form/{factura}', [\App\Http\Controllers\Opland\FacturaFormController::class, 'updateHeader'])->where('factura', '[0-9]+')->name('opland.factura_form.update-header');
            Route::post('facturas_form/{factura}/lineas', [\App\Http\Controllers\Opland\FacturaFormController::class, 'addLinea'])->where('factura', '[0-9]+')->name('opland.factura_form.lineas.add');
            Route::patch('facturas_form/{factura}/lineas/{linea}', [\App\Http\Controllers\Opland\FacturaFormController::class, 'updateLinea'])->where(['factura' => '[0-9]+', 'linea' => '[0-9]+'])->name('opland.factura_form.lineas.update');
            Route::delete('facturas_form/{factura}/lineas/{linea}', [\App\Http\Controllers\Opland\FacturaFormController::class, 'removeLinea'])->where(['factura' => '[0-9]+', 'linea' => '[0-9]+'])->name('opland.factura_form.lineas.remove');
            Route::post('facturas_form/{factura}/lineas/reorder', [\App\Http\Controllers\Opland\FacturaFormController::class, 'reorderLineas'])->where('factura', '[0-9]+')->name('opland.factura_form.lineas.reorder');
            Route::post('facturas_form/{factura}/lineas/{linea}/imputaciones', [\App\Http\Controllers\Opland\FacturaFormController::class, 'attachImputacion'])->where(['factura' => '[0-9]+', 'linea' => '[0-9]+'])->name('opland.factura_form.lineas.attach-imp');
            Route::post('facturas_form/{factura}/duplicar', [\App\Http\Controllers\Opland\FacturaFormController::class, 'duplicar'])->where('factura', '[0-9]+')->name('opland.factura_form.duplicar');
            Route::post('facturas_form/{factura}/emitir', [\App\Http\Controllers\Opland\FacturaFormController::class, 'emitir'])->where('factura', '[0-9]+')->name('opland.factura_form.emitir');
        }); // fin opland.only

        Route::middleware('rodcar.only')->group(function () {
            Route::get('movs-validacion', [\App\Http\Controllers\Rodcar\ValidacionMovimientosController::class, 'index'])->name('rodcar.movs-validacion');
            Route::post('movs-validacion/clasificar', [\App\Http\Controllers\Rodcar\ValidacionMovimientosController::class, 'clasificar'])->name('rodcar.movs-validacion.clasificar');
            Route::post('movs-validacion/{origen}/{id}', [\App\Http\Controllers\Rodcar\ValidacionMovimientosController::class, 'validar'])->where(['origen' => 'movs|detalle', 'id' => '[0-9]+'])->name('rodcar.movs-validacion.validar');

            Route::get('importar', [\App\Http\Controllers\Rodcar\ImportarMovimientosController::class, 'index'])->where('project', 'rodcar')->name('rodcar.importar');
            Route::post('importar', [\App\Http\Controllers\Rodcar\ImportarMovimientosController::class, 'subir'])->where('project', 'rodcar')->name('rodcar.importar.subir');
            Route::post('importar/confirmar-dudosos', [\App\Http\Controllers\Rodcar\ImportarMovimientosController::class, 'confirmarDudosos'])->where('project', 'rodcar')->name('rodcar.importar.confirmar-dudosos');
            Route::get('importar/huerfanos/{lote}', [\App\Http\Controllers\Rodcar\ImportarMovimientosController::class, 'huerfanosLote'])->where(['project' => 'rodcar', 'lote' => '[0-9]+'])->name('rodcar.importar.huerfanos');
            Route::post('importar/huerfanos/{lote}/vincular', [\App\Http\Controllers\Rodcar\ImportarMovimientosController::class, 'vincular'])->where(['project' => 'rodcar', 'lote' => '[0-9]+'])->name('rodcar.importar.vincular');
        }); // fin rodcar.only

        Route::middleware('mb.only')->group(function () {
            Route::get('clasificacion_gastos', [\App\Http\Controllers\Mb\ClasificacionGastosController::class, 'index'])->name('mb.clasificacion-gastos');
            Route::post('clasificacion_gastos/{gasto}/clasificar', [\App\Http\Controllers\Mb\ClasificacionGastosController::class, 'clasificar'])->where('gasto', '[0-9]+')->name('mb.clasificacion-gastos.clasificar');
            Route::post('clasificacion_gastos/{gasto}/deshacer', [\App\Http\Controllers\Mb\ClasificacionGastosController::class, 'deshacer'])->where('gasto', '[0-9]+')->name('mb.clasificacion-gastos.deshacer');

            // ->where('project','mb') además del middleware mb.only: el segmento estático "pyg" coincide también
            // con vm/pyg (tabla real vm_pyg) y sin esta restricción Laravel prioriza esta ruta sobre el
            // comodín genérico {table} para CUALQUIER slug, rompiendo vm/pyg con 404 (regresión detectada 2026-07-28).
            Route::get('pyg', [\App\Http\Controllers\Mb\DesgloseContableController::class, 'index'])->where('project', 'mb')->name('mb.pyg');
            Route::get('pyg/movimientos', [\App\Http\Controllers\Mb\DesgloseContableController::class, 'movimientos'])->where('project', 'mb')->name('mb.pyg.movimientos');

            // Sustituye al listado genérico de "viviendas" (mismo motivo que "pyg" arriba: ->where('project','mb')
            // para que el comodín genérico {table} siga funcionando igual para cualquier otro proyecto).
            Route::get('viviendas', [\App\Http\Controllers\Mb\ViviendasController::class, 'index'])->where('project', 'mb')->name('mb.viviendas');
            Route::get('viviendas/export', [\App\Http\Controllers\Mb\ViviendasController::class, 'export'])->where('project', 'mb')->name('mb.viviendas.export');

            // Idem: sustituye al listado genérico de "propietarios".
            Route::get('propietarios', [\App\Http\Controllers\Mb\PropietariosController::class, 'index'])->where('project', 'mb')->name('mb.propietarios');

            Route::post('cuotas/generar', [\App\Http\Controllers\Mb\GenerarCuotasController::class, 'store'])->where('project', 'mb')->name('mb.cuotas.generar');

            Route::post('viviendas/{vivienda}/entregas-cuenta', [\App\Http\Controllers\Mb\EntregasCuentaController::class, 'store'])->where(['project' => 'mb', 'vivienda' => '[0-9]+'])->name('mb.entregas-cuenta.store');
            Route::post('entregas-cuenta/{entrega}/aplicar', [\App\Http\Controllers\Mb\EntregasCuentaController::class, 'aplicar'])->where(['project' => 'mb', 'entrega' => '[0-9]+'])->name('mb.entregas-cuenta.aplicar');
            Route::get('entregas-cuenta/{entrega}/pendientes', [\App\Http\Controllers\Mb\EntregasCuentaController::class, 'pendientes'])->where(['project' => 'mb', 'entrega' => '[0-9]+'])->name('mb.entregas-cuenta.pendientes');

            Route::post('viviendas/ticket', [\App\Http\Controllers\Mb\TicketController::class, 'confirmar'])->where('project', 'mb')->name('mb.viviendas.ticket.confirmar');
            Route::get('viviendas/ticket/{ticket}', [\App\Http\Controllers\Mb\TicketController::class, 'imprimir'])->where(['project' => 'mb', 'ticket' => '[0-9]+'])->name('mb.viviendas.ticket.imprimir');

            Route::get('asamblea/reparto', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'index'])->where('project', 'mb')->name('mb.asamblea.reparto');
            Route::get('asamblea/reparto/vivienda', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'vivienda'])->where('project', 'mb')->name('mb.asamblea.reparto.vivienda');
            Route::get('asamblea/reparto/buscar-nombre', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'buscarNombre'])->where('project', 'mb')->name('mb.asamblea.reparto.buscar-nombre');
            Route::post('asamblea/reparto/hoja', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'registrarHoja'])->where('project', 'mb')->name('mb.asamblea.reparto.hoja');
            Route::delete('asamblea/reparto/hoja', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'anularHoja'])->where('project', 'mb')->name('mb.asamblea.reparto.hoja.anular');
            Route::get('asamblea/reparto/historico', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'historico'])->where('project', 'mb')->name('mb.asamblea.reparto.historico');

            Route::get('asamblea/recuento/estado', [\App\Http\Controllers\Mb\AsambleaRecuentoController::class, 'estadoRefresh'])->where('project', 'mb')->name('mb.asamblea.recuento.estado');
            Route::post('asamblea/recuento/voto', [\App\Http\Controllers\Mb\AsambleaRecuentoController::class, 'registrarVoto'])->where('project', 'mb')->name('mb.asamblea.recuento.voto');

            Route::get('asamblea_reparto', [\App\Http\Controllers\Mb\AsambleaRepartoController::class, 'backoffice'])->where('project', 'mb')->name('mb.asamblea_reparto');
            Route::get('asamblea_recuento', [\App\Http\Controllers\Mb\AsambleaRecuentoController::class, 'backoffice'])->where('project', 'mb')->name('mb.asamblea_recuento');
            Route::delete('asamblea/recuento/voto', [\App\Http\Controllers\Mb\AsambleaRecuentoController::class, 'eliminarVoto'])->where('project', 'mb')->name('mb.asamblea.recuento.voto.delete');

            Route::get('asamblea_generador', [\App\Http\Controllers\Mb\AsambleaGeneradorController::class, 'index'])->where('project', 'mb')->name('mb.asamblea_generador');
            Route::get('asamblea_generador/pdf', [\App\Http\Controllers\Mb\AsambleaGeneradorController::class, 'descargarPdf'])->where('project', 'mb')->name('mb.asamblea_generador.pdf');
            Route::post('asamblea_generador/asamblea', [\App\Http\Controllers\Mb\AsambleaGeneradorController::class, 'crearAsamblea'])->where('project', 'mb')->name('mb.asamblea_generador.asamblea');
            Route::post('asamblea_generador/preguntas', [\App\Http\Controllers\Mb\AsambleaGeneradorController::class, 'guardarPreguntas'])->where('project', 'mb')->name('mb.asamblea_generador.preguntas');
            Route::post('asamblea_generador/pregunta', [\App\Http\Controllers\Mb\AsambleaGeneradorController::class, 'agregarPregunta'])->where('project', 'mb')->name('mb.asamblea_generador.pregunta.add');
            Route::delete('asamblea_generador/pregunta', [\App\Http\Controllers\Mb\AsambleaGeneradorController::class, 'eliminarPregunta'])->where('project', 'mb')->name('mb.asamblea_generador.pregunta.delete');
        }); // fin mb.only

        Route::get('{table}', [ListadoController::class, 'index'])->name('listado');
        Route::get('{table}/ids', [ListadoController::class, 'ids'])->name('listado.ids');
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

            Route::get('{table}/actualizacion-masiva', [FichaController::class, 'bulkEditForm'])->name('ficha.bulk-edit-form');
            Route::post('{table}/actualizacion-masiva', [FichaController::class, 'bulkUpdate'])->name('ficha.bulk-update');
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
