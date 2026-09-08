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
            return [
                'texto' => ($dato['valor'] ?? 0) == 0.0
                    ? '—'
                    : number_format((float) $dato['valor'], 2, '.', ''),
                'alerta' => (bool) ($dato['alerta'] ?? false),
                'negativo' => ((float) ($dato['valor'] ?? 0)) < 0,
            ];
        }

        $num = (float) $dato;

        return [
            'texto' => $num == 0.0 ? '—' : number_format($num, 2, '.', ''),
            'alerta' => false,
            'negativo' => $num < 0,
        ];
    };
@endphp

<div class="programas-grid-wrap mrp-grid-wrap" @if(!empty($scrollId)) id="{{ $scrollId }}" data-mrp-scroll @endif>
    <table @class([
        'programas-grid',
        'mrp-grid',
        'mrp-grid--plan' => ! empty($editablePlan),
        'mrp-grid--consumo' => ! empty($resaltarNegativos),
    ])>
        <thead>
            <tr>
                <th class="programas-grid__th programas-grid__th--stack mrp-grid__th--sticky mrp-grid__th--code">Código<br>PL</th>
                <th class="programas-grid__th programas-grid__th--stack mrp-grid__th--sticky mrp-grid__th--material">Material<br>PL</th>
                @foreach ($columnasExtra ?? [] as $col)
                    <th class="programas-grid__th programas-grid__th--num mrp-grid__th--sticky mrp-grid__th--extra">{{ $col['label'] }}</th>
                @endforeach
                @foreach ($semanas as $sem)
                    <th class="programas-grid__th programas-grid__th--stack programas-grid__th--num mrp-grid__th--sem" title="{{ $sem->fechaInicio?->format('d/m/Y') }}">
                        {{ $sem->etiqueta }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    <td class="programas-grid__code mrp-grid__td--sticky mrp-grid__td--code">{{ $fila['codigoPL'] }}</td>
                    <td class="programas-grid__material mrp-grid__td--sticky mrp-grid__td--material">{{ $fila['descripcion'] }}</td>
                    @foreach ($columnasExtra ?? [] as $col)
                        <td class="programas-grid__qty mrp-grid__td--sticky mrp-grid__td--extra">{{ $formatearCantidad($fila[$col['key']] ?? 0) }}</td>
                    @endforeach
                    @foreach ($semanas as $sem)
                        @php
                            $clave = 'sem_'.$sem->idSemConsumo;
                            $celda = $celdaSemana($fila, $clave);
                            $valorNumerico = is_array($fila[$clave] ?? null)
                                ? (float) (($fila[$clave]['valor'] ?? 0))
                                : (float) ($fila[$clave] ?? 0);
                        @endphp
                        <td @class([
                            'programas-grid__qty',
                            'mrp-grid__qty--alerta' => ! empty($resaltarNegativos) && $celda['negativo'],
                            'mrp-grid__qty--editable' => ! empty($editablePlan),
                        ])>
                            @if (! empty($editablePlan))
                                <span class="mrp-grid__valor">{{ $celda['texto'] }}</span>
                                <input
                                    type="number"
                                    class="mrp-grid__input"
                                    name="cantidades[{{ $fila['idMaterialPL'] }}][{{ $sem->idSemConsumo }}]"
                                    value="{{ $valorNumerico == 0.0 ? '' : number_format($valorNumerico, 2, '.', '') }}"
                                    step="0.01"
                                    min="0"
                                    inputmode="decimal"
                                    data-original="{{ $valorNumerico == 0.0 ? '' : number_format($valorNumerico, 2, '.', '') }}"
                                    disabled
                                    hidden
                                >
                            @else
                                {{ $celda['texto'] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 2 + count($columnasExtra ?? []) + $semanas->count() }}" class="mrp-grid__empty">
                        {{ $mensajeVacio ?? 'Sin datos.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
