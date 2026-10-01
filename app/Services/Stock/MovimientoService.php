<?php

namespace App\Services\Stock;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\ProgramaFila;
use App\Models\Proveedores\Proveedor;
use App\Support\CantidadCajaSupport;
use App\Models\Programas\TipoConsumo;
use App\Models\Stock\Almacen;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Traslado;
use App\Services\Integrations\PowerAutomateService;
use App\Support\DocumentoGuiaSupport;
use App\Support\GridPagination;
use App\Support\MaterialProgramaLookup;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MovimientoService
{
    /** @var list<string> */
    private const COLUMNAS_LOTE_INGRESO = [
        'idAlmacen',
        'fecha',
        'idUsuario',
        'fechaRegistro',
        'factura',
        'guia',
        'observacion',
        'archivoOneDriveId',
    ];

    /** @var list<string> */
    private const COLUMNAS_LOTE_EGRESO = [
        'idAlmacen',
        'fecha',
        'idUsuario',
        'fechaRegistro',
        'idTipoConsumo',
        'idPrograma',
        'idPresentacion',
        'factura',
        'guia',
        'observacion',
        'archivoOneDriveId',
    ];

    /** @var list<string> */
    private const COLUMNAS_GRID_PRINCIPAL = [
        'idMovimiento',
        'idAlmacen',
        'idMaterialPL',
        'idUsuario',
        'fecha',
        'fechaRegistro',
        'factura',
        'guia',
        'facturaGuia',
        'archivoOneDriveUrl',
        'observacion',
        'idTipoConsumo',
        'idPrograma',
        'idPresentacion',
    ];

    /** @var list<string> */
    private const WITH_GRID_INGRESO = [
        'materialPl:idMaterialPL,CodigoPL,nomMaterial',
        'almacen:idAlmacen,codigo',
    ];

    /** @var list<string> */
    private const WITH_GRID_EGRESO = [
        'materialPl:idMaterialPL,CodigoPL,nomMaterial',
        'almacen:idAlmacen,codigo',
        'tipoConsumo:idTipoConsumo,nomTipoConsumo',
        'programa:idPrograma,nomPrograma',
        'presentacion:idPresentacion,nomPresentacion',
    ];

    private const CACHE_TOTAL_LOTES_TTL = 45;

    private const CACHE_GRID_REGISTRO_TTL = 45;

    public function __construct(
        private readonly PowerAutomateService $powerAutomateService,
        private readonly StockAlmacenService $stockAlmacenService,
    ) {}

    /**
     * Catálogos para Registro. En ingresos/traslados evita programas/receta pesados (AJAX en consumo).
     *
     * @return array<string, mixed>
     */
    public function catalogosRegistro(string $tab, bool $conModalConsumo = false): array
    {
        $data = [
            'almacenes' => Cache::remember('catalogo.almacenes.v3', 600, function () {
                return Almacen::query()
                    ->activo()
                    ->orderBy('codigo')
                    ->get(['idAlmacen', 'codigo', 'nomAlmacen', 'Estado']);
            }),
            'materiales' => collect(),
        ];

        $cargarConsumoCompleto = $tab === 'consumos';
        $cargarConsumoModal = $cargarConsumoCompleto || $conModalConsumo;

        if (! $cargarConsumoModal) {
            $data['tiposConsumo'] = collect();
            $data['programasGenerales'] = collect();
            $data['programas'] = collect();
            $data['programasPorGeneral'] = [];

            return $data;
        }

        $data['tiposConsumo'] = Cache::remember('catalogo.tipos_consumo', 600, function () {
            return TipoConsumo::query()
                ->activo()
                ->orderBy('nomTipoConsumo')
                ->get(['idTipoConsumo', 'nomTipoConsumo']);
        });
        $data['programasGenerales'] = Cache::remember('catalogo.programas_generales_consumo', 600, function () {
            return app(ConsumoRecetaService::class)->programasGeneralesDisponibles();
        });

        if (! $cargarConsumoCompleto) {
            $data['programas'] = collect();
            $data['programasPorGeneral'] = [];

            return $data;
        }

        $data['programas'] = Cache::remember('catalogo.programas_consumo', 600, function () {
            return app(ConsumoRecetaService::class)->programasDisponibles();
        });
        $data['programasPorGeneral'] = Cache::remember('catalogo.programas_por_general_consumo', 600, function () {
            return app(ConsumoRecetaService::class)->programasAgrupadosPorGeneral();
        });

        return $data;
    }

    /**
     * Carga mínima de materiales (p.ej. old() / edición) sin el catálogo completo.
     *
     * @param  list<int>  $ids
     * @return Collection<int, MaterialProgramaPL>
     */
    public function materialesPorIds(array $ids): Collection
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return MaterialProgramaPL::query()
            ->whereIn('idMaterialPL', $ids)
            ->get(['idMaterialPL', 'CodigoPL', 'nomMaterial']);
    }

    public function listarIngresos(array $filtros = []): Collection
    {
        $movimientos = $this->listarPorTipo(MovimientoStock::TIPO_INGRESO, $filtros);

        return $movimientos
            ->groupBy(fn (MovimientoStock $movimiento) => $this->claveLoteIngreso($movimiento))
            ->map(function (Collection $lineas) {
                $principal = $lineas->first();
                $totalLineas = $lineas->count();
                $nombreMaterial = $principal->materialPl?->nomMaterial ?: '—';

                return (object) [
                    'idMovimiento' => $principal->idMovimiento,
                    'almacen' => $principal->almacen,
                    'fecha' => $principal->fecha,
                    'factura' => $principal->factura,
                    'guia' => $principal->guia ?: $principal->facturaGuia,
                    'archivoOneDriveUrl' => $principal->archivoOneDriveUrl,
                    'observacion' => $principal->observacion,
                    'material' => $totalLineas > 1 ? $nombreMaterial.' y varios' : $nombreMaterial,
                    'codigo_pl' => $totalLineas > 1 ? 'Varios' : ($principal->materialPl?->CodigoPL ?: '—'),
                    'cantidad' => $lineas->sum(fn (MovimientoStock $linea) => (float) $linea->cantidad),
                    'es_lote' => $totalLineas > 1,
                    'total_lineas' => $totalLineas,
                ];
            })
            ->sortByDesc(fn ($grupo) => ($grupo->fecha?->format('Y-m-d') ?? '').sprintf('%010d', $grupo->idMovimiento))
            ->values();
    }

    public function listarIngresosPaginados(array $filtros, Request $request): LengthAwarePaginator
    {
        return $this->listarGrillaRegistroCacheada('ingreso', $filtros, $request, fn () => $this->listarMovimientosPaginadosPorLote(
            MovimientoStock::TIPO_INGRESO,
            self::COLUMNAS_LOTE_INGRESO,
            self::WITH_GRID_INGRESO,
            $filtros,
            $request
        ));
    }

    public function listarEgresosPaginados(array $filtros, Request $request): LengthAwarePaginator
    {
        return $this->listarGrillaRegistroCacheada('egreso', $filtros, $request, fn () => $this->listarMovimientosPaginadosPorLote(
            MovimientoStock::TIPO_EGRESO,
            self::COLUMNAS_LOTE_EGRESO,
            self::WITH_GRID_EGRESO,
            $filtros,
            $request
        ));
    }

    public function listarTrasladosPaginados(array $filtros, Request $request): LengthAwarePaginator
    {
        return $this->listarGrillaRegistroCacheada('traslado', $filtros, $request, fn () => $this->listarTrasladosPaginadosSinCache($filtros, $request));
    }

    private function listarTrasladosPaginadosSinCache(array $filtros, Request $request): LengthAwarePaginator
    {
        $query = Traslado::query()
            ->select([
                'idTraslado',
                'idMaterialPL',
                'idAlmacenOrigen',
                'idAlmacenDestino',
                'cantidad',
                'fecha',
                'observacion',
                'factura',
                'guia',
                'facturaGuia',
                'archivoOneDriveUrl',
                'estado',
            ])
            ->with([
                'materialPl:idMaterialPL,CodigoPL,nomMaterial',
                'almacenOrigen:idAlmacen,codigo',
                'almacenDestino:idAlmacen,codigo',
            ])
            ->where('estado', 1);

        $this->aplicarFiltrosTraslado($query, $filtros);

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('idTraslado')
            ->paginate(GridPagination::PER_PAGE)
            ->withQueryString();
    }

    public function listarEgresos(array $filtros = []): Collection
    {
        $movimientos = $this->listarPorTipo(MovimientoStock::TIPO_EGRESO, $filtros);

        return $movimientos
            ->groupBy(fn (MovimientoStock $movimiento) => $this->claveLoteEgreso($movimiento))
            ->map(function (Collection $lineas) {
                $principal = $lineas->first();
                $totalLineas = $lineas->count();
                $nombreMaterial = $principal->materialPl?->nomMaterial ?: '—';

                return (object) [
                    'idMovimiento' => $principal->idMovimiento,
                    'almacen' => $principal->almacen,
                    'fecha' => $principal->fecha,
                    'factura' => $principal->factura,
                    'guia' => $principal->guia ?: $principal->facturaGuia,
                    'archivoOneDriveUrl' => $principal->archivoOneDriveUrl,
                    'observacion' => $principal->observacion,
                    'tipoConsumo' => $principal->tipoConsumo,
                    'programa' => $principal->programa,
                    'presentacion' => $principal->presentacion,
                    'materialPl' => $principal->materialPl,
                    'material' => $totalLineas > 1 ? $nombreMaterial.' y varios' : $nombreMaterial,
                    'codigo_pl' => $totalLineas > 1 ? 'Varios' : ($principal->materialPl?->CodigoPL ?: '—'),
                    'cantidad' => $this->calcularCajasBoxLoteEgreso($lineas),
                    'es_lote' => $totalLineas > 1,
                    'total_lineas' => $totalLineas,
                ];
            })
            ->sortByDesc(fn ($grupo) => ($grupo->fecha?->format('Y-m-d') ?? '').sprintf('%010d', $grupo->idMovimiento))
            ->values();
    }

    public function listarTraslados(array $filtros = []): Collection
    {
        $query = Traslado::query()
            ->with(['materialPl', 'almacenOrigen', 'almacenDestino'])
            ->where('estado', 1);

        $this->aplicarFiltrosTraslado($query, $filtros);

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('idTraslado')
            ->get();
    }

    public function stockDisponible(int $idMaterialPL, int $idAlmacen): float
    {
        return $this->stockAlmacenService->sumaStockEnAlmacen(
            $this->idsMaterialEquivalentes($idMaterialPL),
            $idAlmacen
        );
    }

    /**
     * Stock disponible por material de receta (agrupa duplicados por CodigoPL).
     *
     * @param  list<int>  $idsMaterial
     * @return array<int, float>
     */
    public function stocksDisponiblesPorMaterial(array $idsMaterial, int $idAlmacen): array
    {
        if ($idsMaterial === [] || $idAlmacen <= 0) {
            return [];
        }

        $materiales = MaterialProgramaPL::query()
            ->whereIn('idMaterialPL', $idsMaterial)
            ->get(['idMaterialPL', 'CodigoPL']);

        $codigos = $materiales
            ->pluck('CodigoPL')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $idsPorCodigo = collect();
        if ($codigos !== []) {
            $idsPorCodigo = MaterialProgramaPL::query()
                ->whereIn('CodigoPL', $codigos)
                ->get(['idMaterialPL', 'CodigoPL'])
                ->groupBy('CodigoPL')
                ->map(fn (Collection $grupo) => $grupo->pluck('idMaterialPL')->map(fn ($id) => (int) $id)->all());
        }

        $todosLosIds = $idsPorCodigo->flatten()->merge($idsMaterial)->unique()->values()->all();

        $stocksPorId = $this->stockAlmacenService->mapaStockEnAlmacen($todosLosIds, $idAlmacen);

        $stockPorCodigo = $idsPorCodigo->map(function (array $ids) use ($stocksPorId) {
            return collect($ids)->sum(fn (int $id) => (float) ($stocksPorId[$id] ?? 0));
        });

        $stocks = [];
        foreach ($materiales as $material) {
            $idMaterial = (int) $material->idMaterialPL;
            $codigo = trim((string) $material->CodigoPL);

            if ($codigo !== '' && $stockPorCodigo->has($codigo)) {
                $stocks[$idMaterial] = (float) $stockPorCodigo[$codigo];
                continue;
            }

            $stocks[$idMaterial] = (float) ($stocksPorId[$idMaterial] ?? 0);
        }

        return $stocks;
    }

    /**
     * @return list<int>
     */
    private function idsMaterialEquivalentes(int $idMaterialPL): array
    {
        $codigoPL = MaterialProgramaPL::query()
            ->where('idMaterialPL', $idMaterialPL)
            ->value('CodigoPL');

        $codigoPL = trim((string) $codigoPL);
        if ($codigoPL === '') {
            return [$idMaterialPL];
        }

        return MaterialProgramaPL::query()
            ->where('CodigoPL', $codigoPL)
            ->pluck('idMaterialPL')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Descuenta del idMaterialPL que realmente tiene stock (mismo CodigoPL).
     *
     * @return list<array{id_material_pl: int, cantidad: float}>
     */
    public function asignarMaterialesParaEgreso(int $idMaterialPL, int $idAlmacen, float $cantidad): array
    {
        $ids = $this->idsMaterialEquivalentes($idMaterialPL);

        $candidatos = collect($ids)
            ->map(fn (int $id) => [
                'id' => $id,
                'stock' => max(0, $this->stockPorIdMaterial($id, $idAlmacen)),
            ])
            ->filter(fn (array $fila) => $fila['stock'] > 0)
            ->sortByDesc('stock')
            ->values();

        $pendiente = round($cantidad, 2);
        $asignaciones = [];

        foreach ($candidatos as $candidato) {
            if ($pendiente <= 0) {
                break;
            }

            $tomar = min($pendiente, round($candidato['stock'], 2));
            if ($tomar <= 0) {
                continue;
            }

            $asignaciones[] = [
                'id_material_pl' => $candidato['id'],
                'cantidad' => $tomar,
            ];
            $pendiente = round($pendiente - $tomar, 2);
        }

        if ($asignaciones === []) {
            $mejorId = collect($ids)
                ->sortByDesc(fn (int $id) => $this->stockPorIdMaterial($id, $idAlmacen))
                ->first();

            return [[
                'id_material_pl' => $mejorId ?? $idMaterialPL,
                'cantidad' => $cantidad,
            ]];
        }

        return $asignaciones;
    }

    private function stockPorIdMaterial(int $idMaterialPL, int $idAlmacen): float
    {
        return $this->stockAlmacenService->sumaStockEnAlmacen([$idMaterialPL], $idAlmacen);
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        return MaterialProgramaLookup::porCodigo($codigo);
    }

    /**
     * @return list<array{id_material_pl: int, codigo_pl: string, nom_material: string}>
     */
    public function buscarMateriales(string $termino, int $limite = 50): array
    {
        return MaterialProgramaLookup::buscar($termino, $limite, 100)['materiales'];
    }

    public function crearIngreso(array $datos, ?int $idUsuario, ?UploadedFile $archivo = null): int
    {
        $lineas = $datos['lineas'] ?? [];
        if ($lineas === []) {
            throw new InvalidArgumentException('Debe agregar al menos un material al ingreso.');
        }

        $referenciaPdf = $this->referenciaDocumento($datos);
        $archivoData = $this->procesarArchivoPdf($archivo, MovimientoStock::TIPO_INGRESO, $referenciaPdf);

        $creados = DB::transaction(function () use ($datos, $idUsuario, $lineas, $archivoData) {
            $creados = 0;

            foreach ($lineas as $linea) {
                MovimientoStock::query()->create([
                    'tipo' => MovimientoStock::TIPO_INGRESO,
                    'idAlmacen' => (int) $datos['id_almacen'],
                    'idMaterialPL' => (int) $linea['id_material_pl'],
                    'cantidad' => $linea['cantidad'],
                    'fecha' => $datos['fecha'],
                    'observacion' => $this->nullableString($datos['observacion'] ?? null),
                    'facturaGuia' => null,
                    'factura' => $this->nullableString($datos['factura'] ?? null),
                    'guia' => $this->nullableString($datos['guia'] ?? null),
                    'archivoOneDriveId' => $archivoData['id'],
                    'archivoOneDriveUrl' => $archivoData['webUrl'],
                    'archivoNombre' => $archivoData['name'],
                    'idUsuario' => $idUsuario,
                    'fechaRegistro' => now(),
                    'estado' => 1,
                ]);

                $creados++;
            }

            return $creados;
        });

        $this->olvidarCacheGrillaRegistro();

        return $creados;
    }

    public function crearEgreso(array $datos, ?int $idUsuario, ?UploadedFile $archivo = null): int
    {
        $idAlmacen = (int) $datos['id_almacen'];
        $idPrograma = (int) $datos['id_programa'];
        $idPresentacion = (int) $datos['id_presentacion'];
        $lineas = app(ConsumoRecetaService::class)->resolverLineasConsumo(
            $idPrograma,
            $idPresentacion,
            $datos['lineas'] ?? [],
            $datos['pallets_fuera'] ?? null,
            $datos['conformacion_dentro'] ?? null
        );

        if ($lineas === []) {
            throw new InvalidArgumentException('No hay cantidades para retirar.');
        }

        $referenciaPdf = $this->referenciaDocumento($datos);
        $archivoData = $this->procesarArchivoPdf($archivo, MovimientoStock::TIPO_EGRESO, $referenciaPdf);

        $creados = DB::transaction(function () use ($datos, $idUsuario, $idAlmacen, $lineas, $archivoData) {
            return $this->conBloqueoStockAlmacen($idAlmacen, function () use ($datos, $idUsuario, $idAlmacen, $lineas, $archivoData) {
                $creados = 0;
                $ahora = now();

                foreach ($lineas as $linea) {
                    $idMaterialPL = (int) $linea['id_material_pl'];
                    $cantidad = (float) $linea['cantidad'];
                    $disponible = $this->stockDisponible($idMaterialPL, $idAlmacen);

                    if ($cantidad > $disponible) {
                        $nombre = $linea['nom_material'] ?: 'material';
                        throw new InvalidArgumentException(
                            'Stock insuficiente de '.$nombre.'. Disponible: '.number_format($disponible, 2, '.', '')
                        );
                    }

                    $asignaciones = $this->asignarMaterialesParaEgreso($idMaterialPL, $idAlmacen, $cantidad);

                    foreach ($asignaciones as $asignacion) {
                        MovimientoStock::query()->create([
                            'tipo' => MovimientoStock::TIPO_EGRESO,
                            'idAlmacen' => $idAlmacen,
                            'idMaterialPL' => (int) $asignacion['id_material_pl'],
                            'cantidad' => (float) $asignacion['cantidad'],
                            'fecha' => $datos['fecha'],
                            'observacion' => $this->nullableString($datos['observacion'] ?? null),
                            'idTipoConsumo' => (int) $datos['id_tipo_consumo'],
                            'idPrograma' => (int) $datos['id_programa'],
                            'idPresentacion' => (int) $datos['id_presentacion'],
                            'facturaGuia' => null,
                            'factura' => $this->nullableString($datos['factura'] ?? null),
                            'guia' => $this->nullableString($datos['guia'] ?? null),
                            'archivoOneDriveId' => $archivoData['id'],
                            'archivoOneDriveUrl' => $archivoData['webUrl'],
                            'archivoNombre' => $archivoData['name'],
                            'idUsuario' => $idUsuario,
                            'fechaRegistro' => $ahora,
                            'estado' => 1,
                        ]);

                        $creados++;
                    }
                }

                return $creados;
            });
        });

        $this->olvidarCacheGrillaRegistro();

        return $creados;
    }

    public function crearTraslado(array $datos, ?int $idUsuario, ?UploadedFile $archivo = null): Traslado
    {
        $idMaterialPL = (int) $datos['id_material_pl'];
        $idOrigen = (int) $datos['id_almacen_origen'];
        $idDestino = (int) $datos['id_almacen_destino'];
        $cantidad = (float) $datos['cantidad'];

        if ($idOrigen === $idDestino) {
            throw new InvalidArgumentException('El almacén origen y destino deben ser distintos.');
        }

        $referenciaPdf = $this->referenciaDocumento($datos);
        $archivoData = $this->procesarArchivoPdf($archivo, Traslado::TIPO_ARCHIVO, $referenciaPdf);

        $traslado = DB::transaction(function () use ($datos, $idUsuario, $idMaterialPL, $idOrigen, $idDestino, $cantidad, $archivoData) {
            return $this->conBloqueoStock($idOrigen, $idMaterialPL, function () use ($datos, $idUsuario, $idMaterialPL, $idOrigen, $idDestino, $cantidad, $archivoData) {
                $disponible = $this->stockDisponible($idMaterialPL, $idOrigen);
                if ($cantidad > $disponible) {
                    throw new InvalidArgumentException(
                        'Stock insuficiente en origen. Disponible: '.number_format($disponible, 2, '.', '')
                    );
                }

                $traslado = Traslado::query()->create([
                    'idMaterialPL' => $idMaterialPL,
                    'idAlmacenOrigen' => $idOrigen,
                    'idAlmacenDestino' => $idDestino,
                    'cantidad' => $cantidad,
                    'fecha' => $datos['fecha'],
                    'observacion' => $this->nullableString($datos['observacion'] ?? null),
                    'facturaGuia' => null,
                    'factura' => $this->nullableString($datos['factura'] ?? null),
                    'guia' => $this->nullableString($datos['guia'] ?? null),
                    'archivoOneDriveId' => $archivoData['id'],
                    'archivoOneDriveUrl' => $archivoData['webUrl'],
                    'archivoNombre' => $archivoData['name'],
                    'idUsuario' => $idUsuario,
                    'fechaRegistro' => now(),
                    'estado' => 1,
                ]);

                $payload = [
                    'idMaterialPL' => $idMaterialPL,
                    'cantidad' => $cantidad,
                    'fecha' => $datos['fecha'],
                    'observacion' => $this->nullableString($datos['observacion'] ?? null),
                    'idTraslado' => $traslado->idTraslado,
                    'idUsuario' => $idUsuario,
                    'fechaRegistro' => now(),
                    'estado' => 1,
                ];

                MovimientoStock::query()->create(array_merge($payload, [
                    'tipo' => MovimientoStock::TIPO_EGRESO,
                    'idAlmacen' => $idOrigen,
                ]));

                MovimientoStock::query()->create(array_merge($payload, [
                    'tipo' => MovimientoStock::TIPO_INGRESO,
                    'idAlmacen' => $idDestino,
                ]));

                return $traslado->load(['materialPl', 'almacenOrigen', 'almacenDestino']);
            });
        });

        $this->olvidarCacheGrillaRegistro();

        return $traslado;
    }

    public function anularMovimiento(int $idMovimiento): void
    {
        $movimiento = MovimientoStock::query()
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        if ($movimiento->tipo === MovimientoStock::TIPO_INGRESO) {
            $this->lineasIngresoRelacionadas($movimiento)->each(function (MovimientoStock $linea) {
                $linea->update(['estado' => 0]);
            });
            $this->olvidarCacheGrillaRegistro();

            return;
        }

        if ($movimiento->tipo === MovimientoStock::TIPO_EGRESO) {
            $this->lineasEgresoRelacionadas($movimiento)->each(function (MovimientoStock $linea) {
                $linea->update(['estado' => 0]);
            });
            $this->olvidarCacheGrillaRegistro();

            return;
        }

        $movimiento->update(['estado' => 0]);
        $this->olvidarCacheGrillaRegistro();
    }

    public function anularTraslado(int $idTraslado): void
    {
        DB::transaction(function () use ($idTraslado) {
            $traslado = Traslado::query()->where('estado', 1)->findOrFail($idTraslado);
            $traslado->update(['estado' => 0]);

            MovimientoStock::query()
                ->where('idTraslado', $idTraslado)
                ->where('estado', 1)
                ->update(['estado' => 0]);
        });

        $this->olvidarCacheGrillaRegistro();
    }

    public function actualizarEgreso(int $idMovimiento, array $datos, ?UploadedFile $archivo = null): MovimientoStock
    {
        $principal = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        $idAlmacen = (int) $datos['id_almacen'];
        $lineasResueltas = collect(app(ConsumoRecetaService::class)->resolverLineasConsumo(
            (int) $datos['id_programa'],
            (int) $datos['id_presentacion'],
            $datos['lineas'] ?? [],
            $datos['pallets_fuera'] ?? null,
            $datos['conformacion_dentro'] ?? null
        ))->keyBy('id_material_pl');

        if ($lineasResueltas->isEmpty()) {
            throw new InvalidArgumentException('No hay cantidades para retirar.');
        }

        $archivoData = $this->resolverArchivoPdf($archivo, MovimientoStock::TIPO_EGRESO, $datos, $principal);
        $lineasInput = collect($datos['lineas'] ?? [])->keyBy(fn ($linea) => (int) ($linea['id_material_pl'] ?? 0));

        $resultado = DB::transaction(function () use ($principal, $datos, $idAlmacen, $lineasResueltas, $lineasInput, $archivoData) {
            return $this->conBloqueoStockAlmacen($idAlmacen, function () use ($principal, $datos, $idAlmacen, $lineasResueltas, $lineasInput, $archivoData) {
                $lineasActuales = $this->lineasEgresoRelacionadas($principal);

                $camposComunes = [
                    'idAlmacen' => $idAlmacen,
                    'fecha' => $datos['fecha'],
                    'observacion' => $this->nullableString($datos['observacion'] ?? null),
                    'idTipoConsumo' => (int) $datos['id_tipo_consumo'],
                    'idPrograma' => (int) $datos['id_programa'],
                    'idPresentacion' => (int) $datos['id_presentacion'],
                    'factura' => $this->nullableString($datos['factura'] ?? null),
                    'guia' => $this->nullableString($datos['guia'] ?? null),
                    'archivoOneDriveId' => $archivoData['id'],
                    'archivoOneDriveUrl' => $archivoData['webUrl'],
                    'archivoNombre' => $archivoData['name'],
                ];

                $idsActualizados = [];

                foreach ($lineasResueltas as $idMaterialPL => $lineaData) {
                    $cantidad = (float) $lineaData['cantidad'];
                    $input = $lineasInput->get((int) $idMaterialPL, []);
                    $idLinea = isset($input['id_movimiento']) ? (int) $input['id_movimiento'] : null;
                    $linea = $idLinea ? $lineasActuales->firstWhere('idMovimiento', $idLinea) : null;

                    $disponible = $this->stockDisponible((int) $idMaterialPL, $idAlmacen);
                    if ($linea) {
                        $disponible += (float) $linea->cantidad;
                    }

                    if ($cantidad > $disponible) {
                        $nombre = $lineaData['nom_material'] ?: 'material';
                        throw new InvalidArgumentException(
                            'Stock insuficiente de '.$nombre.'. Disponible: '.number_format($disponible, 2, '.', '')
                        );
                    }

                    $idDescontar = (int) ($this->asignarMaterialesParaEgreso((int) $idMaterialPL, $idAlmacen, $cantidad)[0]['id_material_pl'] ?? $idMaterialPL);

                    if ($linea) {
                        $linea->update(array_merge($camposComunes, [
                            'idMaterialPL' => $idDescontar,
                            'cantidad' => $cantidad,
                        ]));
                        $idsActualizados[] = (int) $linea->idMovimiento;

                        continue;
                    }

                    $nueva = MovimientoStock::query()->create(array_merge($camposComunes, [
                        'tipo' => MovimientoStock::TIPO_EGRESO,
                        'idMaterialPL' => $idDescontar,
                        'cantidad' => $cantidad,
                        'idUsuario' => $principal->idUsuario,
                        'fechaRegistro' => $principal->fechaRegistro,
                        'estado' => 1,
                    ]));
                    $idsActualizados[] = (int) $nueva->idMovimiento;
                }

                foreach ($lineasActuales as $linea) {
                    if (! in_array((int) $linea->idMovimiento, $idsActualizados, true)) {
                        $linea->update(['estado' => 0]);
                    }
                }

                return $principal->fresh(['materialPl', 'almacen', 'tipoConsumo', 'programa', 'presentacion']);
            });
        });

        $this->olvidarCacheGrillaRegistro();

        return $resultado;
    }

    public function actualizarTraslado(int $idTraslado, array $datos, ?UploadedFile $archivo = null): Traslado
    {
        $traslado = DB::transaction(function () use ($idTraslado, $datos, $archivo) {
            $traslado = Traslado::query()->where('estado', 1)->findOrFail($idTraslado);

            $idMaterialPL = (int) $datos['id_material_pl'];
            $idOrigen = (int) $datos['id_almacen_origen'];
            $idDestino = (int) $datos['id_almacen_destino'];
            $cantidad = (float) $datos['cantidad'];

            if ($idOrigen === $idDestino) {
                throw new InvalidArgumentException('El almacén origen y destino deben ser distintos.');
            }

            $disponible = $this->stockDisponible($idMaterialPL, $idOrigen);
            if ($traslado->idMaterialPL === $idMaterialPL && $traslado->idAlmacenOrigen === $idOrigen) {
                $disponible += (float) $traslado->cantidad;
            }

            if ($cantidad > $disponible) {
                throw new InvalidArgumentException(
                    'Stock insuficiente en origen. Disponible: '.number_format($disponible, 2, '.', '')
                );
            }

            $archivoData = $this->resolverArchivoPdf($archivo, Traslado::TIPO_ARCHIVO, $datos, $traslado);

            $payload = [
                'idMaterialPL' => $idMaterialPL,
                'cantidad' => $cantidad,
                'fecha' => $datos['fecha'],
                'observacion' => $this->nullableString($datos['observacion'] ?? null),
                'factura' => $this->nullableString($datos['factura'] ?? null),
                'guia' => $this->nullableString($datos['guia'] ?? null),
                'archivoOneDriveId' => $archivoData['id'],
                'archivoOneDriveUrl' => $archivoData['webUrl'],
                'archivoNombre' => $archivoData['name'],
            ];

            $traslado->update(array_merge($payload, [
                'idAlmacenOrigen' => $idOrigen,
                'idAlmacenDestino' => $idDestino,
            ]));

            $movimientos = MovimientoStock::query()
                ->where('idTraslado', $idTraslado)
                ->where('estado', 1)
                ->get();

            foreach ($movimientos as $movimiento) {
                $movimiento->update(array_merge($payload, [
                    'idAlmacen' => $movimiento->tipo === MovimientoStock::TIPO_INGRESO ? $idDestino : $idOrigen,
                ]));
            }

            return $traslado->fresh(['materialPl', 'almacenOrigen', 'almacenDestino']);
        });

        $this->olvidarCacheGrillaRegistro();

        return $traslado;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function validarEdicionIngreso(MovimientoStock $movimiento, array $datos): void
    {
        $lineasActuales = $this->lineasIngresoRelacionadas($movimiento);
        $lineasNuevas = $datos['lineas'] ?? [];
        $idsConservados = collect($lineasNuevas)
            ->pluck('id_movimiento')
            ->filter()
            ->map(fn ($id) => (int) $id);

        foreach ($lineasActuales as $linea) {
            if ($idsConservados->contains($linea->idMovimiento)) {
                continue;
            }

            $this->validarReduccionIngreso($linea, 0.0);
        }

        foreach ($lineasNuevas as $lineaData) {
            $idMovimiento = isset($lineaData['id_movimiento']) ? (int) $lineaData['id_movimiento'] : null;
            $nuevaCantidad = (float) $lineaData['cantidad'];

            if (! $idMovimiento) {
                continue;
            }

            $linea = $lineasActuales->firstWhere('idMovimiento', $idMovimiento);
            if ($linea) {
                $this->validarReduccionIngreso($linea, $nuevaCantidad);
            }
        }
    }

    public function actualizarIngreso(int $idMovimiento, array $datos, ?UploadedFile $archivo = null): int
    {
        $principal = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_INGRESO)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        $this->validarEdicionIngreso($principal, $datos);

        $actualizados = DB::transaction(function () use ($principal, $datos, $archivo) {
            $lineasActuales = $this->lineasIngresoRelacionadas($principal);
            $lineasNuevas = $datos['lineas'] ?? [];
            $archivoData = $this->resolverArchivoPdf($archivo, MovimientoStock::TIPO_INGRESO, $datos, $principal);

            $camposComunes = [
                'idAlmacen' => (int) $datos['id_almacen'],
                'fecha' => $datos['fecha'],
                'observacion' => $this->nullableString($datos['observacion'] ?? null),
                'factura' => $this->nullableString($datos['factura'] ?? null),
                'guia' => $this->nullableString($datos['guia'] ?? null),
                'archivoOneDriveId' => $archivoData['id'],
                'archivoOneDriveUrl' => $archivoData['webUrl'],
                'archivoNombre' => $archivoData['name'],
            ];

            $idsEnRequest = collect($lineasNuevas)
                ->pluck('id_movimiento')
                ->filter()
                ->map(fn ($id) => (int) $id);

            foreach ($lineasActuales as $linea) {
                if (! $idsEnRequest->contains($linea->idMovimiento)) {
                    $linea->update(['estado' => 0]);
                }
            }

            $actualizados = 0;

            foreach ($lineasNuevas as $lineaData) {
                $idMaterialPL = (int) $lineaData['id_material_pl'];
                $cantidad = (float) $lineaData['cantidad'];
                $idLinea = isset($lineaData['id_movimiento']) ? (int) $lineaData['id_movimiento'] : null;
                $linea = $idLinea ? $lineasActuales->firstWhere('idMovimiento', $idLinea) : null;

                if ($linea) {
                    $linea->update(array_merge($camposComunes, [
                        'idMaterialPL' => $idMaterialPL,
                        'cantidad' => $cantidad,
                    ]));
                    $actualizados++;

                    continue;
                }

                MovimientoStock::query()->create(array_merge($camposComunes, [
                    'tipo' => MovimientoStock::TIPO_INGRESO,
                    'idMaterialPL' => $idMaterialPL,
                    'cantidad' => $cantidad,
                    'idUsuario' => $principal->idUsuario,
                    'fechaRegistro' => $principal->fechaRegistro,
                    'estado' => 1,
                ]));

                $actualizados++;
            }

            return $actualizados;
        });

        $this->olvidarCacheGrillaRegistro();

        return $actualizados;
    }

    private function validarReduccionIngreso(MovimientoStock $linea, float $nuevaCantidad): void
    {
        $cantidadActual = (float) $linea->cantidad;

        if ($nuevaCantidad >= $cantidadActual) {
            return;
        }

        $delta = $cantidadActual - $nuevaCantidad;
        $stock = $this->stockDisponible((int) $linea->idMaterialPL, (int) $linea->idAlmacen);

        if ($stock < $delta) {
            throw new InvalidArgumentException(
                'No se puede reducir o quitar el material: stock insuficiente en almacén.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{id: mixed, webUrl: mixed, name: mixed}
     */
    private function resolverArchivoPdf(
        ?UploadedFile $archivo,
        string $tipo,
        array $datos,
        MovimientoStock|Traslado $registro
    ): array {
        if ($archivo) {
            try {
                return $this->procesarArchivoPdfStrict($archivo, $tipo, $this->referenciaDocumento($datos));
            } catch (\Throwable $exception) {
                Log::warning('No se pudo reemplazar el PDF; se conserva el archivo anterior.', [
                    'tipo' => $tipo,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'id' => $registro->archivoOneDriveId,
                    'webUrl' => $registro->archivoOneDriveUrl,
                    'name' => $registro->archivoNombre,
                ];
            }
        }

        return [
            'id' => $registro->archivoOneDriveId,
            'webUrl' => $registro->archivoOneDriveUrl,
            'name' => $registro->archivoNombre,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detalleMovimiento(int $idMovimiento, bool $contextoEdicion = false): array
    {
        $movimiento = MovimientoStock::query()
            ->with(['almacen', 'materialPl', 'tipoConsumo', 'programa', 'presentacion', 'usuario.persona'])
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        $lineas = $movimiento->tipo === MovimientoStock::TIPO_INGRESO
            ? $this->lineasIngresoRelacionadas($movimiento)
            : $this->lineasEgresoRelacionadas($movimiento);

        return $this->formatearDetalleMovimiento($movimiento, $lineas, $contextoEdicion);
    }

    /**
     * @return array<string, mixed>
     */
    public function detalleTraslado(int $idTraslado): array
    {
        $traslado = Traslado::query()
            ->with([
                'materialPl',
                'almacenOrigen',
                'almacenDestino',
                'usuario.persona',
                'movimientos.almacen',
            ])
            ->where('estado', 1)
            ->findOrFail($idTraslado);

        $usuario = $traslado->usuario;
        $persona = $usuario?->persona;

        return [
            'tipo' => 'traslado',
            'titulo' => 'Detalle de traslado',
            'id' => $traslado->idTraslado,
            'usuario' => $persona?->nombreCompleto() ?: ($usuario?->Usuario ?: '—'),
            'id_usuario' => $usuario?->idUsuario,
            'usuario_email' => $persona?->Correo ?: ($usuario?->Usuario ?: null),
            'fecha_operacion' => $traslado->fecha?->format('d/m/Y'),
            'fecha_registro' => $traslado->fechaRegistro?->format('d/m/Y H:i'),
            'almacen' => null,
            'almacen_origen' => $traslado->almacenOrigen?->codigo,
            'almacen_destino' => $traslado->almacenDestino?->codigo,
            'factura' => $traslado->factura,
            'guia' => DocumentoGuiaSupport::resolver($traslado->guia, $traslado->factura, $traslado->facturaGuia),
            'factura_guia' => null,
            'observacion' => $traslado->observacion,
            'pdf_url' => $traslado->archivoOneDriveUrl,
            'pdf_nombre' => $traslado->archivoNombre,
            'lineas' => [[
                'codigo_pl' => $traslado->materialPl?->CodigoPL,
                'material' => $traslado->materialPl?->nomMaterial,
                'cantidad' => $this->formatearCantidad($traslado->cantidad, (int) $traslado->idMaterialPL),
                'almacen' => ($traslado->almacenOrigen?->codigo ?? '—').' → '.($traslado->almacenDestino?->codigo ?? '—'),
            ]],
            'movimientos_vinculados' => $traslado->movimientos
                ->sortBy('tipo')
                ->values()
                ->map(fn (MovimientoStock $mov) => [
                    'tipo' => $mov->tipo === MovimientoStock::TIPO_INGRESO ? 'Ingreso' : 'Consumo',
                    'almacen' => $mov->almacen?->codigo,
                    'cantidad' => $this->formatearCantidad($mov->cantidad, (int) $mov->idMaterialPL),
                ])
                ->all(),
            'datos_edicion' => [
                'id_almacen_origen' => $traslado->idAlmacenOrigen,
                'id_almacen_destino' => $traslado->idAlmacenDestino,
                'id_material_pl' => $traslado->idMaterialPL,
                'codigo_pl' => $traslado->materialPl?->CodigoPL,
                'nom_material' => $traslado->materialPl?->nomMaterial,
                'cantidad' => (float) $traslado->cantidad,
                'fecha' => $traslado->fecha?->format('Y-m-d'),
                'factura' => $traslado->factura,
                'guia' => $traslado->guia,
                'observacion' => $traslado->observacion,
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function idsMovimientosLoteEgreso(int $idMovimiento): array
    {
        if ($idMovimiento <= 0) {
            return [];
        }

        $movimiento = MovimientoStock::query()->find($idMovimiento);
        if (! $movimiento || $movimiento->tipo !== MovimientoStock::TIPO_EGRESO) {
            return [$idMovimiento];
        }

        return $this->lineasEgresoRelacionadas($movimiento)
            ->pluck('idMovimiento')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, MovimientoStock>
     */
    private function lineasEgresoRelacionadas(MovimientoStock $movimiento): Collection
    {
        $query = MovimientoStock::query()
            ->with(['materialPl', 'almacen', 'tipoConsumo', 'programa', 'presentacion'])
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->whereNull('idTraslado')
            ->where('idAlmacen', $movimiento->idAlmacen)
            ->whereDate('fecha', $movimiento->fecha)
            ->where('idUsuario', $movimiento->idUsuario)
            ->where('fechaRegistro', $movimiento->fechaRegistro)
            ->where('idTipoConsumo', $movimiento->idTipoConsumo)
            ->where('idPrograma', $movimiento->idPrograma)
            ->where('idPresentacion', $movimiento->idPresentacion);
        $this->aplicarCriteriosLoteDocumento($query, $movimiento);

        return $query
            ->orderBy('idMovimiento')
            ->get();
    }

    /**
     * @return Collection<int, MovimientoStock>
     */
    private function lineasIngresoRelacionadas(MovimientoStock $movimiento): Collection
    {
        $query = MovimientoStock::query()
            ->with(['materialPl', 'almacen'])
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_INGRESO)
            ->whereNull('idTraslado')
            ->where('idAlmacen', $movimiento->idAlmacen)
            ->whereDate('fecha', $movimiento->fecha)
            ->where('idUsuario', $movimiento->idUsuario)
            ->where('fechaRegistro', $movimiento->fechaRegistro);
        $this->aplicarCriteriosLoteDocumento($query, $movimiento);

        return $query
            ->orderBy('idMovimiento')
            ->get();
    }

    private function aplicarCriteriosLoteDocumento($query, MovimientoStock $movimiento): void
    {
        $query
            ->when(
                $movimiento->factura,
                fn ($q) => $q->where('factura', $movimiento->factura),
                fn ($q) => $q->whereNull('factura')
            )
            ->when(
                $movimiento->guia,
                fn ($q) => $q->where('guia', $movimiento->guia),
                fn ($q) => $q->whereNull('guia')
            )
            ->when(
                $movimiento->observacion,
                fn ($q) => $q->where('observacion', $movimiento->observacion),
                fn ($q) => $q->whereNull('observacion')
            )
            ->when(
                $movimiento->archivoOneDriveId,
                fn ($q) => $q->where('archivoOneDriveId', $movimiento->archivoOneDriveId),
                fn ($q) => $q->whereNull('archivoOneDriveId')
            );
    }

    private function claveLoteIngreso(MovimientoStock $movimiento): string
    {
        return implode('|', [
            (string) $movimiento->idAlmacen,
            $movimiento->fecha?->format('Y-m-d') ?? '',
            (string) ($movimiento->idUsuario ?? ''),
            $movimiento->fechaRegistro?->format('Y-m-d H:i:s') ?? '',
            $movimiento->factura ?? '',
            $movimiento->guia ?? '',
            $movimiento->observacion ?? '',
            $movimiento->archivoOneDriveId ?? '',
        ]);
    }

    private function claveLoteEgreso(MovimientoStock $movimiento): string
    {
        return implode('|', [
            (string) $movimiento->idAlmacen,
            $movimiento->fecha?->format('Y-m-d') ?? '',
            (string) ($movimiento->idUsuario ?? ''),
            $movimiento->fechaRegistro?->format('Y-m-d H:i:s') ?? '',
            (string) ($movimiento->idTipoConsumo ?? ''),
            (string) ($movimiento->idPrograma ?? ''),
            (string) ($movimiento->idPresentacion ?? ''),
            $movimiento->factura ?? '',
            $movimiento->guia ?? '',
            $movimiento->observacion ?? '',
            $movimiento->archivoOneDriveId ?? '',
        ]);
    }

    /**
     * @param  list<string>  $columnasLote
     * @param  list<string>  $withRelations
     */
    private function listarMovimientosPaginadosPorLote(
        string $tipo,
        array $columnasLote,
        array $withRelations,
        array $filtros,
        Request $request
    ): LengthAwarePaginator {
        $perPage = GridPagination::PER_PAGE;
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;
        $alias = $tipo === MovimientoStock::TIPO_EGRESO ? 'lotes_egreso' : 'lotes_ingreso';
        $tabla = (new MovimientoStock)->getTable();
        $columnasLoteCalificadas = array_map(
            static fn (string $columna): string => $tabla.'.'.$columna,
            $columnasLote
        );

        $base = MovimientoStock::query()
            ->where($tabla.'.estado', 1)
            ->where($tabla.'.tipo', $tipo)
            ->whereNull($tabla.'.idTraslado');

        $this->aplicarFiltrosMovimiento($base, $filtros);

        $grouped = (clone $base)
            ->select($columnasLoteCalificadas)
            ->selectRaw('MAX('.$tabla.'.idMovimiento) AS id_principal')
            ->selectRaw('SUM('.$tabla.'.cantidad) AS cantidad_total')
            ->selectRaw('COUNT(*) AS total_lineas')
            ->groupBy($columnasLoteCalificadas);

        $lotes = DB::query()
            ->fromSub($grouped, $alias)
            ->orderByDesc('fecha')
            ->orderByDesc('id_principal')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $total = $this->contarLotesPaginados($base, $columnasLoteCalificadas, $tipo, $filtros, $alias);

        $idsPrincipales = $lotes->pluck('id_principal')->filter()->map(fn ($id) => (int) $id)->all();

        $principales = $idsPrincipales === []
            ? collect()
            : MovimientoStock::query()
                ->select(self::COLUMNAS_GRID_PRINCIPAL)
                ->with($withRelations)
                ->whereIn('idMovimiento', $idsPrincipales)
                ->get()
                ->keyBy('idMovimiento');

        $items = $lotes->map(fn ($lote) => $this->mapearLotePaginado(
            $lote,
            $principales->get((int) $lote->id_principal),
            $tipo
        ))->values();

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
     * @param  \Illuminate\Database\Eloquent\Builder<MovimientoStock>  $base
     * @param  list<string>  $columnasLote
     * @param  array<string, mixed>  $filtros
     */
    private function contarLotesPaginados($base, array $columnasLote, string $tipo, array $filtros, string $alias): int
    {
        $version = (int) Cache::get('registro.grid.cache_version', 0);
        $cacheKey = 'registro.lotes.total.v'.$version.'.'.$tipo.'.'.md5(json_encode([
            'fecha' => $filtros['fecha'] ?? null,
            'id_almacen' => $filtros['id_almacen'] ?? null,
            'buscar' => $filtros['buscar'] ?? '',
        ]));

        return (int) Cache::remember($cacheKey, self::CACHE_TOTAL_LOTES_TTL, function () use ($base, $columnasLote, $alias) {
            return (int) DB::query()
                ->fromSub(
                    (clone $base)->selectRaw('1')->groupBy($columnasLote),
                    $alias
                )
                ->count();
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function listarGrillaRegistroCacheada(
        string $tipo,
        array $filtros,
        Request $request,
        callable $resolver
    ): LengthAwarePaginator {
        $page = max(1, (int) $request->query('page', 1));
        $version = (int) Cache::get('registro.grid.cache_version', 0);
        $cacheKey = 'registro.grid.v'.$version.'.'.$tipo.'.'.md5(json_encode([
            'fecha' => $filtros['fecha'] ?? null,
            'id_almacen' => $filtros['id_almacen'] ?? null,
            'buscar' => $filtros['buscar'] ?? '',
        ])).'.p'.$page;

        return Cache::remember($cacheKey, self::CACHE_GRID_REGISTRO_TTL, $resolver);
    }

    private function olvidarCacheGrillaRegistro(): void
    {
        $version = (int) Cache::get('registro.grid.cache_version', 0);
        Cache::forever('registro.grid.cache_version', $version + 1);
    }

    private function mapearLotePaginado(object $lote, ?MovimientoStock $principal, string $tipo): object
    {
        $totalLineas = (int) $lote->total_lineas;
        $nombreMaterial = $principal?->materialPl?->nomMaterial ?: '—';

        $item = (object) [
            'idMovimiento' => (int) $lote->id_principal,
            'almacen' => $principal?->almacen,
            'fecha' => $principal?->fecha,
            'factura' => $principal?->factura,
            'guia' => $principal?->guia ?: $principal?->facturaGuia,
            'archivoOneDriveUrl' => $principal?->archivoOneDriveUrl,
            'observacion' => $principal?->observacion,
            'material' => $totalLineas > 1 ? $nombreMaterial.' y varios' : $nombreMaterial,
            'codigo_pl' => $totalLineas > 1 ? 'Varios' : ($principal?->materialPl?->CodigoPL ?: '—'),
            'cantidad' => $tipo === MovimientoStock::TIPO_EGRESO
                ? $this->calcularCajasBoxLoteEgresoDesdePrincipal($principal)
                : (float) $lote->cantidad_total,
            'es_lote' => $totalLineas > 1,
            'total_lineas' => $totalLineas,
        ];

        if ($tipo === MovimientoStock::TIPO_EGRESO) {
            $item->tipoConsumo = $principal?->tipoConsumo;
            $item->programa = $principal?->programa;
            $item->presentacion = $principal?->presentacion;
            $item->materialPl = $principal?->materialPl;
        }

        return $item;
    }

    /**
     * @param  Collection<int, MovimientoStock>  $lineas
     */
    private function calcularCajasBoxLoteEgreso(Collection $lineas): float
    {
        $principal = $lineas->first();
        if ($principal === null) {
            return 0.0;
        }

        $idPrograma = (int) ($principal->idPrograma ?? 0);
        $idPresentacion = (int) ($principal->idPresentacion ?? 0);
        if ($idPrograma <= 0 || $idPresentacion <= 0) {
            return 0.0;
        }

        return app(ConsumoRecetaService::class)->cajasDesdeMovimientosConsumo(
            $idPrograma,
            $idPresentacion,
            $lineas
        );
    }

    private function calcularCajasBoxLoteEgresoDesdePrincipal(?MovimientoStock $principal): float
    {
        if ($principal === null) {
            return 0.0;
        }

        $referencia = MovimientoStock::query()
            ->select(array_merge(self::COLUMNAS_GRID_PRINCIPAL, ['archivoOneDriveId']))
            ->find($principal->idMovimiento);

        if ($referencia === null) {
            return 0.0;
        }

        $query = MovimientoStock::query()
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->where('idAlmacen', $referencia->idAlmacen)
            ->whereDate('fecha', $referencia->fecha);

        foreach (self::COLUMNAS_LOTE_EGRESO as $columna) {
            if ($columna === 'idAlmacen' || $columna === 'fecha') {
                continue;
            }

            $this->aplicarFiltroColumnaLote($query, $columna, $referencia->{$columna} ?? null);
        }

        return $this->calcularCajasBoxLoteEgreso($query->get());
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MovimientoStock>  $query
     */
    private function aplicarFiltroColumnaLote($query, string $columna, mixed $valor): void
    {
        if ($valor === null || $valor === '') {
            $query->where(function ($sub) use ($columna) {
                $sub->whereNull($columna)->orWhere($columna, '');
            });

            return;
        }

        $query->where($columna, $valor);
    }

    /**
     * @param  Collection<int, MovimientoStock>  $lineas
     * @return array<string, mixed>
     */
    private function formatearDetalleMovimiento(MovimientoStock $movimiento, Collection $lineas, bool $contextoEdicion = false): array
    {
        $usuario = $movimiento->usuario;
        $persona = $usuario?->persona;
        $esIngreso = $movimiento->tipo === MovimientoStock::TIPO_INGRESO;
        $consumoReceta = app(ConsumoRecetaService::class);

        $payload = [
            'tipo' => $movimiento->tipo,
            'titulo' => $esIngreso ? 'Detalle de ingreso' : 'Detalle de consumo',
            'id' => $movimiento->idMovimiento,
            'usuario' => $persona?->nombreCompleto() ?: ($usuario?->Usuario ?: '—'),
            'id_usuario' => $usuario?->idUsuario,
            'usuario_email' => $persona?->Correo ?: ($usuario?->Usuario ?: null),
            'fecha_operacion' => $movimiento->fecha?->format('d/m/Y'),
            'fecha_registro' => $movimiento->fechaRegistro?->format('d/m/Y H:i'),
            'almacen' => $movimiento->almacen?->codigo,
            'almacen_origen' => null,
            'almacen_destino' => null,
            'motivo' => $esIngreso ? null : ($movimiento->tipoConsumo?->nomTipoConsumo),
            'programa' => $esIngreso ? null : ($movimiento->programa?->nomPrograma),
            'presentacion' => $esIngreso ? null : ($movimiento->presentacion?->nomPresentacion),
            'factura' => $movimiento->factura,
            'guia' => DocumentoGuiaSupport::resolver($movimiento->guia, $movimiento->factura, $movimiento->facturaGuia),
            'factura_guia' => null,
            'observacion' => $movimiento->observacion,
            'pdf_url' => $movimiento->archivoOneDriveUrl,
            'pdf_nombre' => $movimiento->archivoNombre,
            'lineas' => $lineas->map(fn (MovimientoStock $linea) => [
                'id_movimiento' => $linea->idMovimiento,
                'codigo_pl' => $linea->materialPl?->CodigoPL,
                'material' => $linea->materialPl?->nomMaterial,
                'cantidad' => $this->formatearCantidad($linea->cantidad, $esIngreso ? null : (int) $linea->idMaterialPL),
                'almacen' => $linea->almacen?->codigo,
            ])->values()->all(),
            'movimientos_vinculados' => [],
            'datos_edicion' => $esIngreso ? [
                'id_almacen' => $movimiento->idAlmacen,
                'fecha' => $movimiento->fecha?->format('Y-m-d'),
                'factura' => $movimiento->factura,
                'guia' => $movimiento->guia,
                'observacion' => $movimiento->observacion,
                'lineas' => $lineas->map(fn (MovimientoStock $linea) => [
                    'id_movimiento' => $linea->idMovimiento,
                    'id_material_pl' => $linea->idMaterialPL,
                    'cantidad' => (float) $linea->cantidad,
                    'codigo_pl' => $linea->materialPl?->CodigoPL,
                    'nom_material' => $linea->materialPl?->nomMaterial,
                ])->values()->all(),
            ] : [
                'id_almacen' => $movimiento->idAlmacen,
                'id_programa_general' => $movimiento->programa?->idProgramaGeneral,
                'id_programa' => $movimiento->idPrograma,
                'id_presentacion' => $movimiento->idPresentacion,
                'id_tipo_consumo' => $movimiento->idTipoConsumo,
                'motivo_nombre' => $movimiento->tipoConsumo?->nomTipoConsumo,
                'fecha' => $movimiento->fecha?->format('Y-m-d'),
                'factura' => $movimiento->factura,
                'guia' => $movimiento->guia,
                'observacion' => $movimiento->observacion,
                'lineas' => $consumoReceta->lineasEdicionDesdeMovimientos(
                    (int) $movimiento->idPrograma,
                    (int) $movimiento->idPresentacion,
                    $lineas
                ),
                'programa_nombre' => $movimiento->programa?->nomPrograma,
                'presentacion_nombre' => $movimiento->presentacion?->nomPresentacion,
                'excluir_ids_movimiento' => $lineas
                    ->pluck('idMovimiento')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ],
        ];

        if ($contextoEdicion && ! $esIngreso) {
            $idPrograma = (int) $movimiento->idPrograma;
            $idPresentacion = (int) $movimiento->idPresentacion;
            $excluirIds = $lineas
                ->pluck('idMovimiento')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $payload['contexto_edicion'] = [
                'presentaciones' => $consumoReceta
                    ->presentacionesDePrograma($idPrograma)
                    ->map(fn ($item) => [
                        'id_presentacion' => $item->idPresentacion,
                        'nom_presentacion' => $item->nomPresentacion,
                    ])
                    ->values()
                    ->all(),
                'materiales' => $consumoReceta->materialesDePresentacion(
                    $idPrograma,
                    $idPresentacion,
                    $excluirIds !== [] ? $excluirIds : null,
                    (int) $movimiento->idAlmacen
                ),
                'presentacion' => $consumoReceta->parametrosPresentacion($idPrograma, $idPresentacion),
            ];
        }

        return $payload;
    }

    /** @var array<int, string|null> */
    private array $nomFamiliaMaterialCache = [];

    private function formatearCantidad(float|string|null $cantidad, ?int $idMaterialPL = null): string
    {
        if ($idMaterialPL === null) {
            // Ingreso: mismo formato que en produccion (2 decimales fijos).
            return number_format((float) $cantidad, 2, '.', '');
        }

        $nomFamilia = $idMaterialPL ? $this->nomFamiliaMaterial($idMaterialPL) : null;

        return CantidadCajaSupport::formatearCantidadFlexible(
            (float) $cantidad,
            CantidadCajaSupport::decimales($nomFamilia)
        );
    }

    private function nomFamiliaMaterial(int $idMaterialPL): ?string
    {
        if (array_key_exists($idMaterialPL, $this->nomFamiliaMaterialCache)) {
            return $this->nomFamiliaMaterialCache[$idMaterialPL];
        }

        $idFamilia = ProgramaFila::query()
            ->where('idMaterialPL', $idMaterialPL)
            ->whereNotNull('idFamilia')
            ->value('idFamilia');

        if ($idFamilia === null) {
            $idFamilia = Proveedor::query()
                ->where('idMaterialPL', $idMaterialPL)
                ->where('estado', 1)
                ->whereNotNull('idFamilia')
                ->value('idFamilia');
        }

        $nom = $idFamilia ? Familia::query()->whereKey($idFamilia)->value('nomFamilia') : null;

        return $this->nomFamiliaMaterialCache[$idMaterialPL] = $nom ? (string) $nom : null;
    }

    private function listarPorTipo(string $tipo, array $filtros = []): Collection
    {
        $query = MovimientoStock::query()
            ->with(['materialPl', 'almacen', 'tipoConsumo', 'programa', 'presentacion'])
            ->where('estado', 1)
            ->where('tipo', $tipo)
            ->whereNull('idTraslado');

        $this->aplicarFiltrosMovimiento($query, $filtros);

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('idMovimiento')
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MovimientoStock>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosMovimiento($query, array $filtros): void
    {
        if (! empty($filtros['fecha'])) {
            $query->whereDate('fecha', $filtros['fecha']);
        }

        if (! empty($filtros['id_almacen'])) {
            $query->where('idAlmacen', (int) $filtros['id_almacen']);
        }

        if (! empty($filtros['buscar'])) {
            $termino = '%'.addcslashes((string) $filtros['buscar'], '%_\\').'%';
            $tabla = $query->getModel()->getTable();
            $query->leftJoin('MaterialProgramaPL as mpl_buscar', $tabla.'.idMaterialPL', '=', 'mpl_buscar.idMaterialPL')
                ->where(function ($consulta) use ($termino, $tabla) {
                    $consulta->where($tabla.'.factura', 'like', $termino)
                        ->orWhere($tabla.'.guia', 'like', $termino)
                        ->orWhere($tabla.'.facturaGuia', 'like', $termino)
                        ->orWhere('mpl_buscar.CodigoPL', 'like', $termino);
                })
                ->select($tabla.'.*');
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Traslado>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosTraslado($query, array $filtros): void
    {
        if (! empty($filtros['fecha'])) {
            $query->whereDate('fecha', $filtros['fecha']);
        }

        if (! empty($filtros['id_almacen'])) {
            $idAlmacen = (int) $filtros['id_almacen'];
            $query->where(function ($consulta) use ($idAlmacen) {
                $consulta->where('idAlmacenOrigen', $idAlmacen)
                    ->orWhere('idAlmacenDestino', $idAlmacen);
            });
        }

        if (! empty($filtros['buscar'])) {
            $termino = '%'.addcslashes((string) $filtros['buscar'], '%_\\').'%';
            $tabla = $query->getModel()->getTable();
            $query->leftJoin('MaterialProgramaPL as mpl_buscar_traslado', $tabla.'.idMaterialPL', '=', 'mpl_buscar_traslado.idMaterialPL')
                ->where(function ($consulta) use ($termino, $tabla) {
                    $consulta->where($tabla.'.factura', 'like', $termino)
                        ->orWhere($tabla.'.guia', 'like', $termino)
                        ->orWhere($tabla.'.facturaGuia', 'like', $termino)
                        ->orWhere('mpl_buscar_traslado.CodigoPL', 'like', $termino);
                })
                ->select($tabla.'.*');
        }
    }

    private function procesarArchivoPdf(?UploadedFile $archivo, string $tipo, ?string $facturaGuia): array
    {
        if (! $archivo) {
            return ['id' => null, 'webUrl' => null, 'name' => null];
        }

        try {
            return $this->procesarArchivoPdfStrict($archivo, $tipo, $facturaGuia);
        } catch (\Throwable $exception) {
            // En campaña el movimiento no debe fallar por OneDrive/Power Automate.
            Log::warning('No se pudo subir el PDF; el movimiento se guardó sin archivo.', [
                'tipo' => $tipo,
                'referencia' => $facturaGuia,
                'error' => $exception->getMessage(),
            ]);

            return ['id' => null, 'webUrl' => null, 'name' => null];
        }
    }

    /**
     * @return array{id: string, webUrl: string, name: string}
     */
    private function procesarArchivoPdfStrict(UploadedFile $archivo, string $tipo, ?string $facturaGuia): array
    {
        $result = $this->powerAutomateService->uploadMovimientoPdf(
            $archivo,
            $tipo,
            $this->nullableString($facturaGuia)
        );

        return [
            'id' => $result['id'],
            'webUrl' => $result['webUrl'],
            'name' => $result['name'],
        ];
    }

    /**
     * Bloqueo corto por material+almacén para evitar stock negativo con usuarios concurrentes.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function conBloqueoStock(int $idAlmacen, int $idMaterialPL, callable $callback): mixed
    {
        return $this->conLockMysql(sprintf('stock_%d_%d', $idAlmacen, $idMaterialPL), $callback);
    }

    private function conBloqueoStockAlmacen(int $idAlmacen, callable $callback): mixed
    {
        return $this->conLockMysql(sprintf('stock_almacen_%d', $idAlmacen), $callback);
    }

    private function conLockMysql(string $llave, callable $callback): mixed
    {
        $resultado = DB::selectOne('SELECT GET_LOCK(?, 8) AS locked', [$llave]);

        if (! $resultado || (int) $resultado->locked !== 1) {
            throw new InvalidArgumentException(
                'Otro usuario está usando este almacén. Intente de nuevo en unos segundos.'
            );
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$llave]);
        }
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function referenciaDocumento(array $datos): ?string
    {
        $factura = $this->nullableString($datos['factura'] ?? null);
        $guia = $this->nullableString($datos['guia'] ?? null);

        if ($factura && $guia) {
            return $factura.'_'.$guia;
        }

        return $factura ?? $guia ?? $this->nullableString($datos['factura_guia'] ?? null);
    }
}

