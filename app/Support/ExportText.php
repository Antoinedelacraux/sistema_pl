<?php

namespace App\Support;

class ExportText
{
    public static function tituloPalabras(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($texto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    public static function tituloSecciones(string $texto): string
    {
        $partes = array_map(
            fn (string $parte) => self::tituloPalabras($parte),
            explode('·', $texto)
        );

        return implode(' · ', $partes);
    }

    /**
     * @param  list<string>  $textos
     * @return list<string>
     */
    public static function tituloPalabrasLista(array $textos): array
    {
        return array_map(fn (string $texto) => self::tituloPalabras($texto), $textos);
    }
}
