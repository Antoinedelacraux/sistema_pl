<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lectura de Stock.xlsx (col. B código, C descripción, D cantidad, E familia; datos desde fila 3).
 * Antes: database/_stock_excel_helpers.php (funciones globales).
 */
final class StockExcelFilas
{
    /**
     * @return list<array{codigo: string, descripcion: string, familia: string, cantidad: float}>
     */
    public static function leer(Worksheet $hoja): array
    {
        $filas = [];
        $filaMax = max((int) $hoja->getHighestRow(), (int) $hoja->getHighestDataRow(), 200);
        $vaciasSeguidas = 0;

        for ($fila = 3; $fila <= $filaMax; $fila++) {
            $codigo = self::normalizarCodigoCelda($hoja, 'B'.$fila);
            $descripcion = trim(self::textoCelda($hoja, 'C'.$fila));
            $familia = trim(self::textoCelda($hoja, 'E'.$fila));
            $cantidad = self::parseCantidadCelda($hoja, 'D'.$fila);

            if ($codigo === '-' && $descripcion === '') {
                $vaciasSeguidas++;
                if ($vaciasSeguidas >= 5) {
                    break;
                }

                continue;
            }

            $vaciasSeguidas = 0;

            $filas[] = [
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'familia' => $familia,
                'cantidad' => $cantidad,
            ];
        }

        return $filas;
    }

    public static function textoCelda(Worksheet $hoja, string $celda): string
    {
        $valor = $hoja->getCell($celda)->getCalculatedValue();

        return trim((string) ($valor ?? ''));
    }

    public static function normalizarCodigoCelda(Worksheet $hoja, string $celda): string
    {
        $cell = $hoja->getCell($celda);
        $valor = $cell->getValue();

        if ($valor === null || $valor === '') {
            return '-';
        }

        if (is_int($valor) || is_float($valor)) {
            return sprintf('%.0f', (float) $valor);
        }

        $texto = trim((string) $cell->getFormattedValue());
        if ($texto === '' || $texto === '-' || $texto === '—') {
            return '-';
        }

        return $texto;
    }

    public static function parseCantidadCelda(Worksheet $hoja, string $celda): float
    {
        $cell = $hoja->getCell($celda);
        $valor = $cell->getValue();

        if (is_int($valor) || is_float($valor)) {
            return max(0.0, (float) $valor);
        }

        if (is_string($valor)) {
            $raw = trim($valor);
            if ($raw !== '' && is_numeric($raw)) {
                return max(0.0, (float) $raw);
            }
        }

        $texto = trim((string) $cell->getFormattedValue());
        if ($texto === '' || $texto === '-' || $texto === '—') {
            return 0.0;
        }

        $texto = preg_replace('/\s+/u', '', $texto) ?? $texto;
        $texto = self::normalizarTextoCantidad($texto);

        return max(0.0, (float) $texto);
    }

    /** Coma de miles en Excel (18,448 → 18448). Coma decimal solo si no es patrón de miles. */
    public static function normalizarTextoCantidad(string $texto): string
    {
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $texto)) {
            return str_replace(',', '', $texto);
        }

        if (preg_match('/,\d{1,2}$/', $texto)) {
            return str_replace(',', '.', $texto);
        }

        return str_replace(',', '', $texto);
    }
}
