<?php

namespace App\Services\Stock;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\Almacen;
use App\Support\MaterialProgramaLookup;
use App\Support\StockExcelFilas;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IngresoStockExcelParserService
{
    /** @var array<string, list<string>> */
    private const ALIAS = [
        'id_producto' => ['Idproducto', 'Id producto', 'ID Producto', 'Codigo PL', 'Código PL'],
        'descripcion' => ['Descripción', 'Descripcion', 'Material', 'Material Planeamiento'],
        'stock' => ['STOCK', 'Stock', 'Cantidad'],
        'observaciones' => ['OBSERVACIONES', 'Observaciones', 'Familia'],
    ];

    /** @var array<string, true> Familias cuyo nomFamilia exacto ya existe (o se creó) en esta lectura. */
    private array $familiasExistentes = [];

    /** @var array<string, MaterialProgramaPL> Materiales por CodigoPL exacto, precargados en esta lectura. */
    private array $materialesPorCodigo = [];

    /**
     * @return array{
     *     ok: bool,
     *     id_almacen: int|null,
     *     codigo_almacen: string|null,
     *     lineas: list<array{
     *         id_material_pl: int,
     *         codigo_pl: string,
     *         nom_material: string,
     *         cantidad: float,
     *         nom_familia: string|null
     *     }>,
     *     advertencias: list<string>,
     *     error?: string
     * }
     */
    public function parse(UploadedFile $archivo): array
    {
        $almacen = Almacen::query()
            ->whereIn('codigo', ['SFC', 'SAFCO'])
            ->where('Estado', 1)
            ->orderByRaw("FIELD(codigo, 'SFC', 'SAFCO')")
            ->first();

        if (! $almacen) {
            return [
                'ok' => false,
                'id_almacen' => null,
                'codigo_almacen' => null,
                'lineas' => [],
                'advertencias' => [],
                'error' => 'No se encontró el almacén SAFCO (SFC).',
            ];
        }

        $hoja = IOFactory::load($archivo->getRealPath())->getActiveSheet();
        $filaEncabezado = $this->detectarFilaEncabezado($hoja);

        if ($filaEncabezado === null) {
            return [
                'ok' => false,
                'id_almacen' => (int) $almacen->idAlmacen,
                'codigo_almacen' => $almacen->codigo,
                'lineas' => [],
                'advertencias' => [],
                'error' => 'No se encontró la fila de encabezados (Idproducto, Descripción, STOCK).',
            ];
        }

        $mapa = $this->mapaColumnas($hoja, $filaEncabezado);
        foreach (['id_producto', 'descripcion', 'stock'] as $requerida) {
            if (! isset($mapa[$requerida])) {
                return [
                    'ok' => false,
                    'id_almacen' => (int) $almacen->idAlmacen,
                    'codigo_almacen' => $almacen->codigo,
                    'lineas' => [],
                    'advertencias' => [],
                    'error' => 'Falta la columna requerida en el Excel.',
                ];
            }
        }

        $lineas = [];
        $advertencias = [];
        $ultimaFila = (int) $hoja->getHighestDataRow();
        $filas = [];

        for ($fila = $filaEncabezado + 1; $fila <= $ultimaFila; $fila++) {
            $codigo = $this->normalizarCodigo($this->valorCodigoProducto($hoja, $mapa['id_producto'].$fila));
            $descripcion = trim($this->valorCelda($hoja, $mapa['descripcion'].$fila));
            $familia = isset($mapa['observaciones'])
                ? trim($this->valorCelda($hoja, $mapa['observaciones'].$fila))
                : null;
            $cantidad = $this->parseCantidadCelda($hoja, $mapa['stock'].$fila);

            if ($codigo === '' && $descripcion === '') {
                continue;
            }

            if ($codigo === '') {
                if ($descripcion === '') {
                    continue;
                }
                $codigo = '-';
            }

            // Stock "-" o vacío = 0; Idproducto "-" = sin código pero sí puede ingresar
            if ($cantidad <= 0) {
                continue;
            }

            $filas[] = [$codigo, $descripcion, $familia, $cantidad];
        }

        // Precarga en lote de familias y materiales (evita 2-3 consultas por fila). La resolución sigue
        // siendo fila a fila y en el mismo orden, creando familias/materiales faltantes igual que antes.
        $this->precargarCatalogos($filas);

        foreach ($filas as [$codigo, $descripcion, $familia, $cantidad]) {
            $material = $this->resolverMaterial($codigo, $descripcion, $familia);

            $lineas[] = [
                'id_material_pl' => (int) $material['id_material_pl'],
                'codigo_pl' => $material['codigo_pl'],
                'nom_material' => $material['nom_material'],
                'cantidad' => $cantidad,
                'nom_familia' => $material['nom_familia'],
            ];
        }

        if ($lineas === []) {
            return [
                'ok' => false,
                'id_almacen' => (int) $almacen->idAlmacen,
                'codigo_almacen' => $almacen->codigo,
                'lineas' => [],
                'advertencias' => $advertencias,
                'error' => 'No hay líneas con Idproducto y stock mayor a cero.',
            ];
        }

        return [
            'ok' => true,
            'id_almacen' => (int) $almacen->idAlmacen,
            'codigo_almacen' => $almacen->codigo,
            'lineas' => $lineas,
            'advertencias' => $advertencias,
        ];
    }

    private function detectarFilaEncabezado(Worksheet $hoja): ?int
    {
        for ($fila = 1; $fila <= 15; $fila++) {
            $textos = [];
            $ultimaCol = Coordinate::columnIndexFromString($hoja->getHighestDataColumn($fila));

            for ($indice = 1; $indice <= max($ultimaCol, 6); $indice++) {
                $col = Coordinate::stringFromColumnIndex($indice);
                $textos[] = mb_strtolower(trim($this->valorCelda($hoja, $col.$fila)));
            }

            $unidos = implode('|', $textos);
            if (str_contains($unidos, 'idproducto') && str_contains($unidos, 'stock')) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function mapaColumnas(Worksheet $hoja, int $filaEncabezado): array
    {
        $columnas = [];
        $ultimaCol = Coordinate::columnIndexFromString($hoja->getHighestDataColumn($filaEncabezado));

        for ($indice = 1; $indice <= max($ultimaCol, 6); $indice++) {
            $col = Coordinate::stringFromColumnIndex($indice);
            $titulo = trim($this->valorCelda($hoja, $col.$filaEncabezado));
            if ($titulo !== '') {
                $columnas[] = ['col' => $col, 'titulo' => $titulo];
            }
        }

        $mapa = [];
        foreach (self::ALIAS as $interna => $aliases) {
            foreach ($aliases as $alias) {
                foreach ($columnas as $columna) {
                    if ($this->coincideEncabezado($columna['titulo'], $alias)) {
                        $mapa[$interna] = $columna['col'];
                        break 2;
                    }
                }
            }
        }

        return $mapa;
    }

    private function coincideEncabezado(string $titulo, string $alias): bool
    {
        return $this->claveEncabezado($titulo) === $this->claveEncabezado($alias);
    }

    private function claveEncabezado(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $texto);
        $texto = preg_replace('/[^a-z0-9]+/u', '', $texto) ?? '';

        return $texto;
    }

    private function valorCelda(Worksheet $hoja, string $celda): string
    {
        $valor = $hoja->getCell($celda)->getCalculatedValue();

        return trim((string) ($valor ?? ''));
    }

    private function valorCodigoProducto(Worksheet $hoja, string $celda): string
    {
        $cell = $hoja->getCell($celda);
        $valor = $cell->getValue();

        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_int($valor) || is_float($valor)) {
            return sprintf('%.0f', (float) $valor);
        }

        return trim((string) $cell->getFormattedValue());
    }

    private function normalizarCodigo(string $codigo): string
    {
        return MaterialProgramaLookup::normalizarCodigo($codigo);
    }

    private function parseCantidadCelda(Worksheet $hoja, string $celda): float
    {
        return StockExcelFilas::parseCantidadCelda($hoja, $celda);
    }

    /**
     * Carga con un whereIn las familias y los materiales (por CodigoPL) que usa el Excel.
     * Solo se indexan coincidencias exactas; cualquier otro caso (mayúsculas, espacios, acentos que
     * la collation considera iguales) cae al camino original de consulta individual.
     *
     * @param  list<array{0: string, 1: string, 2: string|null, 3: float}>  $filas
     */
    private function precargarCatalogos(array $filas): void
    {
        $this->familiasExistentes = [];
        $this->materialesPorCodigo = [];

        $familias = [];
        $codigos = [];
        foreach ($filas as [$codigo, , $familia]) {
            if ($familia !== null && $familia !== '') {
                $familias[$familia] = true;
            }
            if ($codigo !== '' && $codigo !== '-') {
                $codigos[$codigo] = true;
            }
        }

        foreach (array_chunk(array_keys($familias), 500) as $bloque) {
            Familia::query()
                ->whereIn('nomFamilia', $bloque)
                ->pluck('nomFamilia')
                ->each(function ($nombre) {
                    $this->familiasExistentes[(string) $nombre] = true;
                });
        }

        foreach (array_chunk(array_map('strval', array_keys($codigos)), 500) as $bloque) {
            MaterialProgramaPL::query()
                ->whereIn('CodigoPL', $bloque)
                ->orderBy('idMaterialPL')
                ->get()
                ->each(function (MaterialProgramaPL $material) {
                    $clave = (string) $material->CodigoPL;
                    $this->materialesPorCodigo[$clave] ??= $material;
                });
        }
    }

    /**
     * @return array{id_material_pl: int, codigo_pl: string, nom_material: string, nom_familia: string|null}
     */
    private function resolverMaterial(string $codigo, string $descripcion, ?string $familia): array
    {
        if ($familia !== null && $familia !== '' && ! isset($this->familiasExistentes[$familia])) {
            Familia::query()->firstOrCreate(
                ['nomFamilia' => $familia],
                ['Estado' => 1]
            );
            $this->familiasExistentes[$familia] = true;
        }

        $descripcionMaterial = $descripcion !== '' ? $descripcion : $codigo;
        $precargado = $codigo !== '-' ? ($this->materialesPorCodigo[$codigo] ?? null) : null;

        if ($precargado !== null) {
            $material = MaterialProgramaLookup::resolverPrecargado($precargado, $codigo, $descripcionMaterial);
        } else {
            $material = MaterialProgramaLookup::resolverPorCodigo($codigo, $descripcionMaterial);
        }

        // Mantiene la caché alineada con lo que quedó en BD (material creado, reactivado o renombrado).
        $clave = (string) $material->CodigoPL;
        if ($clave === $codigo || isset($this->materialesPorCodigo[$clave])) {
            $this->materialesPorCodigo[$clave] = $material;
        }

        return [
            'id_material_pl' => (int) $material->idMaterialPL,
            'codigo_pl' => (string) $material->CodigoPL,
            'nom_material' => (string) $material->nomMaterial,
            'nom_familia' => $familia !== '' ? $familia : null,
        ];
    }
}
