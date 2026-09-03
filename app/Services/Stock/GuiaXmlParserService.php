<?php

namespace App\Services\Stock;

use App\Models\Programas\MaterialProgramaPL;
use App\Models\Proveedores\Proveedor;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

class GuiaXmlParserService
{
    /**
     * @return array{
     *     ok: bool,
     *     guia: string|null,
     *     lineas: list<array{
     *         id_material_pl: int,
     *         codigo_pl: string,
     *         nom_material: string,
     *         cantidad: float,
     *         codigo_proveedor: string|null,
     *         nom_proveedor: string|null
     *     }>,
     *     advertencias: list<string>,
     *     error?: string
     * }
     */
    public function parse(UploadedFile $archivo): array
    {
        $xpath = $this->cargarXPath($archivo);
        $guia = $this->extraerGuia($xpath);
        $nomProveedorXml = $this->extraerNombreProveedor($xpath);
        $lineasXml = $this->extraerLineas($xpath);

        if ($lineasXml === []) {
            return [
                'ok' => false,
                'guia' => $guia,
                'lineas' => [],
                'advertencias' => [],
                'error' => 'No se encontraron ítems en el XML. Verifique que sea una guía electrónica (DespatchAdvice).',
            ];
        }

        $lineas = [];
        $advertencias = [];

        foreach ($lineasXml as $item) {
            $material = $this->resolverMaterial(
                $item['codigo_proveedor'],
                $nomProveedorXml,
                $item['descripcion'] ?? null
            );

            if ($material === null) {
                $advertencias[] = 'Código proveedor «'.$item['codigo_proveedor'].'» sin relación en catálogo Proveedor.';

                continue;
            }

            if ($material['match_tipo'] === 'descripcion') {
                $advertencias[] = 'Código «'.$item['codigo_proveedor'].'» mapeado por descripción a «'
                    .$material['codigo_pl'].'» ('.$material['nom_material'].').';
            }

            $lineas[] = [
                'id_material_pl' => $material['id_material_pl'],
                'codigo_pl' => $material['codigo_pl'],
                'nom_material' => $material['nom_material'],
                'cantidad' => $item['cantidad'],
                'codigo_proveedor' => $item['codigo_proveedor'],
                'nom_proveedor' => $material['nom_proveedor'],
            ];
        }

        if ($lineas === []) {
            return [
                'ok' => false,
                'guia' => $guia,
                'lineas' => [],
                'advertencias' => $advertencias,
                'error' => 'Ningún código del XML coincide con la tabla Proveedor → Material PL.',
            ];
        }

        return [
            'ok' => true,
            'guia' => $guia,
            'lineas' => $lineas,
            'advertencias' => $advertencias,
        ];
    }

    private function cargarXPath(UploadedFile $archivo): DOMXPath
    {
        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false || trim($contenido) === '') {
            throw new RuntimeException('El archivo XML está vacío.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (@$dom->loadXML($contenido) === false) {
            throw new RuntimeException('El archivo XML no es válido.');
        }

        return new DOMXPath($dom);
    }

    private function extraerGuia(DOMXPath $xpath): ?string
    {
        $nodos = $xpath->query("//*[local-name()='DespatchAdvice']/*[local-name()='ID']");
        if ($nodos !== false && $nodos->length > 0) {
            return $this->formatearGuia(trim($nodos->item(0)?->textContent ?? ''));
        }

        $nodos = $xpath->query("//*[local-name()='ID'][1]");
        if ($nodos !== false && $nodos->length > 0) {
            return $this->formatearGuia(trim($nodos->item(0)?->textContent ?? ''));
        }

        return null;
    }

    private function formatearGuia(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^([A-Z]\d{3})-(\d+)$/u', $valor, $coincidencia)) {
            return $coincidencia[1].' N° '.str_pad($coincidencia[2], 7, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^([A-Z]\d{3})\s*N[°ºo\.]?\s*0*(\d+)$/ui', $valor, $coincidencia)) {
            return strtoupper($coincidencia[1]).' N° '.str_pad($coincidencia[2], 7, '0', STR_PAD_LEFT);
        }

        return $valor;
    }

    private function extraerNombreProveedor(DOMXPath $xpath): ?string
    {
        $consultas = [
            "//*[local-name()='DespatchSupplierParty']//*[local-name()='RegistrationName']",
            "//*[local-name()='SellerSupplierParty']//*[local-name()='RegistrationName']",
            "//*[local-name()='AccountingSupplierParty']//*[local-name()='RegistrationName']",
        ];

        foreach ($consultas as $consulta) {
            $nodos = $xpath->query($consulta);
            if ($nodos !== false && $nodos->length > 0) {
                $nombre = trim($nodos->item(0)?->textContent ?? '');
                if ($nombre !== '') {
                    return $nombre;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{codigo_proveedor: string, cantidad: float, descripcion: string|null}>
     */
    private function extraerLineas(DOMXPath $xpath): array
    {
        $lineas = [];
        $nodos = $xpath->query("//*[local-name()='DespatchLine']");

        if ($nodos === false || $nodos->length === 0) {
            $nodos = $xpath->query("//*[local-name()='InvoiceLine']");
        }

        if ($nodos === false) {
            return [];
        }

        foreach ($nodos as $nodo) {
            $codigo = $this->textoRelativo($xpath, $nodo, [
                ".//*[local-name()='SellersItemIdentification']/*[local-name()='ID']",
                ".//*[local-name()='StandardItemIdentification']/*[local-name()='ID']",
                ".//*[local-name()='Item']/*[local-name()='ID']",
            ]);

            $cantidadTexto = $this->textoRelativo($xpath, $nodo, [
                ".//*[local-name()='DeliveredQuantity']",
                ".//*[local-name()='InvoicedQuantity']",
                ".//*[local-name()='Quantity']",
            ]);

            if ($codigo === null || $cantidadTexto === null) {
                continue;
            }

            $cantidad = $this->normalizarNumero($cantidadTexto);
            if ($cantidad === null || $cantidad <= 0) {
                continue;
            }

            $descripcion = $this->textoRelativo($xpath, $nodo, [
                ".//*[local-name()='Item']/*[local-name()='Description']",
                ".//*[local-name()='Description']",
            ]);

            $lineas[] = [
                'codigo_proveedor' => $codigo,
                'cantidad' => $cantidad,
                'descripcion' => $descripcion,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<string>  $consultas
     */
    private function textoRelativo(DOMXPath $xpath, \DOMNode $contexto, array $consultas): ?string
    {
        foreach ($consultas as $consulta) {
            $nodos = $xpath->query($consulta, $contexto);
            if ($nodos !== false && $nodos->length > 0) {
                $texto = trim($nodos->item(0)?->textContent ?? '');
                if ($texto !== '') {
                    return $texto;
                }
            }
        }

        return null;
    }

    /**
     * @return array{
     *     id_material_pl: int,
     *     codigo_pl: string,
     *     nom_material: string,
     *     nom_proveedor: string|null,
     *     match_tipo: 'codigo'|'descripcion'
     * }|null
     */
    private function resolverMaterial(string $codigoProveedor, ?string $nomProveedorXml, ?string $descripcion): ?array
    {
        $codigoProveedor = trim($codigoProveedor);
        if ($codigoProveedor === '') {
            return null;
        }

        $proveedores = Proveedor::query()
            ->where('estado', 1)
            ->whereNotNull('idMaterialPL')
            ->where('codigo', $codigoProveedor)
            ->with('materialPl')
            ->get();

        if ($proveedores->isEmpty()) {
            $proveedores = Proveedor::query()
                ->where('estado', 1)
                ->whereNotNull('idMaterialPL')
                ->whereRaw('TRIM(codigo) = ?', [$codigoProveedor])
                ->with('materialPl')
                ->get();
        }

        $proveedor = $this->elegirProveedor($proveedores, $nomProveedorXml);
        if ($proveedor !== null && $proveedor->materialPl) {
            $nomMaterial = trim((string) $proveedor->nomMaterial) !== ''
                ? (string) $proveedor->nomMaterial
                : (string) $proveedor->materialPl->nomMaterial;

            return [
                'id_material_pl' => (int) $proveedor->idMaterialPL,
                'codigo_pl' => (string) $proveedor->materialPl->CodigoPL,
                'nom_material' => $nomMaterial,
                'nom_proveedor' => $proveedor->nomProveedor,
                'match_tipo' => 'codigo',
            ];
        }

        $material = MaterialProgramaPL::query()
            ->activo()
            ->where('CodigoPL', $codigoProveedor)
            ->first();

        if ($material) {
            return [
                'id_material_pl' => (int) $material->idMaterialPL,
                'codigo_pl' => (string) $material->CodigoPL,
                'nom_material' => (string) $material->nomMaterial,
                'nom_proveedor' => null,
                'match_tipo' => 'codigo',
            ];
        }

        if ($descripcion !== null && trim($descripcion) !== '') {
            return $this->resolverPorDescripcion($descripcion, $nomProveedorXml);
        }

        return null;
    }

    /**
     * @return array{
     *     id_material_pl: int,
     *     codigo_pl: string,
     *     nom_material: string,
     *     nom_proveedor: string|null,
     *     match_tipo: 'codigo'|'descripcion'
     * }|null
     */
    private function resolverPorDescripcion(string $descripcion, ?string $nomProveedorXml): ?array
    {
        $descripcionNorm = $this->normalizarTextoMatch($descripcion);
        if ($descripcionNorm === '') {
            return null;
        }

        $consulta = Proveedor::query()
            ->where('estado', 1)
            ->whereNotNull('idMaterialPL')
            ->with('materialPl');

        if ($nomProveedorXml !== null && str_contains(mb_strtoupper($nomProveedorXml), 'SAFCO')) {
            $consulta->whereRaw('UPPER(TRIM(nomProveedor)) = ?', ['SAFCO']);
        }

        $mejorProveedor = null;
        $mejorPuntaje = 0.0;

        foreach ($consulta->get() as $proveedor) {
            if (! $proveedor->materialPl) {
                continue;
            }

            $candidatos = array_filter([
                $proveedor->nomMaterial,
                $proveedor->materialPl->nomMaterial,
            ]);

            foreach ($candidatos as $texto) {
                $textoNorm = $this->normalizarTextoMatch((string) $texto);
                if (! $this->descripcionesCompatibles($descripcionNorm, $textoNorm)) {
                    continue;
                }

                $puntaje = $this->puntajeSimilitud($descripcionNorm, $textoNorm);
                if ($puntaje > $mejorPuntaje) {
                    $mejorPuntaje = $puntaje;
                    $mejorProveedor = $proveedor;
                }
            }
        }

        if ($mejorProveedor === null || $mejorPuntaje < 70) {
            return null;
        }

        return [
            'id_material_pl' => (int) $mejorProveedor->idMaterialPL,
            'codigo_pl' => (string) $mejorProveedor->materialPl->CodigoPL,
            'nom_material' => (string) $mejorProveedor->materialPl->nomMaterial,
            'nom_proveedor' => $mejorProveedor->nomProveedor,
            'match_tipo' => 'descripcion',
        ];
    }

    private function normalizarTextoMatch(string $texto): string
    {
        $texto = mb_strtoupper($texto);
        $texto = str_replace(['"', "'", '°', 'º'], ' ', $texto);
        $texto = preg_replace('/[^A-Z0-9\s]/u', ' ', $texto) ?? $texto;

        return trim(preg_replace('/\s+/u', ' ', $texto));
    }

    private function descripcionesCompatibles(string $descripcionXml, string $descripcionCatalogo): bool
    {
        $tokensXml = $this->tokensDistintivos($descripcionXml);
        if ($tokensXml === []) {
            return true;
        }

        foreach ($tokensXml as $token) {
            if (! str_contains($descripcionCatalogo, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function tokensDistintivos(string $textoNorm): array
    {
        $tokens = preg_split('/\s+/u', $textoNorm) ?: [];
        $distintivos = [];

        foreach ($tokens as $token) {
            if (! $this->esTokenDistintivo($token)) {
                continue;
            }

            $distintivos[] = $token;
        }

        return array_values(array_unique($distintivos));
    }

    private function esTokenDistintivo(string $token): bool
    {
        static $stopWords = [
            'ETIQUETA', 'ETQUETA', 'CLAMSHELL', 'CLMASHELL', 'ADHESIVA', 'PREMIUM', 'BANDEJA',
            'CARTON', 'MADERA', 'CAJA', 'TALSA', 'BLANCO', 'KRAFT', 'NEGRA', 'GENERICA', 'LOGO',
            'ESTANDAR', 'METAL', 'CALIDAD', 'EXPORTACION', 'UVA', 'PARA', 'MOD', 'PPB', 'PPM', 'DFI',
        ];

        if ($token === '' || in_array($token, $stopWords, true)) {
            return false;
        }

        if (preg_match('/^\d{3,}$/', $token) === 1) {
            return true;
        }

        return preg_match('/^[A-Z]{4,}$/', $token) === 1;
    }

    private function puntajeSimilitud(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $porcentaje);

        return (float) $porcentaje;
    }

    /**
     * @param  Collection<int, Proveedor>  $proveedores
     */
    private function elegirProveedor(Collection $proveedores, ?string $nomProveedorXml): ?Proveedor
    {
        if ($proveedores->isEmpty()) {
            return null;
        }

        if ($proveedores->count() === 1) {
            return $proveedores->first();
        }

        if ($nomProveedorXml !== null && str_contains(mb_strtoupper($nomProveedorXml), 'SAFCO')) {
            $safco = $proveedores->first(function (Proveedor $proveedor) {
                return mb_strtoupper(trim((string) $proveedor->nomProveedor)) === 'SAFCO';
            });

            if ($safco) {
                return $safco;
            }
        }

        if ($nomProveedorXml) {
            $xml = mb_strtoupper($nomProveedorXml);
            $porNombre = $proveedores->first(function (Proveedor $proveedor) use ($xml) {
                $nombre = mb_strtoupper((string) $proveedor->nomProveedor);

                return str_contains($xml, $nombre) || str_contains($nombre, $xml);
            });

            if ($porNombre) {
                return $porNombre;
            }
        }

        return $proveedores->first();
    }

    private function normalizarNumero(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            if (strrpos($valor, ',') > strrpos($valor, '.')) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }
}
