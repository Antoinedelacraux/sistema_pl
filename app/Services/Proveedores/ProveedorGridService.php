<?php

namespace App\Services\Proveedores;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\ProveedorCatalogo;
use App\Models\Programas\ProgramaFila;
use App\Models\Proveedores\Proveedor;
use App\Support\GridPagination;
use App\Support\MaterialProgramaLookup;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProveedorGridService
{
    private const CACHE_TTL = 600;

    public function filasGrilla(array $filtros = []): Collection
    {
        $registros = $this->queryBase($filtros)
            ->with(['familia', 'materialPl'])
            ->orderBy('nroFila')
            ->get();

        return $this->agruparFilas($registros);
    }

    public function filasGrillaPaginadas(array $filtros, Request $request): LengthAwarePaginator
    {
        $perPage = GridPagination::PER_PAGE;
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $base = $this->queryBase($filtros);

        $agrupado = (clone $base)
            ->select('nroFila')
            ->groupBy('nroFila');

        $pagina = DB::query()
            ->fromSub($agrupado, 'filas_proveedor')
            ->select('nroFila')
            ->selectRaw('COUNT(*) OVER() AS total_filas')
            ->orderBy('nroFila')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $total = (int) ($pagina->first()->total_filas ?? 0);

        $nroFilasPage = $pagina
            ->pluck('nroFila')
            ->map(fn ($nro) => (int) $nro)
            ->values()
            ->all();

        if ($nroFilasPage === []) {
            $items = collect();
        } else {
            $registros = Proveedor::query()
                ->with(['familia', 'materialPl'])
                ->where('estado', 1)
                ->whereIn('nroFila', $nroFilasPage)
                ->orderBy('nroFila')
                ->get();

            $agrupadas = $this->agruparFilas($registros)->keyBy('nroFila');
            $items = collect($nroFilasPage)
                ->map(fn (int $nro) => $agrupadas->get($nro))
                ->filter()
                ->values();
        }

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @return array{columnasProveedor: array<int, array{nombre: string, key: string}>, columnasConfig: array<int, array<string, mixed>>}
     */
    public function metadatosColumnas(): array
    {
        return Cache::remember('proveedor.metadatos_columnas.v2', self::CACHE_TTL, function () {
            $columnasProveedor = $this->consultarColumnasProveedor();
            $columnasConfig = $this->armarColumnasConfig($columnasProveedor);

            return [
                'columnasProveedor' => $columnasProveedor,
                'columnasConfig' => $columnasConfig,
            ];
        });
    }

    public function columnasProveedor(): array
    {
        return $this->metadatosColumnas()['columnasProveedor'];
    }

    public function columnasConfig(): array
    {
        return $this->metadatosColumnas()['columnasConfig'];
    }

    public static function claveColumnaProveedor(string $nombre): string
    {
        return 'prov-'.substr(md5(mb_strtoupper(trim($nombre))), 0, 10);
    }

    public function catalogos(): array
    {
        return Cache::remember('proveedor.catalogos.v2', self::CACHE_TTL, function () {
            $nombresProveedor = ProveedorCatalogo::query()
                ->activo()
                ->orderBy('nomProveedor')
                ->pluck('nomProveedor')
                ->values()
                ->all();

            return [
                'familias' => Familia::query()->activo()->orderBy('nomFamilia')->get(['idFamilia', 'nomFamilia']),
                // Lista completa se resuelve vía AJAX (material-por-codigo / material-por-nombre).
                'materiales' => collect(),
                'nombresProveedor' => $nombresProveedor,
            ];
        });
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        return MaterialProgramaLookup::porCodigo($codigo, soloActivos: false);
    }

    /**
     * @return array{materiales: list<array{id_material_pl: int, codigo_pl: string, nom_material: string}>, hay_mas: bool}
     */
    public function buscarMateriales(string $termino, int $limite = 80): array
    {
        return MaterialProgramaLookup::buscar($termino, $limite, 200);
    }

    public function materialPorNombre(string $nombre): ?MaterialProgramaPL
    {
        $nombre = trim($nombre);
        if (mb_strlen($nombre) < 3) {
            return null;
        }

        return MaterialProgramaPL::query()
            ->activo()
            ->where('nomMaterial', 'like', '%'.$nombre.'%')
            ->orderByDesc('idMaterialPL')
            ->first();
    }

    public function crear(array $datos): Proveedor
    {
        $idMaterialPL = (int) ($datos['id_material_pl'] ?? 0);
        $nomProveedor = trim($datos['nom_proveedor']);
        $idFamilia = $this->familiaDesdeMaterial($idMaterialPL);

        $grupo = Proveedor::query()
            ->where('estado', 1)
            ->where('idMaterialPL', $idMaterialPL)
            ->orderBy('nroFila')
            ->first();

        $nroFila = $grupo?->nroFila ?? $this->siguienteNroFilaMaestra();

        $existente = Proveedor::query()
            ->where('estado', 1)
            ->where('nroFila', $nroFila)
            ->whereRaw('UPPER(nomProveedor) = ?', [mb_strtoupper($nomProveedor)])
            ->first();

        $payload = [
            'nroFila' => $nroFila,
            'idFamilia' => $idFamilia,
            'idMaterialPL' => $idMaterialPL,
            'nomProveedor' => $nomProveedor,
            'codigo' => trim($datos['codigo']),
            'nomMaterial' => trim($datos['nom_material']),
        ];

        $this->olvidarCachesCatalogo();

        if ($existente) {
            $existente->update($payload);

            return $existente->fresh(['familia', 'materialPl']);
        }

        return Proveedor::query()
            ->create(array_merge($payload, [
                'fecha' => now(),
                'estado' => 1,
            ]))
            ->load(['familia', 'materialPl']);
    }

    public function actualizarGrupo(int $nroFila, array $datos): void
    {
        $idFamilia = ! empty($datos['id_familia']) ? (int) $datos['id_familia'] : null;
        $idMaterialPL = Proveedor::query()
            ->where('estado', 1)
            ->where('nroFila', $nroFila)
            ->value('idMaterialPL');

        if (! $idMaterialPL) {
            return;
        }

        Proveedor::query()
            ->where('estado', 1)
            ->where('nroFila', $nroFila)
            ->update(['idFamilia' => $idFamilia]);

        foreach ($datos['proveedores'] ?? [] as $item) {
            $nomProveedor = trim((string) ($item['nombre'] ?? ''));
            if ($nomProveedor === '') {
                continue;
            }

            if (! empty($item['eliminar'])) {
                Proveedor::query()
                    ->where('nroFila', $nroFila)
                    ->whereRaw('UPPER(nomProveedor) = ?', [mb_strtoupper($nomProveedor)])
                    ->update(['estado' => 0]);

                continue;
            }

            $this->guardarProveedorEnGrupo(
                $nroFila,
                (int) $idMaterialPL,
                $nomProveedor,
                $idFamilia,
                $item['codigo'] ?? null,
                $item['nom_material'] ?? null
            );
        }

        $this->olvidarCachesCatalogo();
    }

    public function eliminarGrupo(int $nroFila): void
    {
        Proveedor::query()
            ->where('nroFila', $nroFila)
            ->update(['estado' => 0]);

        $this->olvidarCachesCatalogo();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<Proveedor>
     */
    private function queryBase(array $filtros)
    {
        $query = Proveedor::query()->where('estado', 1);

        if (! empty($filtros['familia'])) {
            $query->where('idFamilia', (int) $filtros['familia']);
        }

        if (! empty($filtros['buscar'])) {
            $termino = '%'.$filtros['buscar'].'%';
            $query->leftJoin('MaterialProgramaPL as mpl_buscar', 'Proveedor.idMaterialPL', '=', 'mpl_buscar.idMaterialPL')
                ->where(function ($consulta) use ($termino) {
                    $consulta->where('Proveedor.codigo', 'like', $termino)
                        ->orWhere('Proveedor.nomMaterial', 'like', $termino)
                        ->orWhere('Proveedor.nomProveedor', 'like', $termino)
                        ->orWhere('mpl_buscar.CodigoPL', 'like', $termino)
                        ->orWhere('mpl_buscar.nomMaterial', 'like', $termino);
                })
                ->select('Proveedor.*');
        }

        return $query;
    }

    /**
     * @param  Collection<int, Proveedor>  $registros
     */
    private function agruparFilas(Collection $registros): Collection
    {
        return $registros
            ->groupBy('nroFila')
            ->map(function (Collection $grupo) {
                $primero = $grupo->first();
                $proveedores = [];

                foreach ($grupo as $registro) {
                    $proveedores[$registro->nomProveedor] = [
                        'codigo' => $registro->codigo,
                        'nomMaterial' => $registro->nomMaterial,
                    ];
                }

                return (object) [
                    'nroFila' => (int) $primero->nroFila,
                    'idMaterialPL' => $primero->idMaterialPL,
                    'idFamilia' => $primero->idFamilia,
                    'familia' => $primero->familia,
                    'materialPl' => $primero->materialPl,
                    'proveedores' => $proveedores,
                ];
            })
            ->sortBy('nroFila')
            ->values();
    }

    private function olvidarCachesCatalogo(): void
    {
        Cache::forget('proveedor.catalogos.v2');
        Cache::forget('proveedor.metadatos_columnas.v2');
    }

    /**
     * @return list<array{nombre: string, key: string}>
     */
    private function consultarColumnasProveedor(): array
    {
        $nombres = Proveedor::query()
            ->where('estado', 1)
            ->whereNotNull('nomProveedor')
            ->where('nomProveedor', '!=', '')
            ->distinct()
            ->pluck('nomProveedor')
            ->unique()
            ->sortBy(fn (string $nombre) => $this->ordenNombreProveedor($nombre))
            ->values();

        return $nombres->map(fn (string $nombre) => [
            'nombre' => $nombre,
            'key' => self::claveColumnaProveedor($nombre),
        ])->all();
    }

    /**
     * @param  list<array{nombre: string, key: string}>  $columnasProveedor
     * @return list<array<string, mixed>>
     */
    private function armarColumnasConfig(array $columnasProveedor): array
    {
        $columnas = [
            ['key' => 'num', 'label' => '#'],
            ['key' => 'familia', 'label' => 'Material'],
            ['key' => 'codigo-pl', 'label' => 'Código planeamiento'],
            ['key' => 'material-pl', 'label' => 'Material planeamiento'],
        ];

        foreach ($columnasProveedor as $proveedor) {
            $columnas[] = [
                'key' => $proveedor['key'].'-codigo',
                'label' => 'Código '.$proveedor['nombre'],
            ];
            $columnas[] = [
                'key' => $proveedor['key'].'-material',
                'label' => 'Material '.$proveedor['nombre'],
            ];
        }

        $columnas[] = ['key' => 'acciones', 'label' => 'Acciones', 'fija' => true];

        return $columnas;
    }

    private function guardarProveedorEnGrupo(
        int $nroFila,
        int $idMaterialPL,
        string $nomProveedor,
        ?int $idFamilia,
        ?string $codigo,
        ?string $nomMaterial
    ): void {
        $codigo = $this->nullableString($codigo);
        $nomMaterial = $this->nullableString($nomMaterial);

        $existente = Proveedor::query()
            ->where('estado', 1)
            ->where('nroFila', $nroFila)
            ->whereRaw('UPPER(nomProveedor) = ?', [mb_strtoupper($nomProveedor)])
            ->first();

        if ($codigo === null && $nomMaterial === null) {
            if ($existente) {
                $existente->update(['estado' => 0]);
            }

            return;
        }

        $payload = [
            'nroFila' => $nroFila,
            'idFamilia' => $idFamilia,
            'idMaterialPL' => $idMaterialPL,
            'nomProveedor' => $nomProveedor,
            'codigo' => $codigo,
            'nomMaterial' => $nomMaterial,
        ];

        if ($existente) {
            $existente->update($payload);

            return;
        }

        Proveedor::query()->create(array_merge($payload, [
            'fecha' => now(),
            'estado' => 1,
        ]));
    }

    private function ordenNombreProveedor(string $nombre): string
    {
        $upper = mb_strtoupper($nombre);

        if ($upper === 'TGF') {
            return '0';
        }

        if ($upper === 'SAFCO') {
            return '1';
        }

        return '2'.$nombre;
    }

    private function siguienteNroFilaMaestra(): int
    {
        $maxFila = Proveedor::query()->where('estado', 1)->max('nroFila');

        return $maxFila ? ((int) $maxFila + 1) : 1;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function familiaDesdeMaterial(int $idMaterialPL): ?int
    {
        $idFamilia = ProgramaFila::query()
            ->where('idMaterialPL', $idMaterialPL)
            ->whereNotNull('idFamilia')
            ->value('idFamilia');

        return $idFamilia ? (int) $idFamilia : null;
    }
}
