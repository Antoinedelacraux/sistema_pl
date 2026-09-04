<?php

namespace App\Services\Maestros;

use App\Models\Estado;
use App\Models\Persona;
use App\Models\Programas\ProgramaFila;
use App\Models\Proveedores\Proveedor;
use App\Models\Stock\MovimientoStock;
use App\Models\Usuario;
use App\Support\GridPagination;
use App\Support\MaestrosCatalogo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class MaestroCatalogoService
{
    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function listar(string $tab, string $buscar, Request $request): LengthAwarePaginator
    {
        $definicion = MaestrosCatalogo::definicion($tab);
        $modelClass = $definicion['model'];
        $columnaNombre = $definicion['nombre'];

        $query = $modelClass::query()->orderBy($columnaNombre);

        if ($buscar !== '') {
            $termino = mb_strtolower($buscar);
            $query->whereRaw('LOWER('.$columnaNombre.') LIKE ?', ['%'.$termino.'%']);
        }

        return $query->paginate(GridPagination::PER_PAGE)->withQueryString();
    }

    public function crear(string $tab, string $nombre): Model
    {
        $definicion = MaestrosCatalogo::definicion($tab);
        $modelClass = $definicion['model'];
        $columnaNombre = $definicion['nombre'];
        $nombre = trim($nombre);

        $existente = $modelClass::query()
            ->whereRaw('LOWER('.$columnaNombre.') = ?', [mb_strtolower($nombre)])
            ->first();

        if ($existente) {
            if ((int) $existente->getAttribute('Estado') === Estado::INACTIVO) {
                $existente->update([
                    $columnaNombre => $nombre,
                    'Estado' => Estado::ACTIVO,
                ]);

                return $existente->fresh();
            }

            throw new InvalidArgumentException(
                'El nombre de '.$definicion['label'].' ya está registrado.'
            );
        }

        return $modelClass::query()->create([
            $columnaNombre => $nombre,
            'Estado' => Estado::ACTIVO,
        ]);
    }

    public function actualizar(string $tab, int $id, string $nombre, int $estado): Model
    {
        $definicion = MaestrosCatalogo::definicion($tab);
        $modelClass = $definicion['model'];
        $columnaNombre = $definicion['nombre'];
        $pk = $definicion['pk'];

        $registro = $modelClass::query()->findOrFail($id);
        $registro->update([
            $columnaNombre => trim($nombre),
            'Estado' => $estado,
        ]);

        return $registro->fresh();
    }

    public function desactivar(string $tab, int $id): void
    {
        $mensaje = $this->mensajeEnUso($tab, $id);
        if ($mensaje !== null) {
            throw new InvalidArgumentException($mensaje);
        }

        $definicion = MaestrosCatalogo::definicion($tab);
        $modelClass = $definicion['model'];

        $modelClass::query()->findOrFail($id)->update(['Estado' => Estado::INACTIVO]);
    }

    public function activar(string $tab, int $id): Model
    {
        $definicion = MaestrosCatalogo::definicion($tab);
        $modelClass = $definicion['model'];

        $registro = $modelClass::query()->findOrFail($id);
        $registro->update(['Estado' => Estado::ACTIVO]);

        return $registro->fresh();
    }

    public function mensajeEnUso(string $tab, int $id): ?string
    {
        $definicion = MaestrosCatalogo::definicion($tab);
        $modelClass = $definicion['model'];
        $pk = $definicion['pk'];
        $registro = $modelClass::query()->find($id);

        if (! $registro) {
            return null;
        }

        $enUso = match ($tab) {
            'mercado' => ProgramaFila::query()->where('idMercado', $id)->exists(),
            'cliente' => ProgramaFila::query()->where('idCliente', $id)->exists(),
            'programa' => ProgramaFila::query()->where('idPrograma', $id)->exists()
                || MovimientoStock::query()->where('idPrograma', $id)->where('estado', 1)->exists(),
            'presentacion' => ProgramaFila::query()->where('idPresentacion', $id)->exists()
                || MovimientoStock::query()->where('idPresentacion', $id)->where('estado', 1)->exists(),
            'familia' => ProgramaFila::query()->where('idFamilia', $id)->exists()
                || Proveedor::query()->where('idFamilia', $id)->where('estado', 1)->exists(),
            'uso' => ProgramaFila::query()->where('idUso', $id)->exists(),
            'situado' => ProgramaFila::query()->where('idSituado', $id)->exists(),
            'proveedor' => Proveedor::query()
                ->where('estado', 1)
                ->whereRaw('UPPER(nomProveedor) = ?', [mb_strtoupper((string) $registro->{$definicion['nombre']})])
                ->exists(),
            'tipo_consumo' => MovimientoStock::query()
                ->where('idTipoConsumo', $id)
                ->where('estado', 1)
                ->exists(),
            'area' => Persona::query()->where('idArea', $id)->exists(),
            'cargo' => Persona::query()->where('idCargo', $id)->exists(),
            'rol' => Usuario::query()->where('idRol', $id)->exists(),
            default => false,
        };

        if (! $enUso) {
            return null;
        }

        return match ($tab) {
            'mercado', 'cliente', 'programa', 'presentacion', 'familia', 'uso', 'situado' => 'No se puede desactivar: está en uso en Receta u otro módulo.',
            'proveedor' => 'No se puede desactivar: el proveedor está referenciado en el módulo Proveedor.',
            'tipo_consumo' => 'No se puede desactivar: el tipo de consumo está usado en registros de Consumo.',
            'area' => 'No se puede desactivar: el área está asignada a personas del sistema.',
            'cargo' => 'No se puede desactivar: el cargo está asignado a personas del sistema.',
            'rol' => 'No se puede desactivar: el rol está asignado a usuarios del sistema.',
            default => 'No se puede desactivar: está en uso.',
        };
    }
}
