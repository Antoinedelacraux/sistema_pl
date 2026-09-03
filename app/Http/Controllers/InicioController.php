<?php

namespace App\Http\Controllers;

use App\Services\Inicio\InicioService;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __construct(
        private readonly InicioService $inicioService
    ) {}

    public function __invoke(): View
    {
        $user = auth()->user();
        $user->load(['persona.area', 'persona.cargo', 'rol']);

        return view('inicio', array_merge($this->inicioService->dashboard(), [
            'accesosRapidos' => $this->accesosRapidos($user),
            'activeNav' => 'inicio',
        ]));
    }

    /**
     * @return list<array{label: string, descripcion: string, route: string, modulo: string}>
     */
    private function accesosRapidos($user): array
    {
        $modulos = [
            ['label' => 'Resumen', 'descripcion' => 'Stock consolidado por almacén', 'route' => 'resumen.index', 'modulo' => 'resumen'],
            ['label' => 'Receta', 'descripcion' => 'Materiales por programa', 'route' => 'programas.index', 'modulo' => 'programas'],
            ['label' => 'Proveedor', 'descripcion' => 'Catálogo de proveedores', 'route' => 'proveedor.index', 'modulo' => 'proveedor'],
            ['label' => 'Inventario', 'descripcion' => 'Stock detallado SFC · SF · FC', 'route' => 'inventario.index', 'modulo' => 'inventario'],
            ['label' => 'Registro', 'descripcion' => 'Ingresos, consumos y traslados', 'route' => 'registro.index', 'modulo' => 'registro'],
        ];

        return array_values(array_filter(
            $modulos,
            fn (array $item) => $user->puedeVer($item['modulo'])
        ));
    }
}
