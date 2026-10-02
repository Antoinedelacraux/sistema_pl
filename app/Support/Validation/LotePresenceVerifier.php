<?php

namespace App\Support\Validation;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Illuminate\Validation\PresenceVerifierInterface;

/**
 * Verificador de presencia que resuelve en lote las reglas `exists:{tabla},{columna}` de un array
 * (p. ej. lineas.*.id_material_pl): la primera consulta trae con un solo whereIn todos los ids de la
 * petición y las siguientes se responden desde memoria. La regla `exists` sigue siendo la de Laravel,
 * así que el mensaje, el orden de los errores y las reglas fallidas no cambian.
 *
 * Cualquier otra consulta (otra tabla/columna, condiciones extra, conexión explícita o valores no enteros)
 * se delega en el verificador original.
 */
class LotePresenceVerifier implements DatabasePresenceVerifierInterface
{
    /** @var array<int, true>|null */
    private ?array $existentes = null;

    private ?string $connection = null;

    /**
     * @param  Closure(): iterable<mixed>  $valores  Valores de la petición a verificar en lote.
     */
    public function __construct(
        private readonly PresenceVerifierInterface $original,
        private readonly string $tabla,
        private readonly string $columna,
        private readonly Closure $valores
    ) {}

    public function setConnection($connection)
    {
        $this->connection = $connection;

        if ($this->original instanceof DatabasePresenceVerifierInterface) {
            $this->original->setConnection($connection);
        }
    }

    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        $id = $this->enteroSeguro($value);

        if ($collection !== $this->tabla
            || $column !== $this->columna
            || $excludeId !== null
            || $extra !== []
            || $this->connection !== null
            || $id === null) {
            return $this->original->getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
        }

        return isset($this->existentes()[$id]) ? 1 : 0;
    }

    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        return $this->original->getMultiCount($collection, $column, $values, $extra);
    }

    /**
     * @return array<int, true>
     */
    private function existentes(): array
    {
        if ($this->existentes !== null) {
            return $this->existentes;
        }

        $ids = [];
        foreach (($this->valores)() as $valor) {
            $id = $this->enteroSeguro($valor);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        $this->existentes = [];
        foreach (array_chunk(array_values($ids), 1000) as $bloque) {
            DB::table($this->tabla)
                ->whereIn($this->columna, $bloque)
                ->pluck($this->columna)
                ->each(function ($id) {
                    $this->existentes[(int) $id] = true;
                });
        }

        return $this->existentes;
    }

    /**
     * Solo enteros "limpios" (int o dígitos con signo opcional); el resto va al verificador original.
     */
    private function enteroSeguro(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?[0-9]{1,18}$/', $valor) === 1) {
            return (int) $valor;
        }

        return null;
    }
}
