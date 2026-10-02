<?php

namespace App\Services\Programas;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProgramaImagenService
{
    private const MAX_WIDTH = 1280;

    private const WEBP_QUALITY = 82;

    private const JPEG_QUALITY = 85;

    /**
     * MIME real (detectado por contenido) => extensión permitida en disco.
     * Nunca se usa la extensión que envía el cliente.
     */
    private const MIME_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Tipos IMAGETYPE_* aceptados al verificar la cabecera binaria con getimagesize().
     */
    private const IMAGETYPES_PERMITIDOS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    public function guardar(?UploadedFile $archivo, int $idProgramaFila): ?string
    {
        if (! $archivo) {
            return null;
        }

        $extensionSegura = $this->extensionSegura($archivo);

        if ($extensionSegura === null) {
            Log::warning('ProgramaImagenService: archivo rechazado (MIME real no permitido o no es imagen válida).', [
                'idProgramaFila' => $idProgramaFila,
                'mime' => $archivo->getMimeType(),
                'size' => $archivo->getSize(),
            ]);

            return null;
        }

        $this->eliminarVariantes($idProgramaFila);

        $directorio = 'programas/receta';
        Storage::disk('public')->makeDirectory($directorio);

        if (extension_loaded('gd')) {
            $rutaWebp = $directorio.'/'.$idProgramaFila.'.webp';

            if ($this->procesarYGuardar($archivo, Storage::disk('public')->path($rutaWebp), 'webp')) {
                return $rutaWebp;
            }

            $rutaJpg = $directorio.'/'.$idProgramaFila.'.jpg';

            if ($this->procesarYGuardar($archivo, Storage::disk('public')->path($rutaJpg), 'jpeg')) {
                return $rutaJpg;
            }

            Log::warning('ProgramaImagenService: GD disponible pero no se pudo comprimir la imagen.', [
                'idProgramaFila' => $idProgramaFila,
                'mime' => $archivo->getMimeType(),
                'size' => $archivo->getSize(),
            ]);
        } else {
            Log::warning('ProgramaImagenService: extensión GD no disponible; se guarda el archivo original.', [
                'idProgramaFila' => $idProgramaFila,
                'php' => PHP_VERSION,
                'ini' => php_ini_loaded_file(),
            ]);
        }

        // Fallback sin compresión: extensión derivada del MIME real (lista blanca), nunca del nombre del cliente.
        $nombre = $idProgramaFila.'.'.$extensionSegura;

        return $archivo->storeAs($directorio, $nombre, 'public') ?: null;
    }

    public function reemplazar(?UploadedFile $archivo, int $idProgramaFila, ?string $rutaAnterior): ?string
    {
        if (! $archivo) {
            return $rutaAnterior;
        }

        // Si el archivo nuevo no es una imagen permitida, se conserva la imagen anterior.
        if ($this->extensionSegura($archivo) === null) {
            Log::warning('ProgramaImagenService: reemplazo rechazado; se conserva la imagen anterior.', [
                'idProgramaFila' => $idProgramaFila,
                'mime' => $archivo->getMimeType(),
            ]);

            return $rutaAnterior;
        }

        $this->eliminar($rutaAnterior);
        $this->eliminarVariantes($idProgramaFila);

        return $this->guardar($archivo, $idProgramaFila);
    }

    public function eliminar(?string $ruta): void
    {
        if ($ruta !== null && $ruta !== '' && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }

    public function eliminarVariantes(int $idProgramaFila): void
    {
        $directorio = 'programas/receta';

        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $extension) {
            $this->eliminar($directorio.'/'.$idProgramaFila.'.'.$extension);
        }
    }

    public function url(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        return Storage::disk('public')->url($ruta);
    }

    /**
     * Devuelve la extensión segura (jpg/png/webp/gif) si el contenido real del archivo es una imagen permitida.
     * Exige que coincidan el MIME detectado por contenido (finfo) y la firma binaria (getimagesize).
     */
    private function extensionSegura(UploadedFile $archivo): ?string
    {
        if (! $archivo->isValid()) {
            return null;
        }

        $ruta = $archivo->getRealPath();

        if ($ruta === false || ! is_file($ruta)) {
            return null;
        }

        $mime = strtolower((string) $archivo->getMimeType());
        $porMime = self::MIME_PERMITIDOS[$mime] ?? null;

        if ($porMime === null) {
            return null;
        }

        $info = @getimagesize($ruta);

        if ($info === false || ! isset($info[2]) || ($info[0] ?? 0) <= 0 || ($info[1] ?? 0) <= 0) {
            return null;
        }

        $porFirma = self::IMAGETYPES_PERMITIDOS[$info[2]] ?? null;

        return $porFirma !== null && $porFirma === $porMime ? $porFirma : null;
    }

    private function procesarYGuardar(UploadedFile $archivo, string $destino, string $formato): bool
    {
        $origen = $this->abrirImagen($archivo);

        if ($origen === null) {
            return false;
        }

        $imagen = $this->redimensionar($origen);

        if ($imagen !== $origen) {
            imagedestroy($origen);
        }

        if ($formato === 'jpeg') {
            $this->aplanarFondo($imagen);
        } else {
            $this->preservarTransparencia($imagen);
        }

        $directorio = dirname($destino);

        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $ok = match ($formato) {
            'webp' => function_exists('imagewebp') && imagewebp($imagen, $destino, self::WEBP_QUALITY),
            'jpeg' => imagejpeg($imagen, $destino, self::JPEG_QUALITY),
            default => false,
        };

        imagedestroy($imagen);

        return $ok && is_file($destino);
    }

    private function abrirImagen(UploadedFile $archivo): ?\GdImage
    {
        $ruta = $archivo->getRealPath();

        if ($ruta === false) {
            return null;
        }

        $contenido = @file_get_contents($ruta);

        if ($contenido === false) {
            return null;
        }

        $imagen = @imagecreatefromstring($contenido);

        if ($imagen instanceof \GdImage) {
            return $imagen;
        }

        $imagen = match ($archivo->getMimeType()) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => @imagecreatefromjpeg($ruta),
            'image/png', 'image/x-png' => @imagecreatefrompng($ruta),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            'image/gif' => @imagecreatefromgif($ruta),
            default => false,
        };

        return $imagen instanceof \GdImage ? $imagen : null;
    }

    private function redimensionar(\GdImage $origen): \GdImage
    {
        $ancho = imagesx($origen);
        $alto = imagesy($origen);

        if ($ancho <= 0 || $alto <= 0 || $ancho <= self::MAX_WIDTH) {
            return $origen;
        }

        $nuevoAlto = max(1, (int) round($alto * (self::MAX_WIDTH / $ancho)));
        $redimensionada = imagescale($origen, self::MAX_WIDTH, $nuevoAlto);

        return $redimensionada !== false ? $redimensionada : $origen;
    }

    private function aplanarFondo(\GdImage $imagen): void
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $fondo = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($fondo, 255, 255, 255);

        imagefilledrectangle($fondo, 0, 0, $ancho, $alto, $blanco);
        imagecopy($fondo, $imagen, 0, 0, 0, 0, $ancho, $alto);
        imagecopy($imagen, $fondo, 0, 0, 0, 0, $ancho, $alto);
        imagedestroy($fondo);
    }

    private function preservarTransparencia(\GdImage $imagen): void
    {
        if (! function_exists('imagepalettetotruecolor')) {
            return;
        }

        imagepalettetotruecolor($imagen);
        imagealphablending($imagen, true);
        imagesavealpha($imagen, true);
    }
}
