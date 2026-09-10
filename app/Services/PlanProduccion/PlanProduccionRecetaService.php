<?php

namespace App\Services\PlanProduccion;

use App\Models\Estado;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\ProgramaGeneral;
use App\Support\CantidadCajaSupport;
use App\Support\FamiliaMaterialSupport;

class PlanProduccionRecetaService
{
    /**
     * Variantes de receta: nombre exacto o "Nombre (3J)", "Nombre (4J, 5J)", etc.
     *
     * @return list<int>
     */
    public function idsRecetaRelacionados(string $nombrePlan): array
    {
        $nombre = trim($nombrePlan);
        if ($nombre === '') {
            return [];
        }

        $lower = mb_strtolower($nombre);

        return ProgramaGeneral::query()
            ->activo()
            ->where(function ($q) use ($lower) {
                $q->whereRaw('LOWER(TRIM(nombre)) = ?', [$lower])
                    ->orWhereRaw('LOWER(TRIM(nombre)) LIKE ?', [$lower.' (%']);
            })
            ->whereIn('idProgramaGeneral', function ($sub) {
                $sub->select('idProgramaGeneral')
                    ->from('ProgramaFila')
                    ->whereNotNull('idProgramaGeneral')
                    ->where('Estado', Estado::ACTIVO)
                    ->distinct();
            })
            ->orderBy('nombre')
            ->pluck('idProgramaGeneral')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Materiales unificados del plan (suma cantidad/caja si el material se repite).
     *
     * @return list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>
     */
    public function materialesUnificadosParaPlan(string $nombrePlan): array
    {
        $ids = $this->idsRecetaRelacionados($nombrePlan);
        if ($ids === []) {
            return [];
        }

        $porPg = $this->materialesPorProgramaGeneral($ids);
        $merged = [];

        foreach ($porPg as $lista) {
            foreach ($lista as $mat) {
                $idMat = (int) $mat['id_material_pl'];
                if (! isset($merged[$idMat])) {
                    $merged[$idMat] = $mat;

                    continue;
                }

                $nomFamilia = $mat['nom_familia'] ?? $merged[$idMat]['nom_familia'];
                $merged[$idMat]['cantidad_por_caja'] = CantidadCajaSupport::redondear(
                    (float) $merged[$idMat]['cantidad_por_caja'] + (float) $mat['cantidad_por_caja'],
                    $nomFamilia
                );
                $merged[$idMat]['es_caja'] = ($merged[$idMat]['es_caja'] ?? false) || ($mat['es_caja'] ?? false);
            }
        }

        $lista = array_values($merged);
        usort($lista, static fn ($a, $b) => strcmp($a['nom_material'] ?? '', $b['nom_material'] ?? ''));

        return $lista;
    }

    /**
     * @param  list<int>  $idsProgramaGeneral
     * @return array<int, list<array{
     *   id_material_pl: int,
     *   codigo_pl: string|null,
     *   nom_material: string|null,
     *   nom_familia: string|null,
     *   cantidad_por_caja: float,
     *   es_caja: bool,
     *   decimales_cantidad: int
     * }>>
     */
    public function materialesPorProgramaGeneral(array $idsProgramaGeneral): array
    {
        if ($idsProgramaGeneral === []) {
            return [];
        }

        $filas = ProgramaFila::query()
            ->activo()
            ->whereIn('idProgramaGeneral', $idsProgramaGeneral)
            ->with(['material', 'cantidadCaja', 'familia'])
            ->get();

        if ($filas->isEmpty()) {
            $filas = ProgramaFila::query()
                ->activo()
                ->whereHas('programa', function ($q) use ($idsProgramaGeneral) {
                    $q->whereIn('idProgramaGeneral', $idsProgramaGeneral);
                })
                ->with(['material', 'cantidadCaja', 'familia', 'programa'])
                ->get();
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $idPg = (int) ($fila->idProgramaGeneral ?? $fila->programa?->idProgramaGeneral ?? 0);
            if ($idPg <= 0 || ! in_array($idPg, $idsProgramaGeneral, true) || ! $fila->material) {
                continue;
            }

            $idMat = (int) $fila->idMaterialPL;
            $qty = CantidadCajaSupport::normalizarValor(
                $fila->cantidadCaja?->cantidad ?? 0,
                $fila->familia?->nomFamilia
            );

            if (! isset($resultado[$idPg][$idMat])) {
                $nomFamilia = $fila->familia?->nomFamilia;
                $resultado[$idPg][$idMat] = [
                    'id_material_pl' => $idMat,
                    'codigo_pl' => $fila->material->CodigoPL,
                    'nom_material' => $fila->material->nomMaterial,
                    'nom_familia' => $nomFamilia,
                    'cantidad_por_caja' => $qty,
                    'es_caja' => FamiliaMaterialSupport::esCajas($nomFamilia),
                    'decimales_cantidad' => CantidadCajaSupport::decimales($nomFamilia),
                ];
            } else {
                $resultado[$idPg][$idMat]['cantidad_por_caja'] = max(
                    (float) $resultado[$idPg][$idMat]['cantidad_por_caja'],
                    $qty
                );
            }
        }

        $ordenado = [];
        foreach ($resultado as $idPg => $materiales) {
            $lista = array_values($materiales);
            usort($lista, static fn ($a, $b) => strcmp($a['nom_material'] ?? '', $b['nom_material'] ?? ''));
            $ordenado[$idPg] = $lista;
        }

        return $ordenado;
    }
}
