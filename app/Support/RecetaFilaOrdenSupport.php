<?php

namespace App\Support;

use App\Models\Estado;
use App\Models\Programas\ProgramaFila;
use Illuminate\Support\Facades\DB;

final class RecetaFilaOrdenSupport
{
    /** Orden típico en Excel tras la caja (y caja alternativa). */
    private const ORDEN_FAMILIA = [
        'others' => 10,
        'bags' => 20,
        'pallet' => 30,
        'pallets' => 30,
        'carton' => 40,
        'plastic liner' => 50,
        'sticker' => 60,
        'clamshell sticker' => 61,
        'clamshell belt' => 62,
        'clamshell' => 65,
        'so2 pad' => 70,
        'carton cap' => 80,
        'temperature recorder' => 90,
        'parihuelas' => 95,
        'tapas' => 96,
    ];

    public static function claveGrupoPresentacion(ProgramaFila $fila): string
    {
        return implode('|', [
            (int) ($fila->idProgramaGeneral ?? 0),
            (int) ($fila->idMercado ?? 0),
            (int) ($fila->idCliente ?? 0),
            (int) ($fila->idPrograma ?? 0),
            (int) ($fila->idVariedad ?? 0),
            (int) ($fila->idPresentacion ?? 0),
        ]);
    }

    public static function etiquetaGrupo(ProgramaFila $fila): string
    {
        return mb_strtolower(implode('|', [
            $fila->programaGeneral?->nombre ?? '',
            $fila->mercado?->nomMercado ?? '',
            $fila->cliente?->nomCliente ?? '',
            $fila->programa?->nomPrograma ?? '',
            $fila->variedad?->nomVariedad ?? '',
            $fila->presentacion?->nomPresentacion ?? '',
        ]));
    }

    public static function comparar(ProgramaFila $a, ProgramaFila $b): int
    {
        $pa = self::prioridadMaterial($a);
        $pb = self::prioridadMaterial($b);
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        $ca = MaterialProgramaLookup::normalizarCodigo($a->material?->CodigoPL ?? '');
        $cb = MaterialProgramaLookup::normalizarCodigo($b->material?->CodigoPL ?? '');
        if ($ca !== $cb) {
            return strcmp($ca, $cb);
        }

        return ((int) $a->idProgramaFila) <=> ((int) $b->idProgramaFila);
    }

    public static function prioridadMaterial(ProgramaFila $fila): int
    {
        return self::prioridadDesdeNombres(
            $fila->familia?->nomFamilia,
            $fila->uso?->nomUso
        );
    }

    public static function prioridadDesdeNombres(?string $familia, ?string $uso): int
    {
        if (FamiliaMaterialSupport::esCajas($familia)) {
            $claveUso = UsoObservacionSupport::clave($uso);
            if (str_starts_with($claveUso, 'alternativo')) {
                if (preg_match('/alternativo(?:\s*(\d+))?/', $claveUso, $matches)) {
                    $n = ! empty($matches[1]) ? (int) $matches[1] : 1;

                    return 10 + min($n, 89);
                }

                return 11;
            }

            return 0;
        }

        $norm = FamiliaMaterialSupport::normalizar($familia);
        $base = self::ORDEN_FAMILIA[$norm] ?? 500;

        $claveUso = UsoObservacionSupport::clave($uso);
        if (str_starts_with($claveUso, 'alternativo')) {
            $base += 5;
        }

        return $base;
    }

    public static function renumerarFilasActivas(): int
    {
        $coleccion = ProgramaFila::query()
            ->activo()
            ->with([
                'mercado', 'cliente', 'programa', 'programaGeneral', 'variedad',
                'presentacion', 'material', 'familia', 'uso',
            ])
            ->orderBy('idProgramaFila')
            ->get();

        if ($coleccion->isEmpty()) {
            return 0;
        }

        $ordenadas = $coleccion
            ->groupBy(fn (ProgramaFila $f) => self::claveGrupoPresentacion($f))
            ->sortBy(fn ($grupo) => self::etiquetaGrupo($grupo->first()))
            ->flatMap(function ($grupo) {
                return $grupo->sort(self::comparar(...))->values();
            })
            ->values();

        $asignaciones = [];
        $nro = 0;
        foreach ($ordenadas as $fila) {
            $nro++;
            if ((int) $fila->nroFila !== $nro) {
                $asignaciones[(int) $fila->idProgramaFila] = $nro;
            }
        }

        if ($asignaciones === []) {
            return 0;
        }

        DB::transaction(function () use ($asignaciones) {
            DB::statement('UPDATE ProgramaFila SET nroFila = 1000000 + idProgramaFila');
            foreach ($asignaciones as $id => $nuevo) {
                ProgramaFila::query()->whereKey($id)->update(['nroFila' => $nuevo]);
            }
            ProgramaFila::query()
                ->where('Estado', '!=', Estado::ACTIVO)
                ->update(['nroFila' => DB::raw('900000 + idProgramaFila')]);
        });

        return count($asignaciones);
    }
}
