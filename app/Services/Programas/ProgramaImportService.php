<?php

namespace App\Services\Programas;

use App\Models\Estado;
use App\Models\Programas\CantidadCaja;
use App\Models\Programas\Cliente;
use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\Mercado;
use App\Models\Programas\Presentacion;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\Situado;
use App\Models\Programas\Uso;
use App\Models\Programas\Variedad;
use App\Support\FamiliaMaterialSupport;
use App\Support\MaterialProgramaLookup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgramaImportService
{
    /** @var list<string> */
    public const COLUMNAS = [
        'Mercado',
        'Cliente',
        'Programa',
        'Variedad',
        'Presentación',
        'Material',
        'Codigo Planificación',
        'Material Descripcion',
        'Observacion',
        'Cantidad x Caja',
    ];

    /** @var array<string, list<string>> nombres exactos del Excel + alias */
    private const ALIAS_EXCEL = [
        'Mercado' => ['MERCADO', 'Mercado', 'Market'],
        'Cliente' => ['Client', 'Cliente', 'client'],
        'Programa' => ['Program', 'Programa', 'program', 'Producto', 'producto'],
        'Presentación' => ['Product', 'Presentación', 'Presentacion', 'presentacion', 'Producto', 'producto'],
        'Variedad' => ['Variety', 'Variedad', 'variety'],
        'Material' => ['Material', 'Familia', 'familia'],
        'Codigo Planificación' => [
            'Code', 'Codigo Planificación', 'Código de Planificación', 'Codigo Planificacion',
            'Código PL', 'Codigo PL', 'CodigoPL', 'Código Planeamiento', 'Codigo Planeamiento',
        ],
        'Material Descripcion' => [
            'Material Description', 'Material Descripcion', 'Material Descripción', 'Materiales',
            'Descripción material', 'Descripcion material', 'Descripcion Material',
        ],
        'Observacion' => ['Observations', 'Observacion', 'Observación', 'Uso'],
        'Cantidad x Caja' => ['Cantidad x caja', 'Cantidad x Caja', 'CANTIDAD X CAJA', 'Cantidad X Caja'],
    ];

    private const SITUADO_IMPORT_DEFAULT = 'Dentro';

    private const OBSERVACION_VACIA = 'Oficial';

    /**
     * Importa Receta desde Excel: inserta filas nuevas y actualiza existentes si cambió cantidad,
     * descripción u otros datos. La clave de coincidencia es programa + producto + código PL + uso + familia.
     *
     * @return array{leidas: int, insertadas: int, actualizadas: int, sin_cambios: int, omitidas: int, total: int}
     */
    public function importar(UploadedFile $archivo): array
    {
        $filasExcel = $this->leerExcel($archivo);
        $indiceExistentes = $this->indiceFilasExistentes();

        $insertadas = 0;
        $actualizadas = 0;
        $sinCambios = 0;
        $omitidas = 0;

        DB::transaction(function () use (
            $filasExcel,
            $indiceExistentes,
            &$insertadas,
            &$actualizadas,
            &$sinCambios,
            &$omitidas
        ) {
            $nroFila = (int) ProgramaFila::query()->max('nroFila');

            foreach ($filasExcel as $filaExcel) {
                if (! $this->codigoPlanificacionValido($filaExcel['Codigo Planificación'])) {
                    $omitidas++;

                    continue;
                }

                $matchKey = $this->matchKeyDesdeExcel($filaExcel);
                $existente = $indiceExistentes[$matchKey] ?? null;

                if ($existente instanceof ProgramaFila) {
                    if ($this->actualizarFila($existente, $filaExcel)) {
                        $actualizadas++;
                    } else {
                        $sinCambios++;
                    }

                    continue;
                }

                $nroFila++;
                $nueva = $this->insertarFila($filaExcel, $nroFila);
                $indiceExistentes[$matchKey] = $nueva;
                $insertadas++;
            }
        });

        Cache::forget('catalogo.programas_consumo');

        return [
            'leidas' => count($filasExcel),
            'insertadas' => $insertadas,
            'actualizadas' => $actualizadas,
            'sin_cambios' => $sinCambios,
            'omitidas' => $omitidas,
            'total' => ProgramaFila::query()->count(),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function leerExcel(UploadedFile $archivo): array
    {
        $ruta = $archivo->getRealPath();
        if ($ruta === false) {
            throw new InvalidArgumentException('No se pudo leer el archivo Excel.');
        }

        $reader = IOFactory::createReaderForFile($ruta);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(false);
        }
        $spreadsheet = $reader->load($ruta);
        $layout = $this->resolverLayout($spreadsheet);
        $hoja = $layout['hoja'];
        $mapaColumnas = $layout['mapa'];
        $filaDatos = $layout['fila_datos'];
        $filas = [];
        $ultimaFila = $hoja->getHighestDataRow();

        for ($numeroFila = $filaDatos; $numeroFila <= $ultimaFila; $numeroFila++) {
            $linea = [];
            $vacia = true;

            foreach (self::COLUMNAS as $columna) {
                $col = $mapaColumnas[$columna];
                $comoEntero = $columna === 'Codigo Planificación';
                $comoNumero = $comoEntero || $columna === 'Cantidad x Caja';
                $valor = $this->valorCeldaTexto($hoja, $col.$numeroFila, $comoNumero, $comoEntero);
                if ($valor !== '') {
                    $vacia = false;
                }
                $linea[$columna] = $valor;
            }

            if (! $vacia) {
                $filas[] = $linea;
            }
        }

        if ($filas === []) {
            throw new InvalidArgumentException('El Excel no tiene filas de datos en la hoja Programas.');
        }

        return $filas;
    }

    /**
     * @return array{hoja: Worksheet, mapa: array<string, string>, fila_datos: int}
     */
    private function resolverLayout(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): array
    {
        $mejor = null;

        foreach ($spreadsheet->getWorksheetIterator() as $hoja) {
            for ($fila = 1; $fila <= 30; $fila++) {
                $mapa = $this->mapaColumnasEnFila($hoja, $fila);
                $coincidencias = count($mapa);
                if ($coincidencias === 0) {
                    continue;
                }

                $esProgramas = mb_strtolower(trim($hoja->getTitle())) === 'programas';
                $puntaje = ($coincidencias * 100) + ($esProgramas ? 5 : 0);

                if ($mejor === null || $puntaje > $mejor['puntaje']) {
                    $mejor = [
                        'hoja' => $hoja,
                        'mapa' => $mapa,
                        'fila' => $fila,
                        'puntaje' => $puntaje,
                        'coincidencias' => $coincidencias,
                        'encabezados' => $this->encabezadosEnFila($hoja, $fila),
                    ];
                }
            }
        }

        if ($mejor === null || $mejor['coincidencias'] < count(self::COLUMNAS)) {
            $faltantes = array_keys(array_diff_key(self::ALIAS_EXCEL, $mejor['mapa'] ?? []));
            $vistos = isset($mejor['encabezados']) ? implode(', ', $mejor['encabezados']) : '(ninguno)';
            throw new InvalidArgumentException(
                'Columnas no encontradas: '.implode(', ', $faltantes)
                .'. Encabezados detectados: '.$vistos
                .'. Use: MERCADO, Client, Program, Product, Variety, Material, Code, Material Description, Observations, Cantidad x caja.'
            );
        }

        return [
            'hoja' => $mejor['hoja'],
            'mapa' => $mejor['mapa'],
            'fila_datos' => $mejor['fila'] + 1,
        ];
    }

    /**
     * @return list<array{col: string, titulo: string}>
     */
    private function columnasConTitulo(Worksheet $hoja, int $fila): array
    {
        $columnas = [];
        $ultimaCol = max(
            Coordinate::columnIndexFromString($hoja->getHighestColumn()),
            Coordinate::columnIndexFromString($hoja->getHighestDataColumn()),
            26
        );

        for ($indice = 1; $indice <= $ultimaCol; $indice++) {
            $col = Coordinate::stringFromColumnIndex($indice);
            $titulo = $this->valorCeldaTexto($hoja, $col.$fila);
            if ($titulo !== '') {
                $columnas[] = ['col' => $col, 'titulo' => $titulo];
            }
        }

        return $columnas;
    }

    /**
     * @return array<string, string> columna lógica => letra Excel
     */
    private function mapaColumnasEnFila(Worksheet $hoja, int $filaEncabezado): array
    {
        $columnas = $this->columnasConTitulo($hoja, $filaEncabezado);
        $mapa = [];

        foreach (['Material Descripcion', 'Material'] as $prioritaria) {
            $col = $this->buscarColumna($columnas, self::ALIAS_EXCEL[$prioritaria]);
            if ($col !== null) {
                $mapa[$prioritaria] = $col;
            }
        }

        foreach (self::ALIAS_EXCEL as $interna => $aliases) {
            if (isset($mapa[$interna])) {
                continue;
            }

            $col = $this->buscarColumna($columnas, $aliases);
            if ($col !== null) {
                $mapa[$interna] = $col;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<array{col: string, titulo: string}>  $columnas
     * @param  list<string>  $aliases
     */
    private function buscarColumna(array $columnas, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            foreach ($columnas as $columna) {
                if ($this->coincideEncabezado($columna['titulo'], $alias)) {
                    return $columna['col'];
                }
            }
        }

        return null;
    }

    private function coincideEncabezado(string $titulo, string $alias): bool
    {
        if (mb_strtolower(trim($titulo)) === mb_strtolower(trim($alias))) {
            return true;
        }

        return $this->claveEncabezado($titulo) === $this->claveEncabezado($alias);
    }

    /**
     * @return list<string>
     */
    private function encabezadosEnFila(Worksheet $hoja, int $fila): array
    {
        return array_map(
            fn (array $columna) => $columna['titulo'],
            $this->columnasConTitulo($hoja, $fila)
        );
    }

    private function valorCeldaTexto(Worksheet $hoja, string $celda, bool $comoNumero = false, bool $comoEntero = false): string
    {
        $cell = $hoja->getCell($celda);

        foreach ($hoja->getMergeCells() as $rango) {
            if ($cell->isInRange($rango)) {
                $cell = $hoja->getCell(explode(':', $rango)[0]);
                break;
            }
        }

        $valor = $cell->getCalculatedValue();
        if ($comoNumero && is_numeric($valor)) {
            return $comoEntero
                ? MaterialProgramaLookup::normalizarCodigo(sprintf('%.0f', (float) $valor))
                : (string) $valor;
        }

        foreach ([$cell->getFormattedValue(), $cell->getCalculatedValue(), $cell->getValue()] as $valorCandidato) {
            if ($valorCandidato === null) {
                continue;
            }

            $texto = trim((string) $valorCandidato);
            if ($texto !== '') {
                if ($comoEntero) {
                    return MaterialProgramaLookup::normalizarCodigo($texto);
                }

                return $texto;
            }
        }

        return '';
    }

    private function claveEncabezado(string $titulo): string
    {
        $titulo = preg_replace('/^\xEF\xBB\xBF/u', '', $titulo) ?? $titulo;
        $titulo = str_replace("\xc2\xa0", ' ', $titulo);
        $titulo = mb_strtolower(trim($titulo));
        $titulo = preg_replace('/\s+/u', ' ', $titulo) ?? $titulo;
        $titulo = $this->sinAcentos($titulo);

        return preg_replace('/[^a-z0-9]/', '', $titulo) ?? '';
    }

    private function sinAcentos(string $texto): string
    {
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $texto
        );

        $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($transliterado !== false) {
            return $transliterado;
        }

        return $texto;
    }

    /**
     * @return array<string, ProgramaFila>
     */
    private function indiceFilasExistentes(): array
    {
        $mapa = [];

        ProgramaFila::query()
            ->with([
                'mercado', 'cliente', 'variedad', 'programa', 'presentacion',
                'material', 'cantidadCaja', 'uso', 'familia',
            ])
            ->orderBy('idProgramaFila')
            ->chunk(300, function ($filas) use (&$mapa) {
                foreach ($filas as $fila) {
                    $clave = $this->matchKeyDesdeFila($fila);
                    if (! isset($mapa[$clave])) {
                        $mapa[$clave] = $fila;
                    }
                }
            });

        return $mapa;
    }

    /**
     * @param  array<string, string>  $filaExcel
     */
    private function matchKeyDesdeExcel(array $filaExcel): string
    {
        return $this->armarFingerprint([
            $filaExcel['Mercado'],
            $filaExcel['Cliente'],
            $filaExcel['Programa'],
            $filaExcel['Variedad'],
            $filaExcel['Presentación'],
            MaterialProgramaLookup::normalizarCodigo($filaExcel['Codigo Planificación']),
            $this->valorObservacion($filaExcel['Observacion']),
            $this->normalizarFamiliaImport($filaExcel['Material']),
        ]);
    }

    private function matchKeyDesdeFila(ProgramaFila $fila): string
    {
        return $this->armarFingerprint([
            $fila->mercado?->nomMercado,
            $fila->cliente?->nomCliente,
            $fila->programa?->nomPrograma,
            $fila->variedad?->nomVariedad,
            $fila->presentacion?->nomPresentacion,
            MaterialProgramaLookup::normalizarCodigo((string) ($fila->material?->CodigoPL ?? '')),
            $fila->uso?->nomUso,
            $this->normalizarFamiliaImport((string) ($fila->familia?->nomFamilia ?? '')),
        ]);
    }

    private function codigoPlanificacionValido(string $codigo): bool
    {
        $codigo = MaterialProgramaLookup::normalizarCodigo($codigo);

        return $codigo !== '' && $codigo !== '-';
    }

    /**
     * @param  list<string|null>  $partes
     */
    private function armarFingerprint(array $partes): string
    {
        return implode('|', array_map(fn ($parte) => $this->normalizarTexto($parte), $partes));
    }

    private function normalizarTexto(?string $valor): string
    {
        return mb_strtolower(trim((string) $valor));
    }

    private function normalizarCantidad(string $valor): string
    {
        $valor = str_replace(',', '.', trim($valor));
        if ($valor === '' || ! is_numeric($valor)) {
            return '0.00';
        }

        return number_format((float) $valor, 2, '.', '');
    }

    /**
     * @param  array<string, string>  $filaExcel
     */
    private function insertarFila(array $filaExcel, int $nroFila): ProgramaFila
    {
        return ProgramaFila::query()->create($this->datosFilaDesdeExcel($filaExcel, $nroFila));
    }

    /**
     * @param  array<string, string>  $filaExcel
     */
    private function actualizarFila(ProgramaFila $fila, array $filaExcel): bool
    {
        $nomMaterialAntes = $fila->material?->nomMaterial;
        $datos = $this->datosFilaDesdeExcel($filaExcel, (int) $fila->nroFila);
        unset($datos['nroFila']);

        $material = MaterialProgramaPL::query()->find($datos['idMaterialPL']);
        $materialCambio = $material && $nomMaterialAntes !== $material->nomMaterial;

        $cambioFila = false;
        foreach ($datos as $campo => $valor) {
            if ((string) $fila->getAttribute($campo) !== (string) $valor) {
                $cambioFila = true;
                break;
            }
        }

        if ($cambioFila) {
            $fila->update($datos);
            $fila->refresh();
        }

        return $cambioFila || $materialCambio;
    }

    /**
     * @param  array<string, string>  $filaExcel
     * @return array<string, int|string>
     */
    private function datosFilaDesdeExcel(array $filaExcel, int $nroFila): array
    {
        $material = $this->resolverMaterialProgramaPl(
            trim($filaExcel['Codigo Planificación']),
            trim($filaExcel['Material Descripcion'])
        );

        $cantidadValor = (float) $this->normalizarCantidad($filaExcel['Cantidad x Caja']);
        $cantidad = CantidadCaja::query()->firstOrCreate(
            ['cantidad' => $cantidadValor],
            ['Estado' => Estado::ACTIVO]
        );

        return [
            'nroFila' => $nroFila,
            'idMercado' => $this->catalogoId(Mercado::class, 'nomMercado', $filaExcel['Mercado']),
            'idCliente' => $this->catalogoId(Cliente::class, 'nomCliente', $filaExcel['Cliente']),
            'idVariedad' => $this->catalogoId(Variedad::class, 'nomVariedad', $filaExcel['Variedad']),
            'idPrograma' => $this->catalogoId(ProgramaCatalogo::class, 'nomPrograma', $filaExcel['Programa']),
            'idPresentacion' => $this->catalogoId(Presentacion::class, 'nomPresentacion', trim($filaExcel['Presentación'])),
            'idMaterialPL' => $material->idMaterialPL,
            'idCantidadCaja' => $cantidad->idCantidadCaja,
            'idUso' => $this->catalogoId(Uso::class, 'nomUso', $this->valorObservacion($filaExcel['Observacion'])),
            'idSituado' => $this->catalogoId(Situado::class, 'nomSituado', self::SITUADO_IMPORT_DEFAULT),
            'idFamilia' => $this->catalogoId(Familia::class, 'nomFamilia', $this->normalizarFamiliaImport($filaExcel['Material'])),
            'Estado' => Estado::ACTIVO,
        ];
    }

    private function valorObservacion(string $valor): string
    {
        $valor = trim($valor);

        return $valor !== '' ? $valor : self::OBSERVACION_VACIA;
    }

    private function normalizarFamiliaImport(string $valor): string
    {
        $valor = trim($valor);
        $norm = FamiliaMaterialSupport::normalizar($valor);

        if (in_array($norm, ['cajas', 'caja'], true)) {
            return 'Box';
        }

        return $valor;
    }

    /** Reutiliza o crea MaterialProgramaPL; en reimportación actualiza la descripción. */
    private function resolverMaterialProgramaPl(string $codigo, string $descripcion): MaterialProgramaPL
    {
        $codigo = MaterialProgramaLookup::normalizarCodigo($codigo);
        if ($codigo === '' || $codigo === '-') {
            throw new InvalidArgumentException('Código de planificación vacío.');
        }

        return MaterialProgramaLookup::resolverPorCodigo($codigo, $descripcion, reactivar: true, actualizarNombre: true);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     */
    private function catalogoId(string $modelo, string $campo, string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '') {
            throw new InvalidArgumentException("Valor vacío para {$campo}.");
        }

        $registro = $modelo::query()->firstOrCreate(
            [$campo => $valor],
            ['Estado' => Estado::ACTIVO]
        );

        return (int) $registro->getKey();
    }
}
