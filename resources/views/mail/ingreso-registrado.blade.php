<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ingreso registrado</title>
</head>
<body style="font-family: sans-serif; font-size: 14px; color: #222;">
    <h2 style="margin: 0 0 12px;">Ingreso registrado</h2>

    <p style="margin: 0 0 8px;"><strong>Almacén:</strong> {{ $payload['almacen'] ?? '—' }}</p>
    <p style="margin: 0 0 8px;"><strong>Fecha:</strong> {{ $payload['fecha'] ?? '—' }}</p>
    <p style="margin: 0 0 8px;"><strong>Registrado por:</strong> {{ $payload['registrado_por'] ?? '—' }}</p>

    @if (! empty($payload['factura']))
        <p style="margin: 0 0 8px;"><strong>Factura:</strong> {{ $payload['factura'] }}</p>
    @endif
    @if (! empty($payload['guia']))
        <p style="margin: 0 0 8px;"><strong>Guía:</strong> {{ $payload['guia'] }}</p>
    @endif
    @if (! empty($payload['observacion']))
        <p style="margin: 0 0 8px;"><strong>Observación:</strong> {{ $payload['observacion'] }}</p>
    @endif

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; margin: 16px 0; min-width: 420px;">
        <thead>
            <tr style="background: #f4f4f4;">
                <th align="left">Código PL</th>
                <th align="left">Material</th>
                <th align="right">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payload['lineas'] ?? [] as $linea)
                <tr>
                    <td>{{ $linea['codigo_pl'] ?? '—' }}</td>
                    <td>{{ $linea['nom_material'] ?? '—' }}</td>
                    <td align="right">{{ number_format((float) ($linea['cantidad'] ?? 0), 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (! empty($payload['url_registro']))
        <p style="margin: 16px 0 0;">
            <a href="{{ $payload['url_registro'] }}">Ver ingresos en el sistema</a>
        </p>
    @endif
</body>
</html>
