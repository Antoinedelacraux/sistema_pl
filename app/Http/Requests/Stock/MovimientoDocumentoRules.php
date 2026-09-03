<?php

namespace App\Http\Requests\Stock;

trait MovimientoDocumentoRules
{
    /**
     * @return array<string, mixed>
     */
    protected function documentoRules(): array
    {
        $maxKb = (int) config('power_automate.max_upload_kb', 10240);

        return [
            'factura' => ['nullable', 'string', 'max:50'],
            'guia' => ['nullable', 'string', 'max:50'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.$maxKb],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function documentoAttributes(): array
    {
        return [
            'factura' => 'factura',
            'guia' => 'guía',
            'archivo_pdf' => 'archivo PDF',
        ];
    }
}
