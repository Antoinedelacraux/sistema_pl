<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MaestroController;
use App\Http\Controllers\MrpController;
use App\Http\Controllers\PlanProduccionController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\ProgramaPresentacionParamController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\StockResumenController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware(['auth', 'usuario.activo'])->group(function () {
    Route::get('/inicio', [StockResumenController::class, 'index'])
        ->middleware('permiso:inicio,ver')
        ->name('inicio');
    Route::get('/inicio/export/excel', [StockResumenController::class, 'exportExcel'])
        ->middleware('permiso:inicio,ver')
        ->name('inicio.export.excel');
    Route::get('/inicio/export/pdf', [StockResumenController::class, 'exportPdf'])
        ->middleware('permiso:inicio,ver')
        ->name('inicio.export.pdf');
    Route::get('/resumen', [StockResumenController::class, 'index'])->middleware('permiso:inicio,ver')->name('resumen');
    Route::redirect('/resumen/export/excel', '/inicio/export/excel');
    Route::redirect('/resumen/export/pdf', '/inicio/export/pdf');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/programas', [ProgramaController::class, 'index'])
        ->middleware('permiso:programas,ver')
        ->name('programas.index');

    Route::post('/programas/import', [ProgramaController::class, 'importExcel'])
        ->middleware('permiso:programas,crear')
        ->name('programas.import');

    Route::post('/programas/vaciar', [ProgramaController::class, 'vaciarReceta'])
        ->middleware('permiso:programas,crear')
        ->name('programas.vaciar');

    Route::get('/mrp', [MrpController::class, 'index'])
        ->middleware('permiso:mrp,ver')
        ->name('mrp.index');

    Route::get('/mrp/seccion/{tipo}', [MrpController::class, 'seccion'])
        ->middleware('permiso:mrp,ver')
        ->whereIn('tipo', ['consumo', 'ingresos'])
        ->name('mrp.seccion');

    Route::get('/mrp/secciones-extra', [MrpController::class, 'seccionesExtra'])
        ->middleware('permiso:mrp,ver')
        ->name('mrp.secciones-extra');

    Route::post('/mrp/import', [MrpController::class, 'importExcel'])
        ->middleware('permiso:mrp,crear')
        ->name('mrp.import');

    Route::post('/mrp/vaciar', [MrpController::class, 'vaciarPlan'])
        ->middleware('permiso:mrp,crear')
        ->name('mrp.vaciar');

    Route::put('/mrp/plan', [MrpController::class, 'updatePlan'])
        ->middleware('permiso:mrp,editar')
        ->name('mrp.plan.update');

    Route::get('/plan-produccion', [PlanProduccionController::class, 'index'])
        ->middleware('permiso:plan_produccion,ver')
        ->name('plan-produccion.index');

    Route::get('/plan-produccion/materiales/{idProgramaGeneral}', [PlanProduccionController::class, 'materiales'])
        ->middleware('permiso:plan_produccion,ver')
        ->whereNumber('idProgramaGeneral')
        ->name('plan-produccion.materiales');

    Route::get('/plan-produccion/export/excel/{seccion}', [PlanProduccionController::class, 'exportExcel'])
        ->middleware('permiso:plan_produccion,ver')
        ->where('seccion', 'ventas|consumo')
        ->name('plan-produccion.export.excel');

    Route::get('/plan-produccion/export/pdf/{seccion}', [PlanProduccionController::class, 'exportPdf'])
        ->middleware('permiso:plan_produccion,ver')
        ->where('seccion', 'ventas|consumo')
        ->name('plan-produccion.export.pdf');

    Route::post('/plan-produccion/import', [PlanProduccionController::class, 'importExcel'])
        ->middleware('permiso:plan_produccion,crear')
        ->name('plan-produccion.import');

    Route::post('/plan-produccion/vaciar', [PlanProduccionController::class, 'vaciar'])
        ->middleware('permiso:plan_produccion,crear')
        ->name('plan-produccion.vaciar');

    Route::put('/plan-produccion/plan', [PlanProduccionController::class, 'updatePlan'])
        ->middleware('permiso:plan_produccion,editar')
        ->name('plan-produccion.update');

    Route::post('/programas', [ProgramaController::class, 'store'])
        ->middleware('permiso:programas,crear')
        ->name('programas.store');

    Route::put('/programas/fila/{idProgramaFila}', [ProgramaController::class, 'update'])
        ->middleware('permiso:programas,editar')
        ->whereNumber('idProgramaFila')
        ->name('programas.update');

    Route::put('/programas/fila/{idProgramaFila}/imagen', [ProgramaController::class, 'updateImagen'])
        ->middleware('permiso:programas,editar')
        ->whereNumber('idProgramaFila')
        ->name('programas.imagen.update');

    Route::get('/programas/material-por-codigo', [ProgramaController::class, 'materialPorCodigo'])
        ->middleware('permiso:programas,ver')
        ->name('programas.material-por-codigo');

    Route::get('/programas/export/excel', [ProgramaController::class, 'exportExcel'])
        ->middleware('permiso:programas,ver')
        ->name('programas.export.excel');

    Route::get('/programas/export/pdf', [ProgramaController::class, 'exportPdf'])
        ->middleware('permiso:programas,ver')
        ->name('programas.export.pdf');

    Route::get('/programas/presentacion-params', [ProgramaPresentacionParamController::class, 'index'])
        ->middleware('permiso:programas,ver')
        ->name('programas.presentacion-params.index');

    Route::post('/programas/presentacion-params', [ProgramaPresentacionParamController::class, 'store'])
        ->middleware('permiso:programas,editar')
        ->name('programas.presentacion-params.store');

    Route::get('/proveedor', [ProveedorController::class, 'index'])
        ->middleware('permiso:proveedor,ver')
        ->name('proveedor.index');

    Route::get('/maestros', [MaestroController::class, 'index'])
        ->middleware('permiso:maestros,ver')
        ->name('maestros.index');

    Route::post('/maestros/{tab}', [MaestroController::class, 'store'])
        ->middleware('permiso:maestros,crear')
        ->name('maestros.store');

    Route::put('/maestros/{tab}/{id}', [MaestroController::class, 'update'])
        ->middleware('permiso:maestros,editar')
        ->whereNumber('id')
        ->name('maestros.update');

    Route::patch('/maestros/{tab}/{id}/desactivar', [MaestroController::class, 'desactivar'])
        ->middleware('permiso:maestros,eliminar')
        ->whereNumber('id')
        ->name('maestros.desactivar');

    Route::patch('/maestros/{tab}/{id}/activar', [MaestroController::class, 'activar'])
        ->middleware('permiso:maestros,editar')
        ->whereNumber('id')
        ->name('maestros.activar');

    Route::get('/proveedor/export/excel', [ProveedorController::class, 'exportExcel'])
        ->middleware('permiso:proveedor,ver')
        ->name('proveedor.export.excel');

    Route::get('/proveedor/export/pdf', [ProveedorController::class, 'exportPdf'])
        ->middleware('permiso:proveedor,ver')
        ->name('proveedor.export.pdf');

    Route::post('/proveedor', [ProveedorController::class, 'store'])
        ->middleware('permiso:proveedor,crear')
        ->name('proveedor.store');

    Route::post('/proveedor/import/preview', [ProveedorController::class, 'importPreview'])
        ->middleware('permiso:proveedor,crear')
        ->name('proveedor.import.preview');

    Route::post('/proveedor/import', [ProveedorController::class, 'importConfirm'])
        ->middleware('permiso:proveedor,crear')
        ->name('proveedor.import');

    Route::get('/proveedor/material-por-codigo', [ProveedorController::class, 'materialPorCodigo'])
        ->middleware('permiso:proveedor,ver')
        ->name('proveedor.material-por-codigo');

    Route::get('/proveedor/material-por-nombre', [ProveedorController::class, 'materialPorNombre'])
        ->middleware('permiso:proveedor,ver')
        ->name('proveedor.material-por-nombre');

    Route::put('/proveedor/fila/{nroFila}', [ProveedorController::class, 'update'])
        ->middleware('permiso:proveedor,editar')
        ->whereNumber('nroFila')
        ->name('proveedor.update');

    Route::delete('/proveedor/fila/{nroFila}', [ProveedorController::class, 'destroy'])
        ->middleware('permiso:proveedor,eliminar')
        ->whereNumber('nroFila')
        ->name('proveedor.destroy');

    Route::get('/inventario', [InventarioController::class, 'index'])
        ->middleware('permiso:inventario,ver')
        ->name('inventario.index');

    Route::get('/inventario/export/excel', [InventarioController::class, 'exportExcel'])
        ->middleware('permiso:inventario,ver')
        ->name('inventario.export.excel');

    Route::get('/inventario/export/pdf', [InventarioController::class, 'exportPdf'])
        ->middleware('permiso:inventario,ver')
        ->name('inventario.export.pdf');

    Route::get('/registro', [MovimientoController::class, 'index'])
        ->middleware('permiso:registro,ver')
        ->name('registro.index');

    Route::get('/registro/export/excel', [MovimientoController::class, 'exportExcel'])
        ->middleware('permiso:registro,ver')
        ->name('registro.export.excel');

    Route::get('/registro/export/pdf', [MovimientoController::class, 'exportPdf'])
        ->middleware('permiso:registro,ver')
        ->name('registro.export.pdf');

    Route::get('/registro/offline/csrf', [MovimientoController::class, 'offlineCsrf'])
        ->middleware('permiso:registro,crear')
        ->name('registro.offline.csrf');

    Route::post('/registro/ingreso/leer-xml', [MovimientoController::class, 'parseIngresoXml'])
        ->middleware('permiso:registro,crear')
        ->name('registro.ingreso.leer-xml');

    Route::post('/registro/ingreso/leer-excel', [MovimientoController::class, 'parseIngresoExcel'])
        ->middleware('permiso:registro,crear')
        ->name('registro.ingreso.leer-excel');

    Route::post('/registro/ingreso', [MovimientoController::class, 'storeIngreso'])
        ->middleware('permiso:registro,crear')
        ->name('registro.ingreso.store');

    Route::put('/registro/ingreso/{idMovimiento}', [MovimientoController::class, 'updateIngreso'])
        ->middleware('permiso:registro,editar')
        ->whereNumber('idMovimiento')
        ->name('registro.ingreso.update');

    Route::post('/registro/egreso', [MovimientoController::class, 'storeEgreso'])
        ->middleware('permiso:registro,crear')
        ->name('registro.egreso.store');

    Route::put('/registro/egreso/{idMovimiento}', [MovimientoController::class, 'updateEgreso'])
        ->middleware('permiso:registro,editar')
        ->whereNumber('idMovimiento')
        ->name('registro.egreso.update');

    Route::post('/registro/traslado', [MovimientoController::class, 'storeTraslado'])
        ->middleware('permiso:registro,crear')
        ->name('registro.traslado.store');

    Route::put('/registro/traslado/{idTraslado}', [MovimientoController::class, 'updateTraslado'])
        ->middleware('permiso:registro,editar')
        ->whereNumber('idTraslado')
        ->name('registro.traslado.update');

    Route::delete('/registro/movimiento/{idMovimiento}', [MovimientoController::class, 'destroyMovimiento'])
        ->middleware('permiso:registro,eliminar')
        ->whereNumber('idMovimiento')
        ->name('registro.movimiento.destroy');

    Route::delete('/registro/traslado/{idTraslado}', [MovimientoController::class, 'destroyTraslado'])
        ->middleware('permiso:registro,eliminar')
        ->whereNumber('idTraslado')
        ->name('registro.traslado.destroy');

    Route::get('/registro/material-por-codigo', [MovimientoController::class, 'materialPorCodigo'])
        ->middleware('permiso:registro,ver')
        ->name('registro.material-por-codigo');

    Route::get('/registro/materiales-buscar', [MovimientoController::class, 'buscarMateriales'])
        ->middleware('permiso:registro,ver')
        ->name('registro.materiales.buscar');

    Route::get('/registro/stock-disponible', [MovimientoController::class, 'stockDisponible'])
        ->middleware('permiso:registro,ver')
        ->name('registro.stock-disponible');

    Route::get('/registro/consumo/programas', [MovimientoController::class, 'programasConsumo'])
        ->middleware('permiso:registro,ver')
        ->name('registro.consumo.programas');

    Route::get('/registro/consumo/presentaciones', [MovimientoController::class, 'presentacionesConsumo'])
        ->middleware('permiso:registro,ver')
        ->name('registro.consumo.presentaciones');

    Route::get('/registro/consumo/materiales', [MovimientoController::class, 'materialesConsumo'])
        ->middleware('permiso:registro,ver')
        ->name('registro.consumo.materiales');

    Route::get('/registro/consumo/hint', [MovimientoController::class, 'hintConsumo'])
        ->middleware('permiso:registro,ver')
        ->name('registro.consumo.hint');

    Route::get('/registro/movimiento/{idMovimiento}/detalle', [MovimientoController::class, 'detalleMovimiento'])
        ->middleware('permiso:registro,ver')
        ->whereNumber('idMovimiento')
        ->name('registro.movimiento.detalle');

    Route::get('/registro/traslado/{idTraslado}/detalle', [MovimientoController::class, 'detalleTraslado'])
        ->middleware('permiso:registro,ver')
        ->whereNumber('idTraslado')
        ->name('registro.traslado.detalle');

    Route::get('/usuario/{usuario}/tarjeta', [UsuarioController::class, 'tarjeta'])
        ->whereNumber('usuario')
        ->name('usuario.tarjeta');

    Route::middleware('admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar'])->name('usuarios.desactivar');
        Route::patch('/usuarios/{usuario}/activar', [UsuarioController::class, 'activar'])->name('usuarios.activar');

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    });
});

