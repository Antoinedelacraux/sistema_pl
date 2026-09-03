<?php

return [

    'acciones' => [
        'ver' => 'Ver',
        'crear' => 'Crear',
        'editar' => 'Editar',
        'eliminar' => 'Eliminar',
    ],

    /*
    | Permisos por defecto al elegir un rol (idRol del seed).
    | 'all' = marcar todo. null = sin acceso.
    */
    'roles' => [
        1 => 'all',

        2 => [
            'inicio' => ['ver' => true],
            'programas' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'maestros' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'resumen' => ['ver' => true, 'crear' => false, 'editar' => true, 'eliminar' => false],
            'inventario' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'materiales' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'familias' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'registro' => ['ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => false],
        ],

        3 => [
            'inicio' => ['ver' => true],
            'programas' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'maestros' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'resumen' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'inventario' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'materiales' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'familias' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
            'registro' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
        ],
    ],

];
