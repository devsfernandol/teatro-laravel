<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Las imágenes subidas se vuelven a codificar como WebP. Así se descartan los
 * metadatos (EXIF, ubicación) y cualquier contenido incrustado que no sea la
 * imagen en sí (archivos "políglotas").
 */
final class Imagenes
{
    private const ANCHO_MAXIMO = 1200;

    public static function guardarPortada(UploadedFile $archivo): string
    {
        $contenido = file_get_contents($archivo->getRealPath());
        $origen = $contenido === false ? false : @imagecreatefromstring($contenido);
        if ($origen === false) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        if ($ancho > self::ANCHO_MAXIMO) {
            $nuevoAlto = (int) round($alto * self::ANCHO_MAXIMO / $ancho);
            $redimensionada = imagescale($origen, self::ANCHO_MAXIMO, $nuevoAlto, IMG_BICUBIC);
            imagedestroy($origen);
            if ($redimensionada === false) {
                throw new RuntimeException('No se pudo redimensionar la imagen.');
            }
            $origen = $redimensionada;
        }

        ob_start();
        $ok = imagewebp($origen, null, 82);
        $webp = ob_get_clean();
        imagedestroy($origen);

        if (! $ok || $webp === false || $webp === '') {
            throw new RuntimeException('No se pudo convertir la imagen.');
        }

        $nombre = Str::random(40).'.webp';
        Storage::disk('public')->put('obras/'.$nombre, $webp);

        return $nombre;
    }

    public static function borrarPortada(?string $nombre): void
    {
        if ($nombre !== null && preg_match('/^[A-Za-z0-9]{40}\.webp$/', $nombre) === 1) {
            Storage::disk('public')->delete('obras/'.$nombre);
        }
    }
}
