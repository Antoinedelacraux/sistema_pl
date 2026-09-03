<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $tituloDocumento ?? 'Exportación' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #241610;
            margin: 0;
            padding: 12px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 16px;
            text-align: center;
        }
        p.meta {
            margin: 0 0 10px;
            color: #6e5a4c;
            font-size: 9px;
            text-align: left;
            line-height: 1.5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 3px 4px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #1f3864;
            color: #fff;
            font-size: 7px;
        }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p class="meta">
        {{ number_format($total) }} Registros · Generado {{ $generado }}<br>
        Exportado Por: {{ $exportadoPor }}
    </p>
    <table>
        <thead>
            <tr>
                @foreach ($encabezados as $encabezado)
                    <th>{{ $encabezado }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ e($celda) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
