<?php

namespace App\Support;

final class PermisoAccion
{
    public const VER = 'ver';

    public const CREAR = 'crear';

    public const EDITAR = 'editar';

    public const ELIMINAR = 'eliminar';

    public static function todas(): array
    {
        return [
            self::VER,
            self::CREAR,
            self::EDITAR,
            self::ELIMINAR,
        ];
    }
}
