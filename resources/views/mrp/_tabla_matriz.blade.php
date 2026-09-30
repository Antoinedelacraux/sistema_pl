@php
    use App\Support\CantidadCajaSupport;

    $formatearCantidad = static function ($valor, ?string $nomFamilia = null) use (&$layoutPlan): string {
        if (is_string($valor)) {
            return $valor;
        }

        if (is_array($valor)) {
            if (isset($valor['texto'])) {
                return (string) $valor['texto'];
            }

            $valor = $valor['valor'] ?? 0;
        }

        $num = (float) $valor;
        if ($num == 0.0) {
            return '—';
        }

        if ($layoutPlan) {
            return CantidadCajaSupport::formatearVisualizacionCelda($num, $nomFamilia);
        }

        return CantidadCajaSupport::formatearCantidadFlexible($num);
    };

    $editablePlan = ! empty($editablePlan);
    $layoutPlan = ! empty($layoutPlan) || $editablePlan;

    $celdaSemana = static function (array $fila, string $clave) use ($layoutPlan, $editablePlan): array {
        $dato = $fila[$clave] ?? 0;
        $nomFamilia = (string) ($fila['nom_familia'] ?? '');

        if (is_array($dato)) {
            $num = (float) ($dato['valor'] ?? 0);
            $texto = $dato['texto'] ?? null;
            if ($texto === null) {
                $texto = $num == 0.0 ? '—' : (
                    $layoutPlan
                        ? CantidadCajaSupport::formatearVisualizacionCelda($num, $nomFamilia)
                        : CantidadCajaSupport::formatearCantidadFlexible($num)
                );
            }

            return [
                'texto' => $texto,
                'alerta' => (bool) ($dato['alerta'] ?? false),
                'negativo' => (bool) ($dato['negativo'] ?? false) || $num < 0 || (bool) ($dato['alerta'] ?? false),
                'num' => $num,
            ];
        }

        if (is_string($dato)) {
            return [
                'texto' => $dato,
                'alerta' => false,
                'negativo' => false,
                'num' => $editablePlan ? (float) str_replace(',', '', $dato) : 0.0,
            ];
        }

        $num = (float) $dato;

        return [
            'texto' => $num == 0.0 ? '—' : (
                $layoutPlan
                    ? CantidadCajaSupport::formatearVisualizacionCelda($num, $nomFamilia)
                    : CantidadCajaSupport::formatearCantidadFlexible($num)
            ),
            'alerta' => false,
            'negativo' => $num < 0,
            'num' => $num,
        ];
    };
    $resaltarNegativos = ! empty($resaltarNegativos);
    $columnasExtra = $columnasExtra ?? [];
    $cabeceraUnaLinea = ! empty($cabeceraUnaLinea);
    $mostrarTotal = ! empty($mostrarTotal);
@endphp
<div class="programas-grid-wrap mrp-grid-wrap" @if(!empty($scrollId)) id="{{ $scrollId }}" data-mrp-scroll @endif>
<table @class(['programas-grid', 'mrp-grid', 'mrp-grid--plan' => $layoutPlan, 'mrp-grid--consumo' => $resaltarNegativos, 'mrp-grid--cabecera-plana' => $cabeceraUnaLinea])>
<thead>
<tr>
@if ($cabeceraUnaLinea)
<th class="programas-grid__th mrp-grid__th--sticky mrp-grid__th--code">Código PL</th>
<th class="programas-grid__th mrp-grid__th--sticky mrp-grid__th--material">Material PL</th>
@else
<th class="programas-grid__th programas-grid__th--stack mrp-grid__th--sticky mrp-grid__th--code">Código PL</th>
<th class="programas-grid__th programas-grid__th--stack mrp-grid__th--sticky mrp-grid__th--material">Material PL</th>
@endif
@foreach ($columnasExtra as $col)
<th class="programas-grid__th programas-grid__th--num mrp-grid__th--sticky mrp-grid__th--extra">{{ $col['label'] }}</th>
@endforeach
@foreach ($semanas as $sem)
<th @class(['programas-grid__th', 'programas-grid__th--num', 'mrp-grid__th--sem', 'programas-grid__th--stack' => ! $cabeceraUnaLinea]) title="{{ $sem->fechaInicio?->format('d/m/Y') }}">{{ $sem->etiqueta }}</th>
@endforeach
@if ($mostrarTotal)
<th class="programas-grid__th programas-grid__th--num mrp-grid__th--total">Total</th>
@endif
</tr>
</thead>
<tbody>
@forelse ($filas as $fila)
<tr @if(! empty($fila['idMaterialPL'])) data-mat-id="{{ $fila['idMaterialPL'] }}" @endif>
<td class="programas-grid__code mrp-grid__td--sticky mrp-grid__td--code">{{ $fila['codigoPL'] }}</td>
<td class="programas-grid__material mrp-grid__td--sticky mrp-grid__td--material">{{ $fila['descripcion'] }}</td>
@foreach ($columnasExtra as $col)
<td class="programas-grid__qty mrp-grid__td--sticky mrp-grid__td--extra">{{ $formatearCantidad($fila[$col['key']] ?? 0, $fila['nom_familia'] ?? null) }}</td>
@endforeach
@php $totalFila = 0.0; @endphp
@foreach ($semanas as $sem)
@php
    $clave = 'sem_'.$sem->idSemConsumo;
@endphp
@if (! $editablePlan && ! $resaltarNegativos && is_string($fila[$clave] ?? null))
<td class="programas-grid__qty" data-mat="{{ $fila['idMaterialPL'] }}" data-sem="{{ $sem->idSemConsumo }}">{{ $fila[$clave] }}</td>
@else
@php
    $celda = $celdaSemana($fila, $clave);
    $totalFila += $celda['num'];
@endphp
@if ($editablePlan)
<td class="programas-grid__qty mrp-grid__qty--editable" data-mat="{{ $fila['idMaterialPL'] }}" data-sem="{{ $sem->idSemConsumo }}" data-val="{{ $celda['num'] == 0.0 ? '' : number_format($celda['num'], 2, '.', '') }}"><span class="mrp-grid__valor">{{ $celda['texto'] }}</span></td>
@else
<td @class(['programas-grid__qty', 'mrp-grid__qty--alerta' => $resaltarNegativos && $celda['negativo']]) data-mat="{{ $fila['idMaterialPL'] }}" data-sem="{{ $sem->idSemConsumo }}">{{ $celda['texto'] }}</td>
@endif
@endif
@endforeach
@if ($mostrarTotal)
<td class="programas-grid__qty mrp-grid__cell--total">{{ $totalFila == 0.0 ? '—' : $formatearCantidad($totalFila, $fila['nom_familia'] ?? null) }}</td>
@endif
</tr>
@empty
<tr>
<td colspan="{{ 2 + count($columnasExtra) + $semanas->count() + ($mostrarTotal ? 1 : 0) }}" class="mrp-grid__empty">{{ $mensajeVacio ?? 'Sin datos.' }}</td>
</tr>
@endforelse
</tbody>
</table>
</div>