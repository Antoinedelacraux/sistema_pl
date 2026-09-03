<?php

namespace App\Services\Integrations;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PowerAutomateService
{
    public function isConfigured(): bool
    {
        if (! config('power_automate.enabled')) {
            return false;
        }

        return filled(config('power_automate.upload_url'));
    }

    /**
     * @return array{id: string, webUrl: string, name: string}
     */
    public function uploadMovimientoPdf(UploadedFile $archivo, string $tipo, ?string $facturaGuia = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Power Automate no está configurado. Revise POWER_AUTOMATE_* en el archivo .env.'
            );
        }

        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo PDF.');
        }

        $nombre = $this->nombreArchivo($archivo, $tipo, $facturaGuia);

        $payload = [
            'factura_guia' => $facturaGuia ?? '',
            'tipo' => $tipo,
            'nombre_archivo' => $nombre,
            'contenido_base64' => base64_encode($contenido),
        ];

        $request = Http::timeout((int) config('power_automate.timeout_seconds', 120))
            ->acceptJson()
            ->asJson();

        $request = $this->applySslOptions($request);

        $apiKey = config('power_automate.api_key');
        if (filled($apiKey)) {
            $request = $request->withHeaders(['X-Api-Key' => $apiKey]);
        }

        $response = $request->post((string) config('power_automate.upload_url'), $payload);

        if (! $response->successful()) {
            $detalle = trim((string) $response->body());
            $mensaje = 'Power Automate no pudo guardar el PDF. Código: '.$response->status();

            if ($response->status() === 502) {
                $mensaje .= '. El flujo no respondió a tiempo o falló en un paso interno.'
                    .' Revise el historial de ejecuciones en Power Automate (pasos Crear archivo, Crear vínculo para compartir y Respuesta).';
            }

            if ($detalle !== '') {
                $json = json_decode($detalle, true);
                $errorMsg = is_array($json) ? ($json['error']['message'] ?? null) : null;
                $mensaje .= $errorMsg ? ' Detalle: '.$errorMsg : ' Detalle: '.Str::limit($detalle, 200);
            }

            throw new RuntimeException($mensaje);
        }

        return $this->mapRespuesta($response->json());
    }

    private function applySslOptions(\Illuminate\Http\Client\PendingRequest $request): \Illuminate\Http\Client\PendingRequest
    {
        if (! config('power_automate.verify_ssl')) {
            return $request->withoutVerifying();
        }

        $caBundle = (string) config('power_automate.ca_bundle');

        if ($caBundle !== '' && is_file($caBundle)) {
            return $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    private function nombreArchivo(UploadedFile $archivo, string $tipo, ?string $facturaGuia): string
    {
        $base = $facturaGuia
            ? Str::slug($facturaGuia, '_')
            : Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME), '_');

        if ($base === '') {
            $base = $tipo;
        }

        return $base.'_'.now()->format('Ymd_His').'.pdf';
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{id: string, webUrl: string, name: string}
     */
    private function mapRespuesta(?array $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('Power Automate devolvió una respuesta inválida.');
        }

        $id = $this->firstString($data, ['id', 'Id', 'fileId', 'FileId']);
        $name = $this->firstString($data, ['name', 'Name', 'nombre_archivo']);
        $webUrl = $this->resolveWebUrl($data);

        if ($webUrl === '') {
            throw new RuntimeException(
                'Power Automate respondió sin URL. Revise el paso Respuesta del flujo: '
                .'id y name deben venir de Crear archivo, y webUrl de Crear vínculo para compartir por ruta de acceso (URL web). '
                .'Si los tres campos salen vacíos, las referencias dinámicas del Cuerpo no están bien enlazadas.'
            );
        }

        return [
            'id' => $id !== '' ? $id : $name,
            'webUrl' => $webUrl,
            'name' => $name !== '' ? $name : basename(parse_url($webUrl, PHP_URL_PATH) ?: $webUrl),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveWebUrl(array $data): string
    {
        $direct = $this->firstString($data, ['webUrl', 'WebUrl', 'web_url', 'url', 'Url', 'link', 'Link']);

        if ($this->looksLikeHttpUrl($direct)) {
            return $direct;
        }

        foreach (['Link', 'link', 'sharingLink', 'SharingLink'] as $nestedKey) {
            if (! isset($data[$nestedKey]) || ! is_array($data[$nestedKey])) {
                continue;
            }

            $nested = $this->firstString($data[$nestedKey], ['WebUrl', 'webUrl', 'web_url', 'Url', 'url']);

            if ($this->looksLikeHttpUrl($nested)) {
                return $nested;
            }
        }

        return $this->findHttpUrlRecursive($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findHttpUrlRecursive(array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && $this->looksLikeHttpUrl($value)) {
                if (is_string($key) && preg_match('/weburl|web_url|url|link/i', $key)) {
                    return trim($value);
                }
            }

            if (is_array($value)) {
                $found = $this->findHttpUrlRecursive($value);

                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    private function looksLikeHttpUrl(string $value): bool
    {
        return $value !== '' && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'));
    }
}
