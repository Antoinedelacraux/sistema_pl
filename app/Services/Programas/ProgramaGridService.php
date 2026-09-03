<?php

namespace App\Services\Programas;

use App\Models\Programas\CantidadCaja;
use App\Models\Programas\Cliente;
use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\Mercado;
use App\Models\Programas\Presentacion;
use App\Models\Programas\PreNisira;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\Situado;
use App\Models\Programas\Uso;
use App\Models\Programas\Variedad;
use App\Support\GridPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgramaGridService
{
    public function __construct(
        private readonly ProgramaImagenService $imagenService
    ) {}

    public function filas(array $filtros = []): Collection
    {
        return $this->queryFilas($filtros)
            ->orderBy('nroFila')
            ->get();
    }

    public function filasPaginadas(array $filtros, Request $request): LengthAwarePaginator
    {
        return $this->queryFilas($filtros)
            ->orderBy('nroFila')
            ->paginate(GridPagination::PER_PAGE)
            ->withQueryString();
    }

    public function totalFilas(array $filtros = []): int
    {
        return $this->queryFilas($filtros)->count();
    }

    public function catalogos(): array
    {
        return [
            'mercados' => Mercado::query()->activo()->orderBy('idMercado')->get(),
            'clientes' => Cliente::query()->activo()->orderBy('idCliente')->get(),
            'variedades' => Variedad::query()->activo()->orderBy('idVariedad')->get(),
            'programas' => ProgramaCatalogo::query()->activo()->orderBy('idPrograma')->get(),
            'presentaciones' => Presentacion::query()->activo()->orderBy('idPresentacion')->get(),
            'preNisiras' => PreNisira::query()->activo()->orderBy('idPreNisira')->get(),
            'usos' => Uso::query()->activo()->orderBy('idUso')->get(),
            'situados' => Situado::query()->activo()->orderBy('idSituado')->get(),
            'familias' => Familia::query()->activo()->orderBy('idFamilia')->get(),
        ];
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        return MaterialProgramaPL::query()
            ->where('CodigoPL', $codigo)
            ->orderByDesc('idMaterialPL')
            ->first();
    }

    public function crearFila(array $datos, ?UploadedFile $imagen = null): ProgramaFila
    {
        return DB::transaction(function () use ($datos, $imagen) {
            $material = MaterialProgramaPL::query()->updateOrCreate(
                ['CodigoPL' => $datos['codigo_pl']],
                [
                    'nomMaterial' => $datos['nom_material'],
                    'fechaRegistro' => now()->toDateString(),
                    'Estado' => 1,
                ]
            );

            $cantidadValor = number_format((float) $datos['cantidad'], 2, '.', '');

            $cantidad = CantidadCaja::query()->firstOrCreate(
                ['cantidad' => $cantidadValor],
                ['Estado' => 1]
            );

            $nroFila = (int) ProgramaFila::query()->max('nroFila') + 1;

            $fila = ProgramaFila::query()->create([
                'nroFila' => $nroFila,
                'idMercado' => $datos['id_mercado'],
                'idCliente' => $datos['id_cliente'],
                'idVariedad' => $datos['id_variedad'],
                'idPrograma' => $datos['id_programa'],
                'idPresentacion' => $datos['id_presentacion'],
                'idPreNisira' => $datos['id_pre_nisira'],
                'idMaterialPL' => $material->idMaterialPL,
                'idCantidadCaja' => $cantidad->idCantidadCaja,
                'idUso' => $datos['id_uso'],
                'idSituado' => $datos['id_situado'],
                'idFamilia' => $datos['id_familia'],
                'Estado' => 1,
            ]);

            if ($imagen) {
                $ruta = $this->imagenService->guardar($imagen, $fila->idProgramaFila);

                if ($ruta) {
                    $fila->update(['imagen' => $ruta]);
                }
            }

            return $fila->fresh()->load([
                'mercado', 'cliente', 'variedad', 'programa', 'presentacion',
                'preNisira', 'material', 'cantidadCaja', 'uso', 'situado', 'familia',
            ]);
        });
    }

    public function actualizarImagen(int $idProgramaFila, ?UploadedFile $imagen, bool $eliminarImagen = false): ProgramaFila
    {
        return DB::transaction(function () use ($idProgramaFila, $imagen, $eliminarImagen) {
            $fila = ProgramaFila::query()->findOrFail($idProgramaFila);

            if ($eliminarImagen) {
                $this->imagenService->eliminarVariantes($idProgramaFila);
                $fila->update(['imagen' => null]);

                return $fila->fresh();
            }

            if ($imagen) {
                $ruta = $this->imagenService->reemplazar($imagen, $idProgramaFila, $fila->imagen);

                if ($ruta) {
                    $fila->update(['imagen' => $ruta]);
                }
            }

            return $fila->fresh();
        });
    }

    private function queryFilas(array $filtros): Builder
    {
        $query = ProgramaFila::query()->with([
            'mercado',
            'cliente',
            'variedad',
            'programa',
            'presentacion',
            'preNisira',
            'material',
            'cantidadCaja',
            'uso',
            'situado',
            'familia',
        ]);

        if (! empty($filtros['mercado'])) {
            $query->where('idMercado', (int) $filtros['mercado']);
        }

        if (! empty($filtros['cliente'])) {
            $query->where('idCliente', (int) $filtros['cliente']);
        }

        if (! empty($filtros['familia'])) {
            $query->where('idFamilia', (int) $filtros['familia']);
        }

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->whereHas('material', function (Builder $material) use ($termino) {
                $material->where('CodigoPL', 'like', '%'.$termino.'%')
                    ->orWhere('nomMaterial', 'like', '%'.$termino.'%');
            });
        }

        return $query;
    }
}
