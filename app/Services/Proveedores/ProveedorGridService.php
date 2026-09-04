<?php

namespace App\Services\Proveedores;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
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

        $base = $this->queryBase($filtros);

        $total = (int) DB::query()
            ->fromSub(
                (clone $base)->select('nroFila')->groupBy('nroFila'),
                'filas_proveedor'
            )
            ->count();

        $nroFilasPage = (clone $base)
            ->select('nroFila')
            ->groupBy('nroFila')
            ->orderBy('nroFila')
            ->forPage($page, $perPage)
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

    public function columnasProveedor(): array
    {
        return Cache::remember('proveedor.columnas_proveedor.v1', self::CACHE_TTL, function () {
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
        });
    }

    public function columnasConfig(): array
    {
        return Cache::remember('proveedor.columnas_config.v1', self::CACHE_TTL, function () {
            $columnas = [
                ['key' => 'num', 'label' => '#'],
                ['key' => 'familia', 'label' => 'Material'],
                ['key' => 'codigo-pl', 'label' => 'Código planeamiento'],
                ['key' => 'material-pl', 'label' => 'Material planeamiento'],
            ];

            foreach ($this->columnasProveedor() as $proveedor) {
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
        });
    }

    public static function claveColumnaProveedor(string $nombre): string
    {
        return 'prov-'.substr(md5(mb_strtoupper(trim($nombre))), 0, 10);
    }

    public function catalogos(): array
    {
        return Cache::remember('proveedor.catalogos.v2', self::CACHE_TTL, function () {
            $desdeDb = Proveedor::query()
                ->where('estado', 1)
                ->whereNotNull('nomProveedor')
                ->distinct()
                ->orderBy('nomProveedor')
                ->pluck('nomProveedor')
                ->all();

            $nombresProveedor = collect(['TGF', 'SAFCO'])
                ->merge($desdeDb)
                ->unique()
                ->sort()
                ->values()
                ->all();

            return [
                'familias' => Familia::query()->activo()->orderBy('nomFamilia')->get(),
                // Lista completa se resuelve vía AJAX (material-por-codigo / material-por-nombre).
                'materiales' => collect(),
                'nombresProveedor' => $nombresProveedor,
            ];
        });
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        return MaterialProgramaLookup::porCodigo($codigo);
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
        $idMaterialPL = (int) $datos['id_material_pl'];
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
            $query->where(function ($consulta) use ($termino) {
                $consulta->where('codigo', 'like', $termino)
                    ->orWhere('nomMaterial', 'like', $termino)
                    ->orWhere('nomProveedor', 'like', $termino)
                    ->orWhereHas('materialPl', function ($material) use ($termino) {
                        $material->where('CodigoPL', 'like', $termino)
                            ->orWhere('nomMaterial', 'like', $termino);
                    });
            });
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
        Cache::forget('proveedor.columnas_proveedor.v1');
        Cache::forget('proveedor.columnas_config.v1');
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
