<?php

namespace App\Services\Stock;

use App\Models\Programas\Familia;
use App\Models\Stock\Almacen;
use App\Support\MaterialProgramaLookup;
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
        $cell = $hoja->getCell($celda);
        $valor = $cell->getValue();

        if (is_int($valor) || is_float($valor)) {
            return max(0.0, round((float) $valor, 2));
        }

        $texto = trim((string) $cell->getFormattedValue());
        if ($texto === '' || $texto === '-' || $texto === '—') {
            return 0.0;
        }

        $texto = preg_replace('/\s+/u', '', $texto) ?? $texto;

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $texto)) {
            $texto = str_replace(',', '', $texto);
        } elseif (preg_match('/,\d{1,2}$/', $texto)) {
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return max(0.0, round((float) $texto, 2));
    }

    /**
     * @return array{id_material_pl: int, codigo_pl: string, nom_material: string, nom_familia: string|null}
     */
    private function resolverMaterial(string $codigo, string $descripcion, ?string $familia): array
    {
        if ($familia !== null && $familia !== '') {
            Familia::query()->firstOrCreate(
                ['nomFamilia' => $familia],
                ['Estado' => 1]
            );
        }

        $material = MaterialProgramaLookup::resolverPorCodigo(
            $codigo,
            $descripcion !== '' ? $descripcion : $codigo
        );

        return [
            'id_material_pl' => (int) $material->idMaterialPL,
            'codigo_pl' => (string) $material->CodigoPL,
            'nom_material' => (string) $material->nomMaterial,
            'nom_familia' => $familia !== '' ? $familia : null,
        ];
    }
}
