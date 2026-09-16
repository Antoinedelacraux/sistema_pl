<?php

namespace App\Services\Programas;

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
use App\Support\MaterialFamiliaSupport;
use App\Support\GridPagination;
use App\Support\MaterialProgramaLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProgramaGridService
{
    private const CACHE_TTL = 600;

    /** @var array<string, Collection> */
    private array $opcionesFiltroMemo = [];

    public function __construct(
        private readonly ProgramaImagenService $imagenService
    ) {}

    public function filas(array $filtros = []): Collection
    {
        return $this->queryFilas($filtros)
            ->orderBy('nroFila')
            ->get();
    }

    public function filasPaginadas(array $filtros, Request $request): LengthAwarePaginator
    {
        return $this->queryFilas($filtros)
            ->orderBy('nroFila')
            ->paginate(GridPagination::PER_PAGE)
            ->withQueryString();
    }

    /** Programas distintos presentes en la grilla de receta (no el catÃ¡logo CRUD). */
    public function totalProgramasDistintos(array $filtros = []): int
    {
        $version = (int) Cache::get('programa.cache_version', 0);
        $cacheKey = 'programa.total_distintos.v'.$version.'.'.md5(json_encode($filtros));

        return (int) Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filtros) {
            // Evita el with() pesado de queryFilas: solo IDs de receta.
            $base = $this->queryIdsReceta($filtros);

            $porGeneral = (clone $base)
                ->whereNotNull('idProgramaGeneral')
                ->distinct()
                ->count('idProgramaGeneral');

            $sinGeneral = (clone $base)
                ->whereNull('idProgramaGeneral')
                ->distinct()
                ->count('idPrograma');

            return $porGeneral + $sinGeneral;
        });
    }

    public function catalogos(): array
    {
        return Cache::remember('programa.catalogos.v2', self::CACHE_TTL, fn () => [
            'programasGenerales' => ProgramaGeneral::query()->activo()->orderBy('nombre')->get(['idProgramaGeneral', 'nombre', 'Estado']),
            'mercados' => Mercado::query()->activo()->orderBy('nomMercado')->get(['idMercado', 'nomMercado', 'Estado']),
            'clientes' => Cliente::query()->activo()->orderBy('nomCliente')->get(['idCliente', 'nomCliente', 'Estado']),
            'variedades' => Variedad::query()->activo()->orderBy('nomVariedad')->get(['idVariedad', 'nomVariedad', 'Estado']),
            'programas' => ProgramaCatalogo::query()->activo()->orderBy('nomPrograma')->get(['idPrograma', 'nomPrograma', 'idProgramaGeneral', 'Estado']),
            'presentaciones' => Presentacion::query()->activo()->orderBy('nomPresentacion')->get(['idPresentacion', 'nomPresentacion', 'Estado']),
            'usos' => Uso::query()->activo()->orderBy('nomUso')->get(['idUso', 'nomUso', 'Estado']),
            'situados' => Situado::query()->activo()->orderBy('nomSituado')->get(['idSituado', 'nomSituado', 'Estado']),
            'familias' => Familia::query()->activo()->orderBy('nomFamilia')->get(['idFamilia', 'nomFamilia', 'Estado']),
        ]);
    }

    /**
     * Opciones de filtros según selección previa (datos reales en receta).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{programasGenerales: Collection, programas: Collection, presentaciones: Collection, familias: Collection}
     */
    public function catalogosFiltroReceta(array $filtros = []): array
    {
        $version = (int) Cache::get('programa.cache_version', 0);
        $cacheKey = 'programa.catalogos_filtro_receta.v'.$version.'.'.md5(json_encode([
            'programa_general' => $filtros['programa_general'] ?? null,
            'programa' => $filtros['programa'] ?? null,
            'presentacion' => $filtros['presentacion'] ?? null,
            'familia' => $filtros['familia'] ?? null,
        ]));

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => [
            'programasGenerales' => $this->opcionesProgramaGeneralFiltro(),
            'programas' => $this->opcionesProgramaFiltro($filtros),
            'presentaciones' => $this->opcionesPresentacionFiltro($filtros),
            'familias' => $this->opcionesFamiliaFiltro($filtros),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filtros: array<string, mixed>, catalogos_filtro: array<string, Collection>}
     */
    public function resolverFiltrosReceta(array $filtros): array
    {
        $parsed = $this->parseFiltrosEntrada($filtros);

        for ($i = 0; $i < 5; $i++) {
            $catalogosFiltro = $this->catalogosFiltroReceta($parsed);
            $ajustado = $this->ajustarFiltrosRecetaConOpciones($parsed, $catalogosFiltro);
            if ($ajustado === $parsed) {
                return [
                    'filtros' => $parsed,
                    'catalogos_filtro' => $catalogosFiltro,
                ];
            }
            $parsed = $ajustado;
        }

        return [
            'filtros' => $parsed,
            'catalogos_filtro' => $this->catalogosFiltroReceta($parsed),
        ];
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        return MaterialProgramaLookup::porCodigo($codigo, soloActivos: false);
    }

    public function crearFila(array $datos, ?UploadedFile $imagen = null): ProgramaFila
    {
        return DB::transaction(function () use ($datos, $imagen) {
            $material = MaterialProgramaLookup::resolverPorCodigo(
                $datos['codigo_pl'],
                $datos['nom_material']
            );

            $familia = Familia::query()->findOrFail($datos['id_familia']);
            $claveCantidad = CantidadCajaSupport::claveCantidad($datos['cantidad'], $familia->nomFamilia);

            $cantidad = CantidadCaja::query()->firstOrCreate(
                ['cantidad' => $claveCantidad],
                ['Estado' => 1]
            );

            $nroFila = (int) ProgramaFila::query()->max('nroFila') + 1;

            $idProgramaGeneral = ProgramaCatalogo::query()
                ->whereKey($datos['id_programa'])
                ->value('idProgramaGeneral');

            $fila = ProgramaFila::query()->create([
                'nroFila' => $nroFila,
                'idMercado' => $datos['id_mercado'],
                'idCliente' => $datos['id_cliente'],
                'idVariedad' => $datos['id_variedad'],
                'idPrograma' => $datos['id_programa'],
                'idProgramaGeneral' => $idProgramaGeneral,
                'idPresentacion' => $datos['id_presentacion'],
                'idMaterialPL' => $material->idMaterialPL,
                'idCantidadCaja' => $cantidad->idCantidadCaja,
                'idCantidadCajaRefExcel' => $cantidad->idCantidadCaja,
                'idUso' => $datos['id_uso'],
                'idSituado' => $datos['id_situado'],
                'idFamilia' => $datos['id_familia'],
                'Estado' => 1,
            ]);

            if ($imagen) {
                $ruta = $this->imagenService->guardar($imagen, $fila->idProgramaFila);

                if ($ruta) {
                    $fila->update(['imagen' => $ruta]);
                }
            }

            $this->olvidarCaches();

            return $fila->fresh()->load([
                'mercado', 'cliente', 'variedad', 'programa', 'presentacion',
                'material', 'cantidadCaja', 'uso', 'situado', 'familia',
            ]);
        });
    }

    public function actualizarImagen(int $idProgramaFila, ?UploadedFile $imagen, bool $eliminarImagen = false): ProgramaFila
    {
        return DB::transaction(function () use ($idProgramaFila, $imagen, $eliminarImagen) {
            $fila = ProgramaFila::query()->findOrFail($idProgramaFila);

            if ($eliminarImagen) {
                $this->imagenService->eliminarVariantes($idProgramaFila);
                $fila->update(['imagen' => null]);

                return $fila->fresh();
            }

            if ($imagen) {
                $ruta = $this->imagenService->reemplazar($imagen, $idProgramaFila, $fila->imagen);

                if ($ruta) {
                    $fila->update(['imagen' => $ruta]);
                }
            }

            return $fila->fresh();
        });
    }

    public function actualizarFila(int $idProgramaFila, array $datos, ?UploadedFile $imagen = null): ProgramaFila
    {
        return DB::transaction(function () use ($idProgramaFila, $datos, $imagen) {
            $fila = ProgramaFila::query()->findOrFail($idProgramaFila);

            $material = MaterialProgramaLookup::resolverPorCodigo(
                $datos['codigo_pl'],
                $datos['nom_material']
            );

            $familia = Familia::query()->findOrFail($datos['id_familia']);
            $claveCantidad = CantidadCajaSupport::claveCantidad($datos['cantidad'], $familia->nomFamilia);

            $cantidad = CantidadCaja::query()->firstOrCreate(
                ['cantidad' => $claveCantidad],
                ['Estado' => 1]
            );

            $idProgramaGeneral = ProgramaCatalogo::query()
                ->whereKey($datos['id_programa'])
                ->value('idProgramaGeneral');

            $fila->update([
                'idMercado' => $datos['id_mercado'],
                'idCliente' => $datos['id_cliente'],
                'idVariedad' => $datos['id_variedad'],
                'idPrograma' => $datos['id_programa'],
                'idProgramaGeneral' => $idProgramaGeneral,
                'idPresentacion' => $datos['id_presentacion'],
                'idMaterialPL' => $material->idMaterialPL,
                'idCantidadCaja' => $cantidad->idCantidadCaja,
                'idUso' => $datos['id_uso'],
                'idSituado' => $datos['id_situado'],
                'idFamilia' => $datos['id_familia'],
            ]);

            if ($imagen) {
                $ruta = $this->imagenService->reemplazar($imagen, $idProgramaFila, $fila->imagen);

                if ($ruta) {
                    $fila->update(['imagen' => $ruta]);
                }
            }

            $this->olvidarCaches();

            return $fila->fresh()->load([
                'mercado', 'cliente', 'variedad', 'programa', 'presentacion',
                'material', 'cantidadCaja', 'uso', 'situado', 'familia',
            ]);
        });
    }

    private function queryFilas(array $filtros): Builder
    {
        $query = ProgramaFila::query()->with([
            'mercado:idMercado,nomMercado',
            'cliente:idCliente,nomCliente',
            'variedad:idVariedad,nomVariedad',
            'programa:idPrograma,nomPrograma,idProgramaGeneral',
            'programa.programaGeneral:idProgramaGeneral,nombre,CodigoProGeneral',
            'programaGeneral:idProgramaGeneral,nombre,CodigoProGeneral',
            'presentacion:idPresentacion,nomPresentacion',
            'material:idMaterialPL,CodigoPL,nomMaterial',
            'cantidadCaja:idCantidadCaja,cantidad',
            'uso:idUso,nomUso',
            'familia:idFamilia,nomFamilia',
        ]);

        if (! empty($filtros['mercado'])) {
            $query->where('idMercado', (int) $filtros['mercado']);
        }

        if (! empty($filtros['cliente'])) {
            $query->where('idCliente', (int) $filtros['cliente']);
        }

        if (! empty($filtros['familia'])) {
            $this->aplicarFiltroFamilia($query, (int) $filtros['familia']);
        }

        $this->aplicarFiltroProgramaGeneral($query, $filtros);

        if (! empty($filtros['programa'])) {
            $query->where('idPrograma', (int) $filtros['programa']);
        }

        if (! empty($filtros['presentacion'])) {
            $query->where('idPresentacion', (int) $filtros['presentacion']);
        }

        if (! empty($filtros['q'])) {
            $this->aplicarFiltroBuscarMaterial($query, trim((string) $filtros['q']));
        }

        return $query;
    }

    private function queryIdsReceta(array $filtros): Builder
    {
        $query = ProgramaFila::query();

        if (! empty($filtros['mercado'])) {
            $query->where('idMercado', (int) $filtros['mercado']);
        }

        if (! empty($filtros['cliente'])) {
            $query->where('idCliente', (int) $filtros['cliente']);
        }

        $this->aplicarFiltroProgramaGeneral($query, $filtros);

        if (! empty($filtros['programa'])) {
            $query->where('idPrograma', (int) $filtros['programa']);
        }

        if (! empty($filtros['presentacion'])) {
            $query->where('idPresentacion', (int) $filtros['presentacion']);
        }

        if (! empty($filtros['familia'])) {
            $this->aplicarFiltroFamilia($query, (int) $filtros['familia']);
        }

        if (! empty($filtros['q'])) {
            $this->aplicarFiltroBuscarMaterial($query, trim((string) $filtros['q']));
        }

        return $query;
    }

    /**
     * @param  Builder<ProgramaFila>  $query
     */
    private function aplicarFiltroBuscarMaterial(Builder $query, string $termino): void
    {
        if ($termino === '') {
            return;
        }

        $like = '%'.$termino.'%';
        $query->leftJoin('MaterialProgramaPL as mpl_buscar', 'ProgramaFila.idMaterialPL', '=', 'mpl_buscar.idMaterialPL')
            ->where(function (Builder $consulta) use ($like) {
                $consulta->where('mpl_buscar.CodigoPL', 'like', $like)
                    ->orWhere('mpl_buscar.nomMaterial', 'like', $like);
            })
            ->select('ProgramaFila.*');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{mercado: ?int, cliente: ?int, programa_general: ?int, familia: ?int, programa: ?int, presentacion: ?int, q: string}
     */
    private function parseFiltrosEntrada(array $filtros): array
    {
        return [
            'mercado' => filled($filtros['mercado'] ?? null) ? (int) $filtros['mercado'] : null,
            'cliente' => filled($filtros['cliente'] ?? null) ? (int) $filtros['cliente'] : null,
            'programa_general' => filled($filtros['programa_general'] ?? null) ? (int) $filtros['programa_general'] : null,
            'programa' => filled($filtros['programa'] ?? null) ? (int) $filtros['programa'] : null,
            'presentacion' => filled($filtros['presentacion'] ?? null) ? (int) $filtros['presentacion'] : null,
            'familia' => filled($filtros['familia'] ?? null) ? (int) $filtros['familia'] : null,
            'q' => trim((string) ($filtros['q'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array{programasGenerales: Collection, programas: Collection, presentaciones: Collection, familias: Collection}  $opciones
     * @return array<string, mixed>
     */
    private function ajustarFiltrosRecetaConOpciones(array $parsed, array $opciones): array
    {
        $out = $parsed;

        if ($out['programa_general'] && ! $opciones['programasGenerales']->contains('idProgramaGeneral', $out['programa_general'])) {
            $out['programa_general'] = null;
        }

        if ($out['programa'] && ! $opciones['programas']->contains('idPrograma', $out['programa'])) {
            $out['programa'] = null;
        }

        if (! $out['programa']) {
            $out['presentacion'] = null;
        } elseif ($out['presentacion'] && ! $opciones['presentaciones']->contains('idPresentacion', $out['presentacion'])) {
            $out['presentacion'] = null;
        }

        if ($out['familia'] && ! $opciones['familias']->contains('idFamilia', $out['familia'])) {
            $out['familia'] = null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $claves
     * @return array<string, mixed>
     */
    private function soloFiltros(array $filtros, array $claves): array
    {
        $resultado = [];

        foreach ($claves as $clave) {
            if (! empty($filtros[$clave])) {
                $resultado[$clave] = $filtros[$clave];
            }
        }

        return $resultado;
    }

    private function opcionesProgramaGeneralFiltro(): Collection
    {
        return $this->memoOpcionesFiltro('programa_general', function (): Collection {
            $idsGeneral = ProgramaFila::query()
                ->whereNotNull('idProgramaGeneral')
                ->distinct()
                ->pluck('idProgramaGeneral');

            if ($idsGeneral->isEmpty()) {
                $idsProgramas = ProgramaFila::query()->distinct()->pluck('idPrograma');
                $idsGeneral = ProgramaCatalogo::query()
                    ->whereIn('idPrograma', $idsProgramas)
                    ->whereNotNull('idProgramaGeneral')
                    ->distinct()
                    ->pluck('idProgramaGeneral');
            }

            return ProgramaGeneral::query()
                ->activo()
                ->whereIn('idProgramaGeneral', $idsGeneral)
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesProgramaFiltro(array $filtros): Collection
    {
        $cacheKey = 'programa:'.md5(json_encode($this->soloFiltros($filtros, ['programa_general'])));

        return $this->memoOpcionesFiltro($cacheKey, function () use ($filtros): Collection {
            $ids = $this->queryIdsReceta($this->soloFiltros($filtros, ['programa_general']))
                ->distinct()
                ->pluck('idPrograma');

            return ProgramaCatalogo::query()
                ->activo()
                ->whereIn('idPrograma', $ids)
                ->orderBy('nomPrograma')
                ->get();
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesPresentacionFiltro(array $filtros): Collection
    {
        if (empty($filtros['programa'])) {
            return collect();
        }

        $cacheKey = 'presentacion:'.md5(json_encode($this->soloFiltros($filtros, ['programa_general', 'programa'])));

        return $this->memoOpcionesFiltro($cacheKey, function () use ($filtros): Collection {
            $ids = $this->queryIdsReceta($this->soloFiltros($filtros, ['programa_general', 'programa']))
                ->distinct()
                ->pluck('idPresentacion');

            return Presentacion::query()
                ->activo()
                ->whereIn('idPresentacion', $ids)
                ->orderBy('nomPresentacion')
                ->get();
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesFamiliaFiltro(array $filtros): Collection
    {
        $cacheKey = 'familia:'.md5(json_encode($this->soloFiltros($filtros, [
            'programa_general', 'programa', 'presentacion',
        ])));

        return $this->memoOpcionesFiltro($cacheKey, function () use ($filtros): Collection {
            $idsMaterial = $this->queryIdsReceta($this->soloFiltros($filtros, [
                'programa_general', 'programa', 'presentacion',
            ]))
                ->distinct()
                ->pluck('idMaterialPL');

            $idsFamilia = MaterialFamiliaSupport::idsFamiliaDeMateriales($idsMaterial);

            if ($idsFamilia === []) {
                return collect();
            }

            return Familia::query()
                ->activo()
                ->whereIn('idFamilia', $idsFamilia)
                ->orderBy('nomFamilia')
                ->get();
        });
    }

    private function memoOpcionesFiltro(string $key, callable $resolver): Collection
    {
        if (! isset($this->opcionesFiltroMemo[$key])) {
            $this->opcionesFiltroMemo[$key] = $resolver();
        }

        return $this->opcionesFiltroMemo[$key];
    }

    public function olvidarCaches(): void
    {
        Cache::forget('programa.catalogos.v2');
        Cache::forget('catalogo.programas_consumo');
        Cache::forget('catalogo.programas_por_general_consumo');
        $version = (int) Cache::get('programa.cache_version', 0);
        Cache::forever('programa.cache_version', $version + 1);
        $paramsVersion = (int) Cache::get('programa.presentacion_params.cache_version', 0);
        Cache::forever('programa.presentacion_params.cache_version', $paramsVersion + 1);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltroProgramaGeneral(Builder $query, array $filtros): void
    {
        if (empty($filtros['programa_general'])) {
            return;
        }

        $idProgramaGeneral = (int) $filtros['programa_general'];
        $query->where(function (Builder $q) use ($idProgramaGeneral) {
            $q->where('idProgramaGeneral', $idProgramaGeneral)
                ->orWhere(function (Builder $legacy) use ($idProgramaGeneral) {
                    $legacy->whereNull('idProgramaGeneral')
                        ->whereHas('programa', function (Builder $programa) use ($idProgramaGeneral) {
                            $programa->where('idProgramaGeneral', $idProgramaGeneral);
                        });
                });
        });
    }

    private function aplicarFiltroFamilia(Builder $query, int $idFamilia): void
    {
        $idsMaterial = MaterialFamiliaSupport::idsMaterialPorFamilia($idFamilia);

        $query->where(function (Builder $q) use ($idFamilia, $idsMaterial) {
            $q->where('idFamilia', $idFamilia);

            if ($idsMaterial !== []) {
                $q->orWhereIn('idMaterialPL', $idsMaterial);
            }
        });
    }

    /**
     * VacÃ­a la grilla Receta (filas + pallets/conformaciÃ³n). No toca catÃ¡logos ni proveedores.
     *
     * @return array{filas: int, params: int}
     */
    public function vaciarReceta(): array
    {
        $filas = ProgramaFila::query()->count();
        $params = ProgramaPresentacionParam::query()->count();

        DB::transaction(function () {
            ProgramaFila::query()->pluck('idProgramaFila')->each(
                fn (int $id) => $this->imagenService->eliminarVariantes($id)
            );

            ProgramaFila::query()->delete();
            ProgramaPresentacionParam::query()->delete();
        });

        $this->olvidarCaches();

        return [
            'filas' => $filas,
            'params' => $params,
        ];
    }
}


