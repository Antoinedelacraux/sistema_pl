<?php

namespace App\Services\Proveedores;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\ProgramaFila;
use App\Support\MaterialProgramaLookup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedorImportService
{
    private const ALIAS = [
        'codigo_pl' => [
            'Codigo PL', 'Código PL', 'CodigoPL', 'Código Planeamiento', 'Codigo Planeamiento',
            'Code', 'Código de Planificación', 'Codigo Planificacion',
        ],
        'codigo_proveedor' => [
            'Codigo proveedor', 'Código proveedor', 'Codigo Proveedor', 'Código Proveedor',
            'Code proveedor', 'Código Proveedor',
        ],
        'nom_material' => [
            'Material proveedor', 'Material Proveedor', 'Nombre material', 'Nombre Material',
            'Nom material', 'Material del proveedor',
        ],
    ];

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   resumen: array{leidas: int, validas: int, sin_pl: int, duplicadas: int, invalidas: int}
     * }
     */
    public function preview(UploadedFile $archivo): array
    {
        $filasExcel = $this->leerExcel($archivo);

        $filas = [];
        $vistasPl = [];
        $validas = 0;
        $sinPl = 0;
        $duplicadas = 0;
        $invalidas = 0;

        foreach ($filasExcel as $item) {
            $filaExcel = $item['fila'];
            $codigoPlRaw = $item['codigo_pl'];
            $codigoProv = trim($item['codigo_proveedor']);
            $nomMaterial = trim($item['nom_material']);

            $base = [
                'fila_excel' => $filaExcel,
                'codigo_pl' => MaterialProgramaLookup::normalizarCodigo($codigoPlRaw),
                'codigo_proveedor' => $codigoProv,
                'nom_material' => $nomMaterial,
                'id_material_pl' => null,
                'nom_material_pl' => null,
                'nom_familia' => null,
            ];

            if ($codigoProv === '' || $nomMaterial === '') {
                $invalidas++;
                $filas[] = array_merge($base, [
                    'estado' => 'invalido',
                    'mensaje' => 'Código proveedor y material son obligatorios.',
                ]);

                continue;
            }

            $codigoPl = $base['codigo_pl'];
            if ($codigoPl === '' || $codigoPl === '-') {
                $sinPl++;
                $filas[] = array_merge($base, [
                    'estado' => 'sin_pl',
                    'mensaje' => 'Código PL vacío.',
                ]);

                continue;
            }

            if (isset($vistasPl[$codigoPl])) {
                $duplicadas++;
                $filas[] = array_merge($base, [
                    'estado' => 'duplicado',
                    'mensaje' => 'Código PL repetido en el Excel (fila '.$vistasPl[$codigoPl].').',
                ]);

                continue;
            }

            $material = MaterialProgramaLookup::porCodigo($codigoPl, soloActivos: false);
            if ($material === null) {
                $sinPl++;
                $filas[] = array_merge($base, [
                    'estado' => 'sin_pl',
                    'mensaje' => 'Código PL no encontrado en planeamiento.',
                ]);

                continue;
            }

            $vistasPl[$codigoPl] = $filaExcel;
            $validas++;
            $filas[] = array_merge($base, [
                'estado' => 'ok',
                'mensaje' => '',
                'id_material_pl' => (int) $material->idMaterialPL,
                'nom_material_pl' => $material->nomMaterial,
                'nom_familia' => $this->nomFamiliaMaterial($material),
            ]);
        }

        return [
            'filas' => $filas,
            'resumen' => [
                'leidas' => count($filasExcel),
                'validas' => $validas,
                'sin_pl' => $sinPl,
                'duplicadas' => $duplicadas,
                'invalidas' => $invalidas,
            ],
        ];
    }

    /**
     * @param  list<array{id_material_pl: int, codigo: string, nom_material: string}>  $filas
     * @return array{guardadas: int, omitidas: int}
     */
    public function confirmar(string $nomProveedor, array $filas, ProveedorGridService $gridService): array
    {
        $guardadas = 0;
        $omitidas = 0;

        DB::transaction(function () use ($nomProveedor, $filas, $gridService, &$guardadas, &$omitidas) {
            foreach ($filas as $fila) {
                $idMaterial = (int) ($fila['id_material_pl'] ?? 0);
                if ($idMaterial <= 0) {
                    $omitidas++;

                    continue;
                }

                $material = MaterialProgramaPL::query()->find($idMaterial);
                if ($material === null || MaterialProgramaLookup::normalizarCodigo($material->CodigoPL) === '-') {
                    $omitidas++;

                    continue;
                }

                $gridService->crear([
                    'nom_proveedor' => $nomProveedor,
                    'id_material_pl' => $idMaterial,
                    'codigo' => trim((string) ($fila['codigo'] ?? '')),
                    'nom_material' => trim((string) ($fila['nom_material'] ?? '')),
                ]);
                $guardadas++;
            }
        });

        return [
            'guardadas' => $guardadas,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @return list<array{fila: int, codigo_pl: string, codigo_proveedor: string, nom_material: string}>
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

        $spreadsheet = $reader->load($ruta);
        $hoja = $spreadsheet->getSheet(0);
        $mapa = $this->mapaColumnas($hoja);

        $filaDatos = 2;
        $ultima = $hoja->getHighestDataRow();
        $filas = [];

        for ($num = $filaDatos; $num <= $ultima; $num++) {
            $codigoPl = $this->valorCelda($hoja, $mapa['codigo_pl'].$num, true);
            $codigoProv = $this->valorCelda($hoja, $mapa['codigo_proveedor'].$num);
            $nomMaterial = $this->valorCelda($hoja, $mapa['nom_material'].$num);

            if ($codigoPl === '' && $codigoProv === '' && $nomMaterial === '') {
                continue;
            }

            $filas[] = [
                'fila' => $num,
                'codigo_pl' => $codigoPl,
                'codigo_proveedor' => $codigoProv,
                'nom_material' => $nomMaterial,
            ];
        }

        if ($filas === []) {
            throw new InvalidArgumentException('El Excel no tiene filas de datos.');
        }

        return $filas;
    }

    /**
     * @return array{codigo_pl: string, codigo_proveedor: string, nom_material: string}
     */
    private function mapaColumnas(Worksheet $hoja): array
    {
        $mapa = [];
        $filaEncabezado = 1;
        $ultimaCol = Coordinate::columnIndexFromString($hoja->getHighestColumn());

        for ($i = 1; $i <= max($ultimaCol, 10); $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $titulo = trim((string) $hoja->getCell($col.$filaEncabezado)->getCalculatedValue());
            if ($titulo === '') {
                continue;
            }

            foreach (self::ALIAS as $logica => $aliases) {
                if (isset($mapa[$logica])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($this->coincideEncabezado($titulo, $alias)) {
                        $mapa[$logica] = $col;
                        break 2;
                    }
                }
            }
        }

        $faltantes = array_diff(array_keys(self::ALIAS), array_keys($mapa));
        if ($faltantes !== []) {
            throw new InvalidArgumentException(
                'Columnas requeridas: Código PL, Código proveedor, Material proveedor (fila 1).'
            );
        }

        return [
            'codigo_pl' => $mapa['codigo_pl'],
            'codigo_proveedor' => $mapa['codigo_proveedor'],
            'nom_material' => $mapa['nom_material'],
        ];
    }

    private function coincideEncabezado(string $titulo, string $alias): bool
    {
        $norm = fn (string $t) => preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($t))) ?? '';

        return $norm($titulo) === $norm($alias);
    }

    private function valorCelda(Worksheet $hoja, string $celda, bool $comoEntero = false): string
    {
        $valor = $hoja->getCell($celda)->getCalculatedValue();
        if ($comoEntero && is_numeric($valor)) {
            return MaterialProgramaLookup::normalizarCodigo(sprintf('%.0f', (float) $valor));
        }

        return trim((string) $valor);
    }

    private function nomFamiliaMaterial(MaterialProgramaPL $material): ?string
    {
        $idFamilia = ProgramaFila::query()
            ->where('idMaterialPL', $material->idMaterialPL)
            ->whereNotNull('idFamilia')
            ->value('idFamilia');

        if (! $idFamilia) {
            return null;
        }

        return Familia::query()->whereKey($idFamilia)->value('nomFamilia');
    }
}
