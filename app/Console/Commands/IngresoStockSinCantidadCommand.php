<?php

namespace App\Console\Commands;

use App\Models\Programas\Familia;
use App\Models\Stock\Almacen;
use App\Models\Usuario;
use App\Services\Stock\MovimientoService;
use App\Support\MaterialProgramaLookup;
use App\Support\StockExcelFilas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Antes: php database/34_ingreso_excel_sin_cantidad_safco.php [ruta.xlsx] [cantidad] */
class IngresoStockSinCantidadCommand extends Command
{
    protected $signature = 'pl:ingreso-stock-sin-cantidad
        {archivo? : Ruta de Stock.xlsx (por defecto Stock.xlsx en la raíz)}
        {--cantidad=50000 : Cantidad a ingresar por cada material sin cantidad en el Excel}';

    protected $description = 'SAFCO: crea un ingreso con la cantidad indicada para cada fila del Excel sin cantidad (no borra movimientos previos).';

    public function handle(MovimientoService $movimientos): int
    {
        $ruta = (string) ($this->argument('archivo') ?: base_path('Stock.xlsx'));
        $cantidadDefault = (float) $this->option('cantidad');

        if (! is_file($ruta)) {
            $this->error("No existe: {$ruta}");

            return self::FAILURE;
        }

        if ($cantidadDefault <= 0) {
            $this->error('La cantidad debe ser mayor a cero.');

            return self::FAILURE;
        }

        $almacen = Almacen::query()
            ->whereIn('codigo', ['SFC', 'SAFCO'])
            ->where('Estado', 1)
            ->orderByRaw("FIELD(codigo, 'SFC', 'SAFCO')")
            ->first();

        if (! $almacen) {
            $this->error('Almacén SAFCO (SFC) no encontrado.');

            return self::FAILURE;
        }

        $idAlmacen = (int) $almacen->idAlmacen;
        $filas = StockExcelFilas::leer(IOFactory::load($ruta)->getActiveSheet());

        if ($filas === []) {
            $this->error('Sin filas válidas en el Excel.');

            return self::FAILURE;
        }

        $idUsuario = Usuario::query()->where('Estado', 1)->orderBy('idUsuario')->value('idUsuario');
        $materialesUpsert = 0;
        $lineasIngreso = [];
        $omitidosConStock = 0;
        $omitidosConCantidadExcel = 0;

        foreach ($filas as $fila) {
            if ($fila['codigo'] === '-' && $fila['descripcion'] === '') {
                continue;
            }

            if ($fila['cantidad'] > 0) {
                $omitidosConCantidadExcel++;

                continue;
            }

            if ($fila['familia'] !== '') {
                Familia::query()->firstOrCreate(['nomFamilia' => $fila['familia']], ['Estado' => 1]);
            }

            $material = MaterialProgramaLookup::resolverPorCodigo(
                $fila['codigo'],
                $fila['descripcion'] !== '' ? $fila['descripcion'] : $fila['codigo']
            );
            $materialesUpsert++;

            $idMaterial = (int) $material->idMaterialPL;

            $stockActual = (float) (DB::table('vw_StockAlmacen')
                ->where('idAlmacen', $idAlmacen)
                ->where('idMaterialPL', $idMaterial)
                ->value('stock') ?? 0);

            if ($stockActual > 0) {
                $omitidosConStock++;

                continue;
            }

            $lineasIngreso[] = [
                'id_material_pl' => $idMaterial,
                'cantidad' => $cantidadDefault,
            ];
        }

        $ingresosCreados = 0;

        if ($lineasIngreso !== []) {
            DB::transaction(function () use ($idAlmacen, $idUsuario, $lineasIngreso, $cantidadDefault, $movimientos, &$ingresosCreados) {
                $ingresosCreados = $movimientos->crearIngreso([
                    'id_almacen' => $idAlmacen,
                    'fecha' => now()->toDateString(),
                    'observacion' => 'Carga Excel sin cantidad · '.$cantidadDefault.' u. SAFCO',
                    'lineas' => $lineasIngreso,
                ], $idUsuario ? (int) $idUsuario : null);
            });
        }

        Cache::forget('catalogo.almacenes.v2');
        Cache::forget('catalogo.programas_consumo');

        $this->info("Ingreso SAFCO ({$almacen->codigo}) · sin cantidad Excel");
        $this->line("- Cantidad por material: {$cantidadDefault}");
        $this->line("- Filas Excel sin cantidad: {$materialesUpsert}");
        $this->line("- Omitidos (ya tenían stock SFC): {$omitidosConStock}");
        $this->line("- Omitidos (Excel con cantidad > 0): {$omitidosConCantidadExcel}");
        $this->line("- Líneas ingreso creadas: {$ingresosCreados}");

        return self::SUCCESS;
    }
}
