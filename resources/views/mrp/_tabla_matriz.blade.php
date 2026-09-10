@php
    $formatearCantidad = static function ($valor): string {
        if (is_array($valor)) {
            $valor = $valor['valor'] ?? 0;
        }

        $num = (float) $valor;
        if ($num == 0.0) {
            return '—';
        }

        return number_format($num, 2, '.', '');
    };

    $celdaSemana = static function (array $fila, string $clave): array {
        $dato = $fila[$clave] ?? 0;

        if (is_array($dato)) {
            $num = (float) ($dato['valor'] ?? 0);

            return [
                'texto' => $num == 0.0 ? '—' : number_format($num, 2, '.', ''),
                'alerta' => (bool) ($dato['alerta'] ?? false),
                'negativo' => (bool) ($dato['negativo'] ?? false) || $num < 0 || (bool) ($dato['alerta'] ?? false),
                'num' => $num,
            ];
        }

        $num = (float) $dato;

        return [
            'texto' => $num == 0.0 ? '—' : number_format($num, 2, '.', ''),
            'alerta' => false,
            'negativo' => $num < 0,
            'num' => $num,
        ];
    };

    $editablePlan = ! empty($editablePlan);
    $layoutPlan = ! empty($layoutPlan) || $editablePlan;
    $resaltarNegativos = ! empty($resaltarNegativos);
    $columnasExtra = $columnasExtra ?? [];
    $cabeceraUnaLinea = ! empty($cabeceraUnaLinea);
@endphp
<div class="programas-grid-wrap mrp-grid-wrap" @if(!empty($scrollId)) id="{{ $scrollId }}" data-mrp-scroll @endif>
<table @class(['programas-grid', 'mrp-grid', 'mrp-grid--plan' => $layoutPlan, 'mrp-grid--consumo' => $resaltarNegativos, 'mrp-grid--cabecera-plana' => $cabeceraUnaLinea])>
<thead>
<tr>
@if ($cabeceraUnaLinea)
<th class="programas-grid__th mrp-grid__th--sticky mrp-grid__th--code">Código PL</th>
<th class="programas-grid__th mrp-grid__th--sticky mrp-grid__th--material">Material PL</th>
@else
<th class="programas-grid__th programas-grid__th--stack mrp-grid__th--sticky mrp-grid__th--code">Código<br>PL</th>
<th class="programas-grid__th programas-grid__th--stack mrp-grid__th--sticky mrp-grid__th--material">Material<br>PL</th>
@endif
@foreach ($columnasExtra as $col)
<th class="programas-grid__th programas-grid__th--num mrp-grid__th--sticky mrp-grid__th--extra">{{ $col['label'] }}</th>
@endforeach
@foreach ($semanas as $sem)
<th @class(['programas-grid__th', 'programas-grid__th--num', 'mrp-grid__th--sem', 'programas-grid__th--stack' => ! $cabeceraUnaLinea]) title="{{ $sem->fechaInicio?->format('d/m/Y') }}">{{ $sem->etiqueta }}</th>
@endforeach
</tr>
</thead>
<tbody>
@forelse ($filas as $fila)
<tr>
<td class="programas-grid__code mrp-grid__td--sticky mrp-grid__td--code">{{ $fila['codigoPL'] }}</td>
<td class="programas-grid__material mrp-grid__td--sticky mrp-grid__td--material">{{ $fila['descripcion'] }}</td>
@foreach ($columnasExtra as $col)
<td class="programas-grid__qty mrp-grid__td--sticky mrp-grid__td--extra">{{ $formatearCantidad($fila[$col['key']] ?? 0) }}</td>
@endforeach
@foreach ($semanas as $sem)
@php
    $clave = 'sem_'.$sem->idSemConsumo;
    $celda = $celdaSemana($fila, $clave);
@endphp
@if ($editablePlan)
<td class="programas-grid__qty mrp-grid__qty--editable" data-mat="{{ $fila['idMaterialPL'] }}" data-sem="{{ $sem->idSemConsumo }}" data-val="{{ $celda['num'] == 0.0 ? '' : number_format($celda['num'], 2, '.', '') }}"><span class="mrp-grid__valor">{{ $celda['texto'] }}</span></td>
@else
<td @class(['programas-grid__qty', 'mrp-grid__qty--alerta' => $resaltarNegativos && $celda['negativo']])>{{ $celda['texto'] }}</td>
@endif
@endforeach
</tr>
@empty
<tr>
<td colspan="{{ 2 + count($columnasExtra) + $semanas->count() }}" class="mrp-grid__empty">{{ $mensajeVacio ?? 'Sin datos.' }}</td>
</tr>
@endforelse
</tbody>
</table>
</div>