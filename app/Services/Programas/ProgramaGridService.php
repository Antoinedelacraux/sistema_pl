<?php

namespace App\Services\Programas;

use App\Models\Programas\CantidadCaja;
use App\Models\Programas\Cliente;
use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\Mercado;
use App\Models\Programas\Presentacion;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\ProgramaFila;
use App\Models\Programas\Situado;
use App\Models\Programas\Uso;
use App\Models\Programas\Variedad;
use App\Support\GridPagination;
use App\Support\MaterialProgramaLookup;
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

    /** Programas distintos presentes en la grilla de receta (no el catálogo CRUD). */
    public function totalProgramasDistintos(array $filtros = []): int
    {
        return (int) $this->queryFilas($filtros)
            ->select('idPrograma')
            ->distinct()
            ->count('idPrograma');
    }

    public function catalogos(): array
    {
        return [
            'mercados' => Mercado::query()->activo()->orderBy('nomMercado')->get(),
            'clientes' => Cliente::query()->activo()->orderBy('nomCliente')->get(),
            'variedades' => Variedad::query()->activo()->orderBy('nomVariedad')->get(),
            'programas' => ProgramaCatalogo::query()->activo()->orderBy('nomPrograma')->get(),
            'presentaciones' => Presentacion::query()->activo()->orderBy('nomPresentacion')->get(),
            'usos' => Uso::query()->activo()->orderBy('nomUso')->get(),
            'situados' => Situado::query()->activo()->orderBy('nomSituado')->get(),
            'familias' => Familia::query()->activo()->orderBy('nomFamilia')->get(),
        ];
    }

    public function materialPorCodigo(string $codigo): ?MaterialProgramaPL
    {
        return MaterialProgramaLookup::porCodigo($codigo, soloActivos: false);
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
                'material', 'cantidadCaja', 'uso', 'situado', 'familia',
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

        if (! empty($filtros['programa'])) {
            $query->where('idPrograma', (int) $filtros['programa']);
        }

        if (! empty($filtros['presentacion'])) {
            $query->where('idPresentacion', (int) $filtros['presentacion']);
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
