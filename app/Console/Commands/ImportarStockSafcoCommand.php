<?php

namespace App\Console\Commands;

use App\Models\Programas\Familia;
use App\Models\Stock\Almacen;
use App\Models\Stock\MovimientoStock;
use App\Models\Usuario;
use App\Services\Stock\MovimientoService;
use App\Services\Mrp\MrpGridService;
use App\Support\MaterialProgramaLookup;
use App\Support\PlanConsumoOrdenSupport;
use App\Support\StockExcelFilas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Antes: php database/32_importar_stock_safco.php [ruta.xlsx] */
class ImportarStockSafcoCommand extends Command
{
    protected $signature = 'pl:importar-stock-safco
        {archivo? : Ruta de Stock.xlsx (por defecto Stock.xlsx en la raíz)}
        {--force : No pedir confirmación}';

    protected $description = 'DESTRUCTIVO: reemplaza el stock de SAFCO (SFC) con Stock.xlsx (upsert de materiales + un ingreso agrupado).';

    public function handle(MovimientoService $movimientos, MrpGridService $mrpGrid): int
    {
        $ruta = (string) ($this->argument('archivo') ?: base_path('Stock.xlsx'));
        if (! is_file($ruta)) {
            $this->error("No existe: {$ruta}");

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

        $filas = StockExcelFilas::leer(IOFactory::load($ruta)->getActiveSheet());

        if ($filas === []) {
            $this->error('Sin filas válidas en el Excel.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Se borrarán los movimientos (no traslados) de {$almacen->codigo} y se cargará el Excel. ¿Continuar?")) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $idUsuario = Usuario::query()->where('Estado', 1)->orderBy('idUsuario')->value('idUsuario');
        $materiales = 0;
        $sinStock = 0;
        $sinCodigo = 0;
        $lineasIngreso = [];

        foreach ($filas as $fila) {
            if ($fila['codigo'] === '-' && $fila['descripcion'] === '') {
                continue;
            }

            if ($fila['codigo'] === '-') {
                $sinCodigo++;
            }

            if ($fila['familia'] !== '') {
                Familia::query()->firstOrCreate(['nomFamilia' => $fila['familia']], ['Estado' => 1]);
            }

            $material = MaterialProgramaLookup::resolverPorCodigo(
                $fila['codigo'],
                $fila['descripcion'] !== '' ? $fila['descripcion'] : $fila['codigo']
            );
            $materiales++;

            if ($fila['cantidad'] <= 0) {
                $sinStock++;

                continue;
            }

            $lineasIngreso[] = [
                'id_material_pl' => (int) $material->idMaterialPL,
                'cantidad' => $fila['cantidad'],
            ];
        }

        $ingresosCreados = 0;

        DB::transaction(function () use ($almacen, $idUsuario, $lineasIngreso, $movimientos, &$ingresosCreados) {
            // Limpia stock previo solo en SAFCO
            $idAlmacen = (int) $almacen->idAlmacen;
            MovimientoStock::query()
                ->where('idAlmacen', $idAlmacen)
                ->whereNull('idTraslado')
                ->delete();

            if ($lineasIngreso !== []) {
                $ingresosCreados = $movimientos->crearIngreso([
                    'id_almacen' => $idAlmacen,
                    'fecha' => now()->toDateString(),
                    'observacion' => 'Carga inicial stock Excel SAFCO',
                    'lineas' => $lineasIngreso,
                ], $idUsuario ? (int) $idUsuario : null);
            }
        });

        Cache::forget('catalogo.almacenes.v2');
        Cache::forget('catalogo.programas_consumo');

        $ordenStock = PlanConsumoOrdenSupport::sincronizarOrdenDesdeStockExcel($ruta);
        if ($ordenStock > 0) {
            $mrpGrid->forgetCaches();
        }

        $this->info("Import SAFCO ({$almacen->codigo}) desde ".basename($ruta));
        $this->line('- Filas Excel: '.count($filas));
        $this->line("- Materiales upsert: {$materiales}");
        $this->line("- Sin Idproducto (-), resueltas por descripción: {$sinCodigo}");
        $this->line("- Sin stock (0 o -): {$sinStock}");
        $this->line("- Líneas ingreso: {$ingresosCreados}");
        $this->line("- Orden plan MRP (Stock.xlsx): {$ordenStock} material(es)");

        return self::SUCCESS;
    }
}
