<?php

namespace App\Support;

final class DocumentoGuiaSupport
{
    public static function resolver(?string $guia, ?string $factura, ?string $facturaGuia): ?string
    {
        if ($guia) {
            return $guia;
        }

        if ($factura || ! $facturaGuia) {
            return null;
        }

        return $facturaGuia;
    }
}
