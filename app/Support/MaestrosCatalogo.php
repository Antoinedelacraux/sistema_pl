<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Cargo;
use App\Models\Programas\Cliente;
use App\Models\Programas\Familia;
use App\Models\Programas\Mercado;
use App\Models\Programas\Presentacion;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\ProveedorCatalogo;
use App\Models\Programas\Situado;
use App\Models\Programas\TipoConsumo;
use App\Models\Programas\Uso;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use InvalidArgumentException;

class MaestrosCatalogo
{
    /** @var list<string> */
    public const TABS = [
        'mercado',
        'cliente',
        'programa',
        'presentacion',
        'familia',
        'uso',
        'situado',
        'proveedor',
        'tipo_consumo',
        'area',
        'cargo',
        'rol',
    ];

    /** @var list<string> */
    public const TABS_CONFIGURACION = [
        'area',
        'cargo',
        'rol',
    ];

    /**
     * @return array<string, array{
     *   model: class-string,
     *   pk: string,
     *   nombre: string,
     *   tabla: string,
     *   label: string,
     *   labelPlural: string,
     *   maxLength: int,
     * }>
     */
    public static function definiciones(): array
    {
        return [
            'mercado' => [
                'model' => Mercado::class,
                'pk' => 'idMercado',
                'nombre' => 'nomMercado',
                'tabla' => 'Mercado',
                'label' => 'Mercado',
                'labelPlural' => 'Mercados',
                'maxLength' => 100,
            ],
            'cliente' => [
                'model' => Cliente::class,
                'pk' => 'idCliente',
                'nombre' => 'nomCliente',
                'tabla' => 'Cliente',
                'label' => 'Cliente',
                'labelPlural' => 'Clientes',
                'maxLength' => 150,
            ],
            'programa' => [
                'model' => ProgramaCatalogo::class,
                'pk' => 'idPrograma',
                'nombre' => 'nomPrograma',
                'tabla' => 'Programa',
                'label' => 'Programa',
                'labelPlural' => 'Programas',
                'maxLength' => 150,
            ],
            'presentacion' => [
                'model' => Presentacion::class,
                'pk' => 'idPresentacion',
                'nombre' => 'nomPresentacion',
                'tabla' => 'Presentacion',
                'label' => 'Presentación',
                'labelPlural' => 'Presentaciones',
                'maxLength' => 150,
            ],
            'familia' => [
                'model' => Familia::class,
                'pk' => 'idFamilia',
                'nombre' => 'nomFamilia',
                'tabla' => 'Familia',
                'label' => 'Familia',
                'labelPlural' => 'Familias',
                'maxLength' => 80,
            ],
            'uso' => [
                'model' => Uso::class,
                'pk' => 'idUso',
                'nombre' => 'nomUso',
                'tabla' => 'Uso',
                'label' => 'Uso',
                'labelPlural' => 'Usos',
                'maxLength' => 50,
            ],
            'situado' => [
                'model' => Situado::class,
                'pk' => 'idSituado',
                'nombre' => 'nomSituado',
                'tabla' => 'Situado',
                'label' => 'Situado',
                'labelPlural' => 'Situados',
                'maxLength' => 50,
            ],
            'proveedor' => [
                'model' => ProveedorCatalogo::class,
                'pk' => 'idProveedorCatalogo',
                'nombre' => 'nomProveedor',
                'tabla' => 'ProveedorCatalogo',
                'label' => 'Proveedor',
                'labelPlural' => 'Proveedores',
                'maxLength' => 100,
            ],
            'tipo_consumo' => [
                'model' => TipoConsumo::class,
                'pk' => 'idTipoConsumo',
                'nombre' => 'nomTipoConsumo',
                'tabla' => 'TipoConsumo',
                'label' => 'Tipo consumo',
                'labelPlural' => 'Tipos de consumo',
                'maxLength' => 100,
            ],
            'area' => [
                'model' => Area::class,
                'pk' => 'idArea',
                'nombre' => 'nomArea',
                'tabla' => 'Area',
                'label' => 'Área',
                'labelPlural' => 'Áreas',
                'maxLength' => 100,
            ],
            'cargo' => [
                'model' => Cargo::class,
                'pk' => 'idCargo',
                'nombre' => 'nomCargo',
                'tabla' => 'Cargo',
                'label' => 'Cargo',
                'labelPlural' => 'Cargos',
                'maxLength' => 100,
            ],
            'rol' => [
                'model' => Rol::class,
                'pk' => 'idRol',
                'nombre' => 'Nombre',
                'tabla' => 'Rol',
                'label' => 'Rol',
                'labelPlural' => 'Roles',
                'maxLength' => 50,
            ],
        ];
    }

    /**
     * @return array{
     *   model: class-string,
     *   pk: string,
     *   nombre: string,
     *   tabla: string,
     *   label: string,
     *   labelPlural: string,
     *   maxLength: int,
     * }
     */
    public static function definicion(string $tab): array
    {
        $definiciones = self::definiciones();

        if (! isset($definiciones[$tab])) {
            throw new InvalidArgumentException('Catálogo no válido.');
        }

        return $definiciones[$tab];
    }

    public static function tabValido(string $tab): bool
    {
        return in_array($tab, self::TABS, true);
    }

    public static function normalizarTab(?string $tab): string
    {
        $tab = (string) $tab;

        return self::tabValido($tab) ? $tab : self::TABS[0];
    }

    public static function esTabConfiguracion(string $tab): bool
    {
        return in_array($tab, self::TABS_CONFIGURACION, true);
    }
}
