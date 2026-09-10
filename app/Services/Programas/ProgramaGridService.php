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

    public function totalFilas(array $filtros = []): int
    {
        return $this->queryFilas($filtros)->count();
    }

    /** Programas distintos presentes en la grilla de receta (no el catálogo CRUD). */
    public function totalProgramasDistintos(array $filtros = []): int
    {
        $query = $this->queryFilas($filtros);

        $porGeneral = (clone $query)
            ->whereNotNull('idProgramaGeneral')
            ->distinct()
            ->count('idProgramaGeneral');

        $sinGeneral = (clone $query)
            ->whereNull('idProgramaGeneral')
            ->distinct()
            ->count('idPrograma');

        return $porGeneral + $sinGeneral;
    }

    public function catalogos(): array
    {
        return [
            'programasGenerales' => ProgramaGeneral::query()->activo()->orderBy('nombre')->get(),
            'mercados' => Mercado::query()->activo()->orderBy('nomMercado')->get(),
            'clientes' => Cliente::query()->activo()->orderBy('nomCliente')->get(),
            'variedades' => Variedad::query()->activo()->orderBy('nomVariedad')->get(),
            'programas' => ProgramaCatalogo::query()->activo()->orderBy('nomPrograma')->get(),
            'presentaciones' => Presentacion::query()->activo()->orderBy('nomPresentacion')->get(),
            'usos' => Uso::query()->activo()->orderBy('nomUso')->get(),
            'situados' => Situado::query()->activo()->orderBy('nomSituado')->get(),
            'familias' => Familia::query()->activo()->orderBy('nomFamilia')->get(),
        ];
    }

    /**
     * Opciones de filtros según selección previa (datos reales en receta).
     *
     * @param  array{mercado?: mixed, cliente?: mixed, programa_general?: mixed, programa?: mixed, presentacion?: mixed, familia?: mixed}  $filtros
     * @return array{mercados: Collection, clientes: Collection, programasGenerales: Collection, programas: Collection, presentaciones: Collection, familias: Collection}
     */
    public function catalogosFiltro(array $filtros = []): array
    {
        return [
            'mercados' => $this->opcionesMercadoFiltro(),
            'clientes' => $this->opcionesClienteFiltro($filtros),
            'programasGenerales' => $this->opcionesProgramaGeneralFiltro(),
            'programas' => $this->opcionesProgramaFiltro($filtros),
            'presentaciones' => $this->opcionesPresentacionFiltro($filtros),
            'familias' => $this->opcionesFamiliaFiltro($filtros),
        ];
    }

    /**
     * @param  array{mercado?: mixed, cliente?: mixed, programa_general?: mixed, programa?: mixed, presentacion?: mixed, familia?: mixed, q?: string}  $filtros
     * @return array{mercado: mixed, cliente: mixed, programa_general: mixed, familia: mixed, programa: mixed, presentacion: mixed, q: string}
     */
    public function normalizarFiltros(array $filtros): array
    {
        $mercado = filled($filtros['mercado'] ?? null) ? (int) $filtros['mercado'] : null;
        $cliente = filled($filtros['cliente'] ?? null) ? (int) $filtros['cliente'] : null;
        $programaGeneral = filled($filtros['programa_general'] ?? null) ? (int) $filtros['programa_general'] : null;
        $programa = filled($filtros['programa'] ?? null) ? (int) $filtros['programa'] : null;
        $presentacion = filled($filtros['presentacion'] ?? null) ? (int) $filtros['presentacion'] : null;
        $familia = filled($filtros['familia'] ?? null) ? (int) $filtros['familia'] : null;

        if ($mercado && ! $this->opcionesMercadoFiltro()->contains('idMercado', $mercado)) {
            $mercado = null;
        }

        if ($cliente && ! $this->opcionesClienteFiltro(['mercado' => $mercado])->contains('idCliente', $cliente)) {
            $cliente = null;
        }

        // ProgramaGeneral es independiente de mercado/cliente
        if ($programaGeneral && ! $this->opcionesProgramaGeneralFiltro()->contains('idProgramaGeneral', $programaGeneral)) {
            $programaGeneral = null;
        }

        if ($programa && ! $this->opcionesProgramaFiltro([
            'mercado' => $mercado,
            'cliente' => $cliente,
            'programa_general' => $programaGeneral,
        ])->contains('idPrograma', $programa)) {
            $programa = null;
        }

        if (! $programa) {
            $presentacion = null;
        } elseif ($presentacion && ! $this->opcionesPresentacionFiltro([
            'mercado' => $mercado,
            'cliente' => $cliente,
            'programa_general' => $programaGeneral,
            'programa' => $programa,
        ])->contains('idPresentacion', $presentacion)) {
            $presentacion = null;
        }

        if ($familia && ! $this->opcionesFamiliaFiltro([
            'mercado' => $mercado,
            'cliente' => $cliente,
            'programa_general' => $programaGeneral,
            'programa' => $programa,
            'presentacion' => $presentacion,
        ])->contains('idFamilia', $familia)) {
            $familia = null;
        }

        return [
            'mercado' => $mercado,
            'cliente' => $cliente,
            'programa_general' => $programaGeneral,
            'familia' => $familia,
            'programa' => $programa,
            'presentacion' => $presentacion,
            'q' => trim((string) ($filtros['q'] ?? '')),
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
            $cantidadValor = CantidadCajaSupport::normalizarValor($datos['cantidad'], $familia->nomFamilia);
            $claveCantidad = CantidadCajaSupport::clave($cantidadValor, $familia->nomFamilia);

            $cantidad = CantidadCaja::query()->firstOrCreate(
                ['cantidad' => $claveCantidad],
                ['Estado' => 1]
            );

            $nroFila = (int) ProgramaFila::query()->max('nroFila') + 1;

            $fila = ProgramaFila::query()->create([
                'nroFila' => $nroFila,
                'idMercado' => $datos['id_mercado'],
                'idCliente' => $datos['id_cliente'],
                'idVariedad' => $datos['id_variedad'],
                'idPrograma' => $datos['id_programa'],
                'idPresentacion' => $datos['id_presentacion'],
                'idMaterialPL' => $material->idMaterialPL,
                'idCantidadCaja' => $cantidad->idCantidadCaja,
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

    private function queryFilas(array $filtros): Builder
    {
        $query = ProgramaFila::query()->with([
            'mercado',
            'cliente',
            'variedad',
            'programa.programaGeneral',
            'programaGeneral',
            'presentacion',
            'material',
            'cantidadCaja',
            'uso',
            'situado',
            'familia',
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
            $termino = trim($filtros['q']);
            $query->whereHas('material', function (Builder $material) use ($termino) {
                $material->where('CodigoPL', 'like', '%'.$termino.'%')
                    ->orWhere('nomMaterial', 'like', '%'.$termino.'%');
            });
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

        return $query;
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

    private function opcionesMercadoFiltro(): Collection
    {
        $ids = ProgramaFila::query()->distinct()->pluck('idMercado');

        return Mercado::query()
            ->activo()
            ->whereIn('idMercado', $ids)
            ->orderBy('nomMercado')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesClienteFiltro(array $filtros): Collection
    {
        $ids = $this->queryIdsReceta($this->soloFiltros($filtros, ['mercado']))
            ->distinct()
            ->pluck('idCliente');

        return Cliente::query()
            ->activo()
            ->whereIn('idCliente', $ids)
            ->orderBy('nomCliente')
            ->get();
    }

    private function opcionesProgramaGeneralFiltro(): Collection
    {
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
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesProgramaFiltro(array $filtros): Collection
    {
        $ids = $this->queryIdsReceta($this->soloFiltros($filtros, ['mercado', 'cliente', 'programa_general']))
            ->distinct()
            ->pluck('idPrograma');

        return ProgramaCatalogo::query()
            ->activo()
            ->whereIn('idPrograma', $ids)
            ->orderBy('nomPrograma')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesPresentacionFiltro(array $filtros): Collection
    {
        if (empty($filtros['programa'])) {
            return collect();
        }

        $ids = $this->queryIdsReceta($this->soloFiltros($filtros, ['mercado', 'cliente', 'programa']))
            ->distinct()
            ->pluck('idPresentacion');

        return Presentacion::query()
            ->activo()
            ->whereIn('idPresentacion', $ids)
            ->orderBy('nomPresentacion')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function opcionesFamiliaFiltro(array $filtros): Collection
    {
        $idsMaterial = $this->queryIdsReceta($this->soloFiltros($filtros, [
            'mercado', 'cliente', 'programa', 'presentacion',
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
     * Vacía la grilla Receta (filas + pallets/conformación). No toca catálogos ni proveedores.
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

        Cache::forget('catalogo.programas_consumo');

        return [
            'filas' => $filas,
            'params' => $params,
        ];
    }
}
