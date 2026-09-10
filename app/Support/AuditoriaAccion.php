<?php

namespace App\Support;

class AuditoriaAccion
{
    public const LOGIN = 'login';

    public const LOGIN_FALLIDO = 'login.fallido';

    public const LOGOUT = 'logout';

    public const USUARIO_CREAR = 'usuario.crear';

    public const USUARIO_ACTUALIZAR = 'usuario.actualizar';

    public const USUARIO_DESACTIVAR = 'usuario.desactivar';

    public const USUARIO_ACTIVAR = 'usuario.activar';

    public const PROVEEDOR_CREAR = 'proveedor.crear';

    public const PROVEEDOR_ACTUALIZAR = 'proveedor.actualizar';

    public const PROVEEDOR_ELIMINAR = 'proveedor.eliminar';

    public const PROGRAMA_CREAR = 'programa.crear';

    public const PROGRAMA_VACIAR = 'programa.vaciar';

    public const PROGRAMA_IMAGEN_ACTUALIZAR = 'programa.imagen.actualizar';

    public const PROGRAMA_PARAM_GUARDAR = 'programa.param.guardar';

    public const MRP_IMPORTAR = 'mrp.importar';

    public const MRP_VACIAR = 'mrp.vaciar';

    public const MRP_PLAN_ACTUALIZAR = 'mrp.plan.actualizar';

    public const PLAN_PRODUCCION_IMPORTAR = 'plan_produccion.importar';

    public const PLAN_PRODUCCION_VACIAR = 'plan_produccion.vaciar';

    public const PLAN_PRODUCCION_ACTUALIZAR = 'plan_produccion.actualizar';

    public const REGISTRO_INGRESO_CREAR = 'registro.ingreso.crear';

    public const REGISTRO_INGRESO_ACTUALIZAR = 'registro.ingreso.actualizar';

    public const REGISTRO_EGRESO_CREAR = 'registro.egreso.crear';

    public const REGISTRO_EGRESO_ACTUALIZAR = 'registro.egreso.actualizar';

    public const REGISTRO_TRASLADO_CREAR = 'registro.traslado.crear';

    public const REGISTRO_TRASLADO_ACTUALIZAR = 'registro.traslado.actualizar';

    public const REGISTRO_MOVIMIENTO_ANULAR = 'registro.movimiento.anular';

    public const REGISTRO_TRASLADO_ANULAR = 'registro.traslado.anular';

    /**
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            self::LOGIN => 'Inicio de sesión',
            self::LOGIN_FALLIDO => 'Intento fallido de sesión',
            self::LOGOUT => 'Cierre de sesión',
            self::USUARIO_CREAR => 'Usuario creado',
            self::USUARIO_ACTUALIZAR => 'Usuario actualizado',
            self::USUARIO_DESACTIVAR => 'Usuario desactivado',
            self::USUARIO_ACTIVAR => 'Usuario activado',
            self::PROVEEDOR_CREAR => 'Proveedor creado',
            self::PROVEEDOR_ACTUALIZAR => 'Proveedor actualizado',
            self::PROVEEDOR_ELIMINAR => 'Proveedor eliminado',
            self::PROGRAMA_CREAR => 'Receta creada',
            self::PROGRAMA_VACIAR => 'Receta vaciada',
            self::PROGRAMA_IMAGEN_ACTUALIZAR => 'Imagen de receta actualizada',
            self::PROGRAMA_PARAM_GUARDAR => 'Parámetros de presentación guardados',
            self::MRP_IMPORTAR => 'Plan MRP importado',
            self::MRP_VACIAR => 'Plan MRP vaciado',
            self::MRP_PLAN_ACTUALIZAR => 'Plan de consumo actualizado',
            self::PLAN_PRODUCCION_IMPORTAR => 'Plan de producción importado',
            self::PLAN_PRODUCCION_VACIAR => 'Plan de producción vaciado',
            self::PLAN_PRODUCCION_ACTUALIZAR => 'Plan de producción actualizado',
            self::REGISTRO_INGRESO_CREAR => 'Ingreso registrado',
            self::REGISTRO_INGRESO_ACTUALIZAR => 'Ingreso actualizado',
            self::REGISTRO_EGRESO_CREAR => 'Consumo registrado',
            self::REGISTRO_EGRESO_ACTUALIZAR => 'Consumo actualizado',
            self::REGISTRO_TRASLADO_CREAR => 'Traslado registrado',
            self::REGISTRO_TRASLADO_ACTUALIZAR => 'Traslado actualizado',
            self::REGISTRO_MOVIMIENTO_ANULAR => 'Movimiento anulado',
            self::REGISTRO_TRASLADO_ANULAR => 'Traslado anulado',
        ];
    }

    public static function etiqueta(string $accion): string
    {
        return self::etiquetas()[$accion] ?? $accion;
    }
}
