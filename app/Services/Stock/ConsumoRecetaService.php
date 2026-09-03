<?php

namespace App\Services\Stock;

use App\Models\Estado;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\Presentacion;
use App\Models\Stock\MovimientoStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConsumoRecetaService
{
    /**
     * @return Collection<int, ProgramaCatalogo>
     */
    public function programasDisponibles(): Collection
    {
        return Cache::remember('catalogo.programas_consumo', 60, function () {
            return ProgramaCatalogo::query()
                ->activo()
                ->whereIn('idPrograma', ProgramaFila::query()
                    ->select('idPrograma')
                    ->where('Estado', Estado::ACTIVO)
                    ->distinct())
                ->orderBy('nomPrograma')
                ->get(['idPrograma', 'nomPrograma']);
        });
    }

    /**
     * @return Collection<int, Presentacion>
     */
    public function presentacionesDePrograma(int $idPrograma): Collection
    {
        if ($idPrograma <= 0) {
            return collect();
        }

        return Presentacion::query()
            ->activo()
            ->whereIn('idPresentacion', ProgramaFila::query()
                ->select('idPresentacion')
                ->where('idPrograma', $idPrograma)
                ->where('Estado', Estado::ACTIVO)
                ->distinct())
            ->orderBy('nomPresentacion')
            ->get(['idPresentacion', 'nomPresentacion']);
    }

    /**
     * @return list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   cantidad_por_caja: float,
     *   id_familia: int|null,
     *   nom_familia: string|null,
     *   nom_uso: string|null,
     *   es_caja_editable: bool,
     *   usa_pallets: bool,
     *   ya_sacado: float,
     *   faltante: float,
     *   stock: float|null
     * }>
     */
    public function materialesDePresentacion(
        int $idPrograma,
        int $idPresentacion,
        ?int $excluirIdMovimiento = null,
        ?int $idAlmacen = null
    ): array {
        if ($idPrograma <= 0 || $idPresentacion <= 0) {
            return [];
        }

        $filas = ProgramaFila::query()
            ->with(['material', 'cantidadCaja', 'familia', 'uso'])
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('Estado', Estado::ACTIVO)
            ->whereHas('material', fn ($q) => $q->where('Estado', Estado::ACTIVO))
            ->get();

        $idsMaterial = $filas->pluck('idMaterialPL')->unique()->values()->all();
        $sacados = $this->cantidadesSacadasPorMaterial(
            $idPrograma,
            $idPresentacion,
            $idsMaterial,
            $excluirIdMovimiento
        );

        $stocks = $this->stocksPorMaterial($idsMaterial, $idAlmacen);

        return $filas
            ->groupBy('idMaterialPL')
            ->map(function (Collection $grupo) use ($sacados, $stocks) {
                $fila = $grupo->first();
                $material = $fila->material;
                $familia = $fila->familia?->nomFamilia;
                $uso = $fila->uso?->nomUso;
                $necesario = (float) ($fila->cantidadCaja?->cantidad ?? 0);
                $idMaterial = (int) $fila->idMaterialPL;
                $yaSacado = (float) ($sacados[$idMaterial] ?? 0);

                return [
                    'id_material_pl' => $idMaterial,
                    'codigo_pl' => $material?->CodigoPL,
                    'nom_material' => $material?->nomMaterial,
                    'cantidad_por_caja' => $necesario,
                    'id_familia' => $fila->idFamilia ? (int) $fila->idFamilia : null,
                    'nom_familia' => $familia,
                    'nom_uso' => $uso,
                    'es_caja_editable' => $this->esCajaEditable($familia, $uso),
                    'usa_pallets' => $this->familiaUsaPallets($familia),
                    'ya_sacado' => $yaSacado,
                    'faltante' => $this->faltantePorSacar($necesario, $yaSacado),
                    'stock' => $stocks[$idMaterial] ?? null,
                ];
            })
            ->sortBy(fn (array $item) => mb_strtolower((string) ($item['nom_material'] ?? '')))
            ->values()
            ->all();
    }

    public function cantidadPorCaja(int $idPrograma, int $idPresentacion, int $idMaterialPL): float
    {
        $fila = ProgramaFila::query()
            ->with('cantidadCaja')
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('idMaterialPL', $idMaterialPL)
            ->where('Estado', Estado::ACTIVO)
            ->first();

        return (float) ($fila?->cantidadCaja?->cantidad ?? 0);
    }

    /**
     * @param  list<array{id_material_pl?: mixed, cantidad?: mixed}>  $lineasInput
     * @return list<array{id_material_pl: int, cantidad: float, nom_material: string|null}>
     */
    public function resolverLineasConsumo(
        int $idPrograma,
        int $idPresentacion,
        array $lineasInput = [],
        mixed $palletsFuera = null
    ): array {
        $receta = $this->materialesDePresentacion($idPrograma, $idPresentacion);
        $inputPorId = collect($lineasInput)->keyBy(fn ($linea) => (int) ($linea['id_material_pl'] ?? 0));
        $factorCajas = $this->factorCajasDesdeLineas($receta, $inputPorId);
        $factorPallets = $this->factorMultiplicador($palletsFuera);

        $lineas = [];
        foreach ($receta as $item) {
            $base = (float) $item['cantidad_por_caja'];
            $factor = ! empty($item['usa_pallets']) ? $factorPallets : $factorCajas;
            $cantidad = round($base * $factor, 2);

            if ($cantidad <= 0) {
                continue;
            }

            $lineas[] = [
                'id_material_pl' => (int) $item['id_material_pl'],
                'cantidad' => $cantidad,
                'nom_material' => $item['nom_material'] ?? null,
            ];
        }

        return $lineas;
    }

    public function familiaUsaPallets(?string $nomFamilia): bool
    {
        $nombre = mb_strtolower(trim((string) $nomFamilia));

        return in_array($nombre, ['tapas', 'parihuelas', 'termografos'], true);
    }

    public function esCajaEditable(?string $nomFamilia, ?string $nomUso): bool
    {
        $familia = mb_strtolower(trim((string) $nomFamilia));
        $uso = $this->normalizarUso($nomUso);

        return $familia === 'cajas' && in_array($uso, ['comun', 'alternativo'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $receta
     * @param  Collection<int, mixed>  $inputPorId
     */
    private function factorCajasDesdeLineas(array $receta, Collection $inputPorId): float
    {
        $factorComun = 0.0;
        $factorAlternativo = 0.0;

        foreach ($receta as $item) {
            if (empty($item['es_caja_editable'])) {
                continue;
            }

            $input = $inputPorId->get((int) $item['id_material_pl'], []);
            $override = trim((string) ($input['cantidad'] ?? ''));
            if ($override === '' || ! is_numeric($override) || (float) $override <= 0) {
                continue;
            }

            $numero = (float) $override;
            $uso = $this->normalizarUso($item['nom_uso'] ?? null);
            if ($uso === 'comun' && $factorComun <= 0) {
                $factorComun = $numero;
            }
            if ($uso === 'alternativo' && $factorAlternativo <= 0) {
                $factorAlternativo = $numero;
            }
        }

        if ($factorComun > 0) {
            return $factorComun;
        }

        if ($factorAlternativo > 0) {
            return $factorAlternativo;
        }

        return 1.0;
    }

    private function normalizarUso(?string $nomUso): string
    {
        $uso = mb_strtolower(trim((string) $nomUso));
        $uso = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $uso);

        if (str_starts_with($uso, 'alternativ')) {
            return 'alternativo';
        }

        if (str_starts_with($uso, 'comun')) {
            return 'comun';
        }

        return $uso;
    }

    private function factorMultiplicador(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if ($texto === '' || ! is_numeric($texto)) {
            return 1.0;
        }

        $numero = (float) $texto;

        return $numero > 0 ? $numero : 1.0;
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array<int, float>
     */
    private function stocksPorMaterial(array $idsMaterial, ?int $idAlmacen): array
    {
        if ($idAlmacen === null || $idAlmacen <= 0 || $idsMaterial === []) {
            return [];
        }

        $filas = MovimientoStock::query()
            ->where('estado', 1)
            ->where('idAlmacen', $idAlmacen)
            ->whereIn('idMaterialPL', $idsMaterial)
            ->selectRaw("
                idMaterialPL,
                COALESCE(SUM(
                    CASE
                        WHEN tipo = 'ingreso' THEN cantidad
                        WHEN tipo = 'egreso' THEN -cantidad
                        ELSE 0
                    END
                ), 0) AS stock
            ")
            ->groupBy('idMaterialPL')
            ->pluck('stock', 'idMaterialPL');

        $stocks = [];
        foreach ($idsMaterial as $idMaterial) {
            $stocks[(int) $idMaterial] = (float) ($filas[$idMaterial] ?? 0);
        }

        return $stocks;
    }

    public function materialPerteneceAPresentacion(int $idPrograma, int $idPresentacion, int $idMaterialPL): bool
    {
        return ProgramaFila::query()
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->where('idMaterialPL', $idMaterialPL)
            ->where('Estado', Estado::ACTIVO)
            ->exists();
    }

    public function cantidadSacada(
        int $idPrograma,
        int $idPresentacion,
        int $idMaterialPL,
        ?int $excluirIdMovimiento = null
    ): float {
        $mapa = $this->cantidadesSacadasPorMaterial(
            $idPrograma,
            $idPresentacion,
            [$idMaterialPL],
            $excluirIdMovimiento
        );

        return (float) ($mapa[$idMaterialPL] ?? 0);
    }

    /**
     * @param  list<int>  $idsMaterial
     * @return array<int, float>
     */
    public function cantidadesSacadasPorMaterial(
        int $idPrograma,
        int $idPresentacion,
        array $idsMaterial,
        ?int $excluirIdMovimiento = null
    ): array {
        if ($idsMaterial === []) {
            return [];
        }

        $query = MovimientoStock::query()
            ->where('estado', 1)
            ->where('tipo', MovimientoStock::TIPO_EGRESO)
            ->whereNull('idTraslado')
            ->where('idPrograma', $idPrograma)
            ->where('idPresentacion', $idPresentacion)
            ->whereIn('idMaterialPL', $idsMaterial)
            ->groupBy('idMaterialPL')
            ->selectRaw('idMaterialPL, COALESCE(SUM(cantidad), 0) AS total');

        if ($excluirIdMovimiento) {
            $query->where('idMovimiento', '!=', $excluirIdMovimiento);
        }

        return $query
            ->pluck('total', 'idMaterialPL')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    public function faltantePorSacar(float $necesario, float $yaSacado): float
    {
        if ($necesario <= 0) {
            return 0.0;
        }

        $resto = fmod($yaSacado, $necesario);
        if ($resto < 0.00001) {
            return $yaSacado < 0.00001 ? round($necesario, 2) : 0.0;
        }

        return round($necesario - $resto, 2);
    }

    /**
     * @return array{
     *   stock: float,
     *   cantidad_por_caja: float,
     *   ya_sacado: float,
     *   faltante: float
     * }
     */
    public function hintConsumo(
        int $idPrograma,
        int $idPresentacion,
        int $idMaterialPL,
        int $idAlmacen,
        ?int $excluirIdMovimiento = null
    ): array {
        $necesario = $this->cantidadPorCaja($idPrograma, $idPresentacion, $idMaterialPL);
        $yaSacado = $this->cantidadSacada($idPrograma, $idPresentacion, $idMaterialPL, $excluirIdMovimiento);
        $stock = app(MovimientoService::class)->stockDisponible($idMaterialPL, $idAlmacen);

        return [
            'stock' => $stock,
            'cantidad_por_caja' => $necesario,
            'ya_sacado' => $yaSacado,
            'faltante' => $this->faltantePorSacar($necesario, $yaSacado),
        ];
    }
}
