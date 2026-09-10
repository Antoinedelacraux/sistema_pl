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
use App\Models\Programas\ProgramaGeneral;
use App\Models\Programas\ProgramaPresentacionParam;
use App\Models\Programas\Situado;
use App\Models\Programas\Uso;
use App\Models\Programas\Variedad;
use App\Support\CantidadCajaSupport;
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
    /** @var array<string, int> */
    private array $cacheCatalogo = [];

    /** @var array<string, int> */
    private array $cacheCantidadCaja = [];

    /** @var array<string, true> */
    private array $cacheConformacionUpsert = [];

    /** @var array<string, int> */
    private array $cacheProgramaGeneralId = [];

    /** @var array<string, MaterialProgramaPL> */
    private array $cacheMaterialPl = [];

    private ?int $idSituadoImport = null;

    /** @var list<string> Columnas requeridas */
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

    /** @var list<string> Columnas opcionales (no impiden la importación si faltan) */
    public const COLUMNAS_OPCIONALES = [
        'Programa General',
        'Codigo Programa General',
        'Conformación Estándar',
    ];

    /** @var array<string, list<string>> nombres exactos del Excel + alias */
    private const ALIAS_EXCEL = [
        'Mercado' => ['MERCADO', 'Mercado', 'Market'],
        'Cliente' => ['Client', 'Cliente', 'client'],
        'Programa General' => ['Programa General', 'ProgramaGeneral', 'General', 'Grupo Programa', 'Prog General'],
        'Codigo Programa General' => [
            'Codigo Programa General', 'Código Programa General', 'CodigoProgramaGeneral',
            'Codigo Pro General', 'Código Pro General', 'CodigoProGeneral',
        ],
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
        'Conformación Estándar' => [
            'Conformacion Estándar', 'Conformacion Estandar', 'Conformación Estándar', 'Conformación Estandar',
            'Conformacion Estándar', 'Conformacion Standard',
        ],
    ];

    private const SITUADO_IMPORT_DEFAULT = 'Dentro';

    private const OBSERVACION_VACIA = 'Oficial';

    /**
     * Importa Receta desde Excel (solo inserción). La receta debe estar vacía; use «Vaciar» antes.
     *
     * @return array{leidas: int, insertadas: int, duplicadas: int, omitidas: int, total: int}
     */
    public function importar(UploadedFile $archivo): array
    {
        @ini_set('max_execution_time', '300');
        set_time_limit(300);

        if (ProgramaFila::query()->exists()) {
            throw new InvalidArgumentException(
                'La receta ya tiene datos. Use «Vaciar» antes de importar el Excel.'
            );
        }

        $this->resetCachesImport();
        $filasExcel = $this->leerExcel($archivo);
        $this->precargarCatalogos();
        $this->precargarMateriales($filasExcel);

        $insertadas = 0;
        $duplicadas = 0;
        $omitidas = 0;
        $vistasEnExcel = [];

        DB::transaction(function () use (
            $filasExcel,
            &$insertadas,
            &$duplicadas,
            &$omitidas,
            &$vistasEnExcel
        ) {
            $nroFila = 0;

            foreach ($filasExcel as $filaExcel) {
                if (! $this->codigoPlanificacionValido($filaExcel['Codigo Planificación'])) {
                    $omitidas++;

                    continue;
                }

                $matchKey = $this->matchKeyDesdeExcel($filaExcel);
                if (isset($vistasEnExcel[$matchKey])) {
                    $duplicadas++;

                    continue;
                }

                $nroFila++;
                $this->insertarFila($filaExcel, $nroFila);
                $vistasEnExcel[$matchKey] = true;
                $insertadas++;
            }
        });

        Cache::forget('catalogo.programas_consumo');

        return [
            'leidas' => count($filasExcel),
            'insertadas' => $insertadas,
            'duplicadas' => $duplicadas,
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
            $reader->setReadDataOnly(true);
        }
        $nombresHojas = $reader->listWorksheetNames($ruta);
        $hojaObjetivo = $this->elegirHojaExcel($nombresHojas);
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$hojaObjetivo]);
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

            // Columnas opcionales
            foreach (self::COLUMNAS_OPCIONALES as $columna) {
                if (isset($mapaColumnas[$columna])) {
                    $linea[$columna] = $this->valorCeldaTexto($hoja, $mapaColumnas[$columna].$numeroFila);
                } else {
                    $linea[$columna] = '';
                }
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

        // Validar solo columnas requeridas
        $requeridas = count(self::COLUMNAS);
        $encontradas = count(array_intersect_key($mejor['mapa'] ?? [], array_flip(self::COLUMNAS)));

        if ($mejor === null || $encontradas < $requeridas) {
            $requeridosAlias = array_filter(
                self::ALIAS_EXCEL,
                fn ($k) => in_array($k, self::COLUMNAS, true),
                ARRAY_FILTER_USE_KEY
            );
            $faltantes = array_keys(array_diff_key($requeridosAlias, $mejor['mapa'] ?? []));
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
     * @param  array<string, string>  $filaExcel
     */
    private function matchKeyDesdeExcel(array $filaExcel): string
    {
        return $this->armarFingerprint([
            trim($filaExcel['Programa General'] ?? ''),
            trim($filaExcel['Codigo Programa General'] ?? ''),
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

    private function normalizarCantidad(string $valor, string $nomFamilia): float
    {
        return CantidadCajaSupport::normalizarValor($valor, $nomFamilia);
    }

    /**
     * @param  array<string, string>  $filaExcel
     */
    private function insertarFila(array $filaExcel, int $nroFila): void
    {
        ProgramaFila::query()->create($this->datosFilaDesdeExcel($filaExcel, $nroFila));
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

        $nomFamilia = $this->normalizarFamiliaImport($filaExcel['Material']);
        $cantidadValor = $this->normalizarCantidad($filaExcel['Cantidad x Caja'], $nomFamilia);
        $cantidad = $this->resolverCantidadCaja($cantidadValor, $nomFamilia);

        $idPrograma = $this->catalogoId(ProgramaCatalogo::class, 'nomPrograma', $filaExcel['Programa']);
        $idPresentacion = $this->catalogoId(Presentacion::class, 'nomPresentacion', trim($filaExcel['Presentación']));

        $nombreGeneral = trim($filaExcel['Programa General'] ?? '');
        $codigoGeneral = trim($filaExcel['Codigo Programa General'] ?? '');
        $idProgramaGeneral = ($nombreGeneral !== '' || $codigoGeneral !== '')
            ? $this->resolverProgramaGeneralId($nombreGeneral, $codigoGeneral)
            : null;

        $this->upsertConformacionDesdeExcel(
            $idPrograma,
            $idPresentacion,
            $filaExcel['Conformación Estándar'] ?? ''
        );

        return [
            'nroFila' => $nroFila,
            'idMercado' => $this->catalogoId(Mercado::class, 'nomMercado', $filaExcel['Mercado']),
            'idCliente' => $this->catalogoId(Cliente::class, 'nomCliente', $filaExcel['Cliente']),
            'idVariedad' => $this->catalogoId(Variedad::class, 'nomVariedad', $filaExcel['Variedad']),
            'idPrograma' => $idPrograma,
            'idProgramaGeneral' => $idProgramaGeneral,
            'idPresentacion' => $idPresentacion,
            'idMaterialPL' => $material->idMaterialPL,
            'idCantidadCaja' => $cantidad->idCantidadCaja,
            'idUso' => $this->catalogoId(Uso::class, 'nomUso', $this->valorObservacion($filaExcel['Observacion'])),
            'idSituado' => $this->idSituadoImport(),
            'idFamilia' => $this->catalogoId(Familia::class, 'nomFamilia', $nomFamilia),
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

        if (isset($this->cacheMaterialPl[$codigo])) {
            $material = $this->cacheMaterialPl[$codigo];
            $nomMaterial = trim($descripcion) !== '' ? trim($descripcion) : $codigo;
            if ($nomMaterial !== $codigo && $nomMaterial !== $material->nomMaterial) {
                $material->nomMaterial = $nomMaterial;
                $material->save();
            }

            return $material;
        }

        $material = MaterialProgramaLookup::resolverPorCodigo($codigo, $descripcion, reactivar: true, actualizarNombre: true);
        $this->cacheMaterialPl[$codigo] = $material;

        return $material;
    }

    private function upsertConformacionDesdeExcel(int $idPrograma, int $idPresentacion, string $valor): void
    {
        $valor = trim($valor);
        if ($valor === '') {
            return;
        }

        $normalizado = str_replace(',', '.', $valor);
        if (! is_numeric($normalizado)) {
            return;
        }

        $clave = $idPrograma.'|'.$idPresentacion;
        if (isset($this->cacheConformacionUpsert[$clave])) {
            return;
        }

        $conformacion = (float) $normalizado;
        $param = ProgramaPresentacionParam::query()->firstOrNew([
            'idPrograma' => $idPrograma,
            'idPresentacion' => $idPresentacion,
        ]);

        $param->conformacion = $conformacion;
        if (! $param->exists) {
            $param->pallets = 0;
        }
        $param->Estado = Estado::ACTIVO;
        $param->save();

        $this->cacheConformacionUpsert[$clave] = true;
    }

    /**
     * Crea o reutiliza ProgramaGeneral por código (prioridad) o nombre.
     */
    private function resolverProgramaGeneralId(string $nombre, string $codigo = ''): int
    {
        $nombre = trim($nombre);
        $codigo = trim($codigo);

        $cacheKey = mb_strtolower($codigo).'|'.mb_strtolower($nombre);
        if (isset($this->cacheProgramaGeneralId[$cacheKey])) {
            return $this->cacheProgramaGeneralId[$cacheKey];
        }

        $programaGeneral = null;

        if ($codigo !== '') {
            $programaGeneral = ProgramaGeneral::query()
                ->where('CodigoProGeneral', $codigo)
                ->first();
        }

        if (! $programaGeneral && $nombre !== '') {
            $programaGeneral = ProgramaGeneral::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->first();
        }

        if ($programaGeneral) {
            $updates = [];

            if ($codigo !== '' && $programaGeneral->CodigoProGeneral !== $codigo) {
                $codigoOcupado = ProgramaGeneral::query()
                    ->where('CodigoProGeneral', $codigo)
                    ->where('idProgramaGeneral', '!=', $programaGeneral->idProgramaGeneral)
                    ->exists();

                if (! $codigoOcupado) {
                    $updates['CodigoProGeneral'] = $codigo;
                }
            }

            if ($nombre !== '' && $programaGeneral->nombre !== $nombre) {
                $updates['nombre'] = $nombre;
            }

            if ($updates !== []) {
                $programaGeneral->update($updates);
            }
        } else {
            $codigoFinal = $codigo !== '' ? $codigo : $this->generarCodigoProgramaGeneral($nombre);
            $programaGeneral = ProgramaGeneral::query()->create([
                'CodigoProGeneral' => $codigoFinal,
                'nombre' => $nombre !== '' ? $nombre : $codigoFinal,
                'Estado' => Estado::ACTIVO,
            ]);
        }

        $id = (int) $programaGeneral->idProgramaGeneral;
        $this->cacheProgramaGeneralId[$cacheKey] = $id;

        return $id;
    }

    private function generarCodigoProgramaGeneral(string $nombre): string
    {
        $codigo = mb_strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $nombre) ?? '');
        $codigo = mb_substr($codigo, 0, 40);
        $base = $codigo !== '' ? $codigo : 'PG';
        $n = 1;

        while (ProgramaGeneral::query()->where('CodigoProGeneral', $codigo)->exists()) {
            $codigo = $base.'-'.$n++;
        }

        return $codigo;
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

        $clave = $modelo.'|'.$campo.'|'.mb_strtolower($valor);
        if (isset($this->cacheCatalogo[$clave])) {
            return $this->cacheCatalogo[$clave];
        }

        $registro = $modelo::query()->firstOrCreate(
            [$campo => $valor],
            ['Estado' => Estado::ACTIVO]
        );

        $this->cacheCatalogo[$clave] = (int) $registro->getKey();

        return $this->cacheCatalogo[$clave];
    }

    private function idSituadoImport(): int
    {
        if ($this->idSituadoImport !== null) {
            return $this->idSituadoImport;
        }

        $this->idSituadoImport = $this->catalogoId(Situado::class, 'nomSituado', self::SITUADO_IMPORT_DEFAULT);

        return $this->idSituadoImport;
    }

    private function resolverCantidadCaja(float $cantidadValor, string $nomFamilia): CantidadCaja
    {
        $clave = CantidadCajaSupport::clave($cantidadValor, $nomFamilia);
        if (isset($this->cacheCantidadCaja[$clave])) {
            return CantidadCaja::query()->findOrFail($this->cacheCantidadCaja[$clave]);
        }

        $cantidad = CantidadCaja::query()->firstOrCreate(
            ['cantidad' => $clave],
            ['Estado' => Estado::ACTIVO]
        );

        $this->cacheCantidadCaja[$clave] = (int) $cantidad->idCantidadCaja;

        return $cantidad;
    }

    private function resetCachesImport(): void
    {
        $this->cacheCatalogo = [];
        $this->cacheCantidadCaja = [];
        $this->cacheConformacionUpsert = [];
        $this->cacheProgramaGeneralId = [];
        $this->cacheMaterialPl = [];
        $this->idSituadoImport = null;
    }

    /**
     * @param  list<string>  $nombresHojas
     */
    private function elegirHojaExcel(array $nombresHojas): string
    {
        $preferidas = ['Materiales V2', 'Programas', 'Receta', 'Materiales'];
        foreach ($preferidas as $preferida) {
            foreach ($nombresHojas as $nombre) {
                if (mb_strtolower(trim($nombre)) === mb_strtolower(trim($preferida))) {
                    return $nombre;
                }
            }
        }

        return $nombresHojas[0] ?? 'Sheet1';
    }

    private function precargarCatalogos(): void
    {
        $catalogos = [
            Mercado::class => 'nomMercado',
            Cliente::class => 'nomCliente',
            Variedad::class => 'nomVariedad',
            ProgramaCatalogo::class => 'nomPrograma',
            Presentacion::class => 'nomPresentacion',
            Uso::class => 'nomUso',
            Familia::class => 'nomFamilia',
            Situado::class => 'nomSituado',
        ];

        foreach ($catalogos as $modelo => $campo) {
            $pk = (new $modelo)->getKeyName();
            $modelo::query()
                ->select([$pk, $campo])
                ->orderBy($pk)
                ->lazy(500)
                ->each(function ($registro) use ($modelo, $campo) {
                    $valor = trim((string) $registro->getAttribute($campo));
                    if ($valor === '') {
                        return;
                    }

                    $clave = $modelo.'|'.$campo.'|'.mb_strtolower($valor);
                    $this->cacheCatalogo[$clave] = (int) $registro->getKey();
                });
        }

        CantidadCaja::query()
            ->select(['idCantidadCaja', 'cantidad'])
            ->orderBy('idCantidadCaja')
            ->lazy(500)
            ->each(function (CantidadCaja $cantidad) {
                $valor = (float) $cantidad->cantidad;
                foreach ([CantidadCajaSupport::DECIMALES_NORMAL, CantidadCajaSupport::DECIMALES_ALTA] as $decimales) {
                    $clave = number_format($valor, $decimales, '.', '');
                    $this->cacheCantidadCaja[$clave] = (int) $cantidad->idCantidadCaja;
                }
            });
    }

    /**
     * @param  list<array<string, string>>  $filasExcel
     */
    private function precargarMateriales(array $filasExcel): void
    {
        $codigos = [];
        foreach ($filasExcel as $fila) {
            $codigo = MaterialProgramaLookup::normalizarCodigo($fila['Codigo Planificación'] ?? '');
            if ($codigo !== '' && $codigo !== '-') {
                $codigos[$codigo] = true;
            }
        }

        if ($codigos === []) {
            return;
        }

        foreach (array_chunk(array_keys($codigos), 200) as $lote) {
            MaterialProgramaPL::query()
                ->whereIn('CodigoPL', $lote)
                ->orderBy('idMaterialPL')
                ->get()
                ->each(function (MaterialProgramaPL $material) {
                    $this->cacheMaterialPl[$material->CodigoPL] = $material;
                });
        }
    }
}
