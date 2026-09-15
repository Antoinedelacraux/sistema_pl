<?php

namespace App\Services\PlanProduccion;

use App\Models\Usuario;
use App\Support\DocumentoExportSupport;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanProduccionExportService
{
    public function __construct(
        private readonly PlanProduccionGridService $gridService,
        private readonly PlanProduccionCantidadService $cantidadService,
    ) {}

    public function exportarExcel(string $seccion, ?Usuario $usuario = null, bool $desglose = false): StreamedResponse
    {
        [$config, $encabezados, $filas] = $this->preparar($seccion, $desglose);

        return DocumentoExportSupport::exportarExcel(
            $encabezados,
            $filas,
            $usuario,
            $config['tituloHoja'],
            $config['prefijo'],
            $config['nombreTabla'],
            $config['columnasTexto'] ?? [],
            $config['filasNegrita'] ?? [],
        );
    }

    public function exportarPdf(string $seccion, ?Usuario $usuario = null, bool $desglose = false): Response
    {
        [$config, $encabezados, $filas] = $this->preparar($seccion, $desglose);

        return DocumentoExportSupport::exportarPdf('exports.pdf-grid', [
            'tituloDocumento' => $config['tituloDocumento'],
            'titulo' => $config['tituloPdf'],
            'filas' => $filas,
            'encabezados' => $encabezados,
            'total' => count($filas),
            'generado' => now()->format('d/m/Y H:i'),
            'exportadoPor' => DocumentoExportSupport::nombreExportador($usuario),
        ], $config['prefijo']);
    }

    /**
     * @return array{
     *     0: array{tituloDocumento: string, tituloPdf: string, prefijo: string, tituloHoja: string, nombreTabla: string},
     *     1: list<string>,
     *     2: list<array<int, string|int|float|null>>
     * }
     */
    private function preparar(string $seccion, bool $desglose = false): array
    {
        return match ($seccion) {
            'ventas' => $this->prepararVentas($desglose),
            'consumo' => $this->prepararConsumo(),
            default => abort(404),
        };
    }

    /**
     * @return array{
     *     0: array{tituloDocumento: string, tituloPdf: string, prefijo: string, tituloHoja: string, nombreTabla: string},
     *     1: list<string>,
     *     2: list<array<int, string|int|float|null>>
     * }
     */
    private function prepararVentas(bool $desglose = false): array
    {
        $datos = $this->gridService->datos();
        $encabezados = ['Programa General', 'Calificación'];

        foreach ($datos['semanas'] as $sem) {
            $encabezados[] = $sem->codigoSemana;
        }
        $encabezados[] = 'Total';

        $filas = [];
        $filasNegrita = [];

        foreach ($datos['filas'] as $fila) {
            $filasNegrita[] = count($filas);
            $filas[] = $this->filaVentasPrograma($fila, $datos['semanas']);

            if ($desglose) {
                $materiales = $this->gridService->materialesDetalle((int) $fila['idProgramaGeneral']);
                foreach ($materiales as $material) {
                    $filas[] = $this->filaVentasMaterial($fila, $material, $datos['semanas']);
                }
            }
        }

        return [
            [
                'tituloDocumento' => $desglose ? 'Plan de ventas (desglose)' : 'Plan de ventas',
                'tituloPdf' => $desglose
                    ? 'Plan de Producción · Plan de ventas · desglose de materiales'
                    : 'Plan de Producción · Plan de ventas',
                'prefijo' => $desglose ? 'plan-ventas-desglose' : 'plan-ventas',
                'tituloHoja' => $desglose ? 'Plan ventas desglose' : 'Plan ventas',
                'nombreTabla' => $desglose ? 'TablaPlanVentasDesglose' : 'TablaPlanVentas',
                'columnasTexto' => [],
                'filasNegrita' => $filasNegrita,
            ],
            $encabezados,
            $filas,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  \Illuminate\Support\Collection<int, \App\Models\Programas\SemProduccion>  $semanas
     * @return list<string|int|float|null>
     */
    private function filaVentasPrograma(array $fila, $semanas): array
    {
        $row = [
            (string) ($fila['nombre'] ?? ''),
            (string) ($fila['calificacion'] ?? ''),
        ];
        $total = 0.0;

        foreach ($semanas as $sem) {
            $valor = (float) ($fila['sem_'.$sem->idSemProduccion] ?? 0);
            $total += $valor;
            $row[] = $valor == 0.0 ? '' : number_format($valor, 0, '.', '');
        }

        $row[] = $total == 0.0 ? '' : number_format($total, 0, '.', '');

        return $row;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $material
     * @param  \Illuminate\Support\Collection<int, \App\Models\Programas\SemProduccion>  $semanas
     * @return list<string|int|float|null>
     */
    private function filaVentasMaterial(array $fila, array $material, $semanas): array
    {
        $etiqueta = sprintf(
            '  %s × %s — %s',
            (string) ($material['codigo_pl'] ?? ''),
            (string) ($material['cantidad_por_caja'] ?? ''),
            (string) ($material['descripcion'] ?? '')
        );

        $row = [$etiqueta, ''];
        $total = 0.0;
        $decimales = (int) ($material['decimales_cantidad'] ?? 2);
        $overrides = $material['overrides'] ?? [];

        foreach ($semanas as $sem) {
            $idSem = (int) $sem->idSemProduccion;
            $cajas = (float) ($fila['sem_'.$idSem] ?? 0);

            if (isset($overrides[(string) $idSem])) {
                $valor = (float) $overrides[(string) $idSem];
            } else {
                $valor = $this->cantidadService->calcularDesdeCajas($cajas, [
                    'cantidad_por_caja' => $material['cantidad_por_caja_num'] ?? $material['cantidad_por_caja'] ?? 0,
                    'nom_familia' => $material['nom_familia'] ?? null,
                ]);
            }

            $total += $valor;
            $row[] = $valor == 0.0 ? '' : number_format($valor, $decimales, '.', '');
        }

        $row[] = $total == 0.0 ? '' : number_format($total, $decimales, '.', '');

        return $row;
    }

    /**
     * @return array{
     *     0: array{tituloDocumento: string, tituloPdf: string, prefijo: string, tituloHoja: string, nombreTabla: string},
     *     1: list<string>,
     *     2: list<array<int, string|int|float|null>>
     * }
     */
    private function prepararConsumo(): array
    {
        $datos = $this->gridService->datos();
        $encabezados = ['Código PL', 'Material PL'];

        foreach ($datos['semanasPlanConsumo'] as $sem) {
            $encabezados[] = (string) $sem->etiqueta;
        }
        $encabezados[] = 'Total';

        $filas = [];
        foreach ($datos['planConsumo'] as $fila) {
            $row = [
                (string) ($fila['codigoPL'] ?? ''),
                (string) ($fila['descripcion'] ?? ''),
            ];
            $total = 0.0;

            foreach ($datos['semanasPlanConsumo'] as $sem) {
                $valor = (float) ($fila['sem_'.$sem->idSemConsumo] ?? 0);
                $total += $valor;
                $row[] = $valor == 0.0 ? '' : number_format($valor, 2, '.', '');
            }

            $row[] = $total == 0.0 ? '' : number_format($total, 2, '.', '');
            $filas[] = $row;
        }

        return [
            [
                'tituloDocumento' => 'Plan de consumo',
                'tituloPdf' => 'Plan de Producción · Plan de consumo',
                'prefijo' => 'plan-consumo',
                'tituloHoja' => 'Plan consumo',
                'nombreTabla' => 'TablaPlanConsumo',
                'columnasTexto' => [0],
            ],
            $encabezados,
            $filas,
        ];
    }
}
