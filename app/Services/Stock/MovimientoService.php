<?php

namespace App\Services\Stock;

use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\TipoConsumo;
use App\Models\Stock\Almacen;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Traslado;
use App\Services\Integrations\PowerAutomateService;
use App\Support\GridPagination;
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
    public function __construct(
        private readonly PowerAutomateService $powerAutomateService
    ) {}
    public function catalogos(bool $conMateriales = true): array
    {
        $catalogos = [
            'almacenes' => Cache::remember('catalogo.almacenes', 60, function () {
                return Almacen::query()->activo()->orderBy('orden')->get();
            }),
            'tiposConsumo' => Cache::remember('catalogo.tipos_consumo', 60, function () {
                return TipoConsumo::query()
                    ->activo()
                    ->orderBy('nomTipoConsumo')
                    ->get(['idTipoConsumo', 'nomTipoConsumo']);
            }),
            'programas' => Cache::remember('catalogo.programas_consumo', 60, function () {
                return app(ConsumoRecetaService::class)->programasDisponibles();
            }),
            'materiales' => collect(),
        ];

        if ($conMateriales) {
            $catalogos['materiales'] = Cache::remember('catalogo.materiales_pl', 60, function () {
                return MaterialProgramaPL::query()
                    ->activo()
                    ->orderBy('CodigoPL')
                    ->get(['idMaterialPL', 'CodigoPL', 'nomMaterial']);
            });
        }

        return $catalogos;
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
        $perPage = GridPagination::PER_PAGE;
        $page = max(1, (int) $request->query('page', 1));
        $columnasLote = [
            'idAlmacen',
            'fecha',
            'idUsuario',
            'fechaRegistro',
            'factura',
            'guia',
            'observacion',
            'archivoOneDriveId',
        ];

        $base = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_INGRESO)
            ->whereNull('idTraslado');

        $this->aplicarFiltrosMovimiento($base, $filtros);

        $total = (int) DB::query()
            ->fromSub(
                (clone $base)->select($columnasLote)->groupBy($columnasLote),
                'lotes_ingreso'
            )
            ->count();

        $lotes = (clone $base)
            ->select($columnasLote)
            ->selectRaw('MAX(idMovimiento) AS id_principal')
            ->selectRaw('SUM(cantidad) AS cantidad_total')
            ->selectRaw('COUNT(*) AS total_lineas')
            ->groupBy($columnasLote)
            ->orderByDesc('fecha')
            ->orderByDesc(DB::raw('MAX(idMovimiento)'))
            ->forPage($page, $perPage)
            ->get();

        $principales = MovimientoStock::query()
            ->with(['materialPl', 'almacen'])
            ->whereIn('idMovimiento', $lotes->pluck('id_principal')->filter()->all())
            ->get()
            ->keyBy('idMovimiento');

        $items = $lotes->map(function ($lote) use ($principales) {
            $principal = $principales->get((int) $lote->id_principal);
            $totalLineas = (int) $lote->total_lineas;
            $nombreMaterial = $principal?->materialPl?->nomMaterial ?: '—';

            return (object) [
                'idMovimiento' => (int) $lote->id_principal,
                'almacen' => $principal?->almacen,
                'fecha' => $principal?->fecha,
                'factura' => $principal?->factura,
                'guia' => $principal?->guia ?: $principal?->facturaGuia,
                'archivoOneDriveUrl' => $principal?->archivoOneDriveUrl,
                'observacion' => $principal?->observacion,
                'material' => $totalLineas > 1 ? $nombreMaterial.' y varios' : $nombreMaterial,
                'codigo_pl' => $totalLineas > 1 ? 'Varios' : ($principal?->materialPl?->CodigoPL ?: '—'),
                'cantidad' => (float) $lote->cantidad_total,
                'es_lote' => $totalLineas > 1,
                'total_lineas' => $totalLineas,
            ];
        })->values();

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

    public function listarEgresosPaginados(array $filtros, Request $request): LengthAwarePaginator
    {
        $query = MovimientoStock::query()
            ->with(['materialPl', 'almacen', 'tipoConsumo', 'programa', 'presentacion'])
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->whereNull('idTraslado');

        $this->aplicarFiltrosMovimiento($query, $filtros);

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('idMovimiento')
            ->paginate(GridPagination::PER_PAGE)
            ->withQueryString();
    }

    public function listarTrasladosPaginados(array $filtros, Request $request): LengthAwarePaginator
    {
        $query = Traslado::query()
            ->with(['materialPl', 'almacenOrigen', 'almacenDestino'])
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
        return $this->listarPorTipo(MovimientoStock::TIPO_EGRESO, $filtros);
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
        $stock = MovimientoStock::query()
            ->where('estado', 1)
            ->where('idMaterialPL', $idMaterialPL)
            ->where('idAlmacen', $idAlmacen)
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN tipo = 'ingreso' THEN cantidad
                        WHEN tipo = 'egreso' THEN -cantidad
                        ELSE 0
                    END
                ), 0) AS stock
            ")
            ->value('stock');

        return (float) $stock;
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        return MaterialProgramaPL::query()
            ->activo()
            ->where('CodigoPL', $codigo)
            ->orderByDesc('idMaterialPL')
            ->first();
    }

    public function crearIngreso(array $datos, ?int $idUsuario, ?UploadedFile $archivo = null): int
    {
        $lineas = $datos['lineas'] ?? [];
        if ($lineas === []) {
            throw new InvalidArgumentException('Debe agregar al menos un material al ingreso.');
        }

        $referenciaPdf = $this->referenciaDocumento($datos);
        $archivoData = $this->procesarArchivoPdf($archivo, MovimientoStock::TIPO_INGRESO, $referenciaPdf);

        return DB::transaction(function () use ($datos, $idUsuario, $lineas, $archivoData) {
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
            $datos['pallets_fuera'] ?? null
        );

        if ($lineas === []) {
            throw new InvalidArgumentException('No hay cantidades para retirar.');
        }

        $referenciaPdf = $this->referenciaDocumento($datos);
        $archivoData = $this->procesarArchivoPdf($archivo, MovimientoStock::TIPO_EGRESO, $referenciaPdf);

        return DB::transaction(function () use ($datos, $idUsuario, $idAlmacen, $lineas, $archivoData) {
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

                    MovimientoStock::query()->create([
                        'tipo' => MovimientoStock::TIPO_EGRESO,
                        'idAlmacen' => $idAlmacen,
                        'idMaterialPL' => $idMaterialPL,
                        'cantidad' => $cantidad,
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

                return $creados;
            });
        });
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

        return DB::transaction(function () use ($datos, $idUsuario, $idMaterialPL, $idOrigen, $idDestino, $cantidad, $archivoData) {
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

            return;
        }

        $movimiento->update(['estado' => 0]);
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
    }

    public function actualizarEgreso(int $idMovimiento, array $datos, ?UploadedFile $archivo = null): MovimientoStock
    {
        $movimiento = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        $idMaterialPL = (int) $datos['id_material_pl'];
        $idAlmacen = (int) $datos['id_almacen'];
        $cantidad = (float) $datos['cantidad'];
        $archivoData = $this->resolverArchivoPdf($archivo, MovimientoStock::TIPO_EGRESO, $datos, $movimiento);

        return DB::transaction(function () use ($movimiento, $datos, $idMaterialPL, $idAlmacen, $cantidad, $archivoData) {
            return $this->conBloqueoStock($idAlmacen, $idMaterialPL, function () use ($movimiento, $datos, $idMaterialPL, $idAlmacen, $cantidad, $archivoData) {
                $disponible = $this->stockDisponible($idMaterialPL, $idAlmacen);
                if ($movimiento->idMaterialPL === $idMaterialPL && $movimiento->idAlmacen === $idAlmacen) {
                    $disponible += (float) $movimiento->cantidad;
                }

                if ($cantidad > $disponible) {
                    throw new InvalidArgumentException(
                        'Stock insuficiente. Disponible: '.number_format($disponible, 2, '.', '')
                    );
                }

                $movimiento->update([
                    'idAlmacen' => $idAlmacen,
                    'idMaterialPL' => $idMaterialPL,
                    'cantidad' => $cantidad,
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
                ]);

                return $movimiento->fresh(['materialPl', 'almacen', 'tipoConsumo', 'programa', 'presentacion']);
            });
        });
    }

    public function actualizarTraslado(int $idTraslado, array $datos, ?UploadedFile $archivo = null): Traslado
    {
        return DB::transaction(function () use ($idTraslado, $datos, $archivo) {
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

        return DB::transaction(function () use ($principal, $datos, $archivo) {
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
    public function detalleMovimiento(int $idMovimiento): array
    {
        $movimiento = MovimientoStock::query()
            ->with(['almacen', 'materialPl', 'tipoConsumo', 'programa', 'presentacion', 'usuario.persona'])
            ->where('estado', 1)
            ->whereNull('idTraslado')
            ->findOrFail($idMovimiento);

        $lineas = $movimiento->tipo === MovimientoStock::TIPO_INGRESO
            ? $this->lineasIngresoRelacionadas($movimiento)
            : collect([$movimiento]);

        return $this->formatearDetalleMovimiento($movimiento, $lineas);
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
            'guia' => $this->guiaDocumento($traslado->guia, $traslado->factura, $traslado->facturaGuia),
            'factura_guia' => null,
            'observacion' => $traslado->observacion,
            'pdf_url' => $traslado->archivoOneDriveUrl,
            'pdf_nombre' => $traslado->archivoNombre,
            'lineas' => [[
                'codigo_pl' => $traslado->materialPl?->CodigoPL,
                'material' => $traslado->materialPl?->nomMaterial,
                'cantidad' => $this->formatearCantidad($traslado->cantidad),
                'almacen' => ($traslado->almacenOrigen?->codigo ?? '—').' → '.($traslado->almacenDestino?->codigo ?? '—'),
            ]],
            'movimientos_vinculados' => $traslado->movimientos
                ->sortBy('tipo')
                ->values()
                ->map(fn (MovimientoStock $mov) => [
                    'tipo' => $mov->tipo === MovimientoStock::TIPO_INGRESO ? 'Ingreso' : 'Consumo',
                    'almacen' => $mov->almacen?->codigo,
                    'cantidad' => $this->formatearCantidad($mov->cantidad),
                ])
                ->all(),
            'datos_edicion' => [
                'id_almacen_origen' => $traslado->idAlmacenOrigen,
                'id_almacen_destino' => $traslado->idAlmacenDestino,
                'id_material_pl' => $traslado->idMaterialPL,
                'cantidad' => (float) $traslado->cantidad,
                'fecha' => $traslado->fecha?->format('Y-m-d'),
                'factura' => $traslado->factura,
                'guia' => $traslado->guia,
                'observacion' => $traslado->observacion,
            ],
        ];
    }

    /**
     * @return Collection<int, MovimientoStock>
     */
    private function lineasIngresoRelacionadas(MovimientoStock $movimiento): Collection
    {
        return MovimientoStock::query()
            ->with(['materialPl', 'almacen'])
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_INGRESO)
            ->whereNull('idTraslado')
            ->where('idAlmacen', $movimiento->idAlmacen)
            ->whereDate('fecha', $movimiento->fecha)
            ->where('idUsuario', $movimiento->idUsuario)
            ->where('fechaRegistro', $movimiento->fechaRegistro)
            ->when(
                $movimiento->factura,
                fn ($query) => $query->where('factura', $movimiento->factura),
                fn ($query) => $query->whereNull('factura')
            )
            ->when(
                $movimiento->guia,
                fn ($query) => $query->where('guia', $movimiento->guia),
                fn ($query) => $query->whereNull('guia')
            )
            ->when(
                $movimiento->observacion,
                fn ($query) => $query->where('observacion', $movimiento->observacion),
                fn ($query) => $query->whereNull('observacion')
            )
            ->when(
                $movimiento->archivoOneDriveId,
                fn ($query) => $query->where('archivoOneDriveId', $movimiento->archivoOneDriveId),
                fn ($query) => $query->whereNull('archivoOneDriveId')
            )
            ->orderBy('idMovimiento')
            ->get();
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

    /**
     * @param  Collection<int, MovimientoStock>  $lineas
     * @return array<string, mixed>
     */
    private function formatearDetalleMovimiento(MovimientoStock $movimiento, Collection $lineas): array
    {
        $usuario = $movimiento->usuario;
        $persona = $usuario?->persona;
        $esIngreso = $movimiento->tipo === MovimientoStock::TIPO_INGRESO;

        return [
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
            'guia' => $this->guiaDocumento($movimiento->guia, $movimiento->factura, $movimiento->facturaGuia),
            'factura_guia' => null,
            'observacion' => $movimiento->observacion,
            'pdf_url' => $movimiento->archivoOneDriveUrl,
            'pdf_nombre' => $movimiento->archivoNombre,
            'lineas' => $lineas->map(fn (MovimientoStock $linea) => [
                'id_movimiento' => $linea->idMovimiento,
                'codigo_pl' => $linea->materialPl?->CodigoPL,
                'material' => $linea->materialPl?->nomMaterial,
                'cantidad' => $this->formatearCantidad($linea->cantidad),
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
                ])->values()->all(),
            ] : [
                'id_almacen' => $movimiento->idAlmacen,
                'id_programa' => $movimiento->idPrograma,
                'id_presentacion' => $movimiento->idPresentacion,
                'id_material_pl' => $movimiento->idMaterialPL,
                'id_tipo_consumo' => $movimiento->idTipoConsumo,
                'motivo_nombre' => $movimiento->tipoConsumo?->nomTipoConsumo,
                'cantidad' => (float) $movimiento->cantidad,
                'fecha' => $movimiento->fecha?->format('Y-m-d'),
                'factura' => $movimiento->factura,
                'guia' => $movimiento->guia,
                'observacion' => $movimiento->observacion,
            ],
        ];
    }

    private function formatearCantidad(float|string|null $cantidad): string
    {
        return number_format((float) $cantidad, 2, '.', '');
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
            $termino = '%'.$filtros['buscar'].'%';
            $query->where(function ($consulta) use ($termino) {
                $consulta->where('factura', 'like', $termino)
                    ->orWhere('guia', 'like', $termino)
                    ->orWhere('facturaGuia', 'like', $termino)
                    ->orWhereHas('materialPl', fn ($material) => $material->where('CodigoPL', 'like', $termino));
            });
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
            $termino = '%'.$filtros['buscar'].'%';
            $query->where(function ($consulta) use ($termino) {
                $consulta->where('factura', 'like', $termino)
                    ->orWhere('guia', 'like', $termino)
                    ->orWhere('facturaGuia', 'like', $termino)
                    ->orWhereHas('materialPl', fn ($material) => $material->where('CodigoPL', 'like', $termino));
            });
        }
    }

    private function guiaDocumento(?string $guia, ?string $factura, ?string $facturaGuia): ?string
    {
        if ($guia) {
            return $guia;
        }

        if ($factura || ! $facturaGuia) {
            return null;
        }

        return $facturaGuia;
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
