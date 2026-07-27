<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    private const TIPOS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];

    /**
     * Sirve los archivos del disco configurado (S3 u otro) a través del
     * propio dominio de la app, en vez de que el navegador pida el dominio
     * público del bucket directamente -- ese dominio devuelve 503 en el
     * embed cruzado de <img> aunque el archivo esté perfecto (confirmado con
     * curl y con navegación directa, ambos siempre 200).
     */
    public function show(string $path): Response
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        abort_unless($disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response($disk->get($path))
            ->header('Content-Type', self::TIPOS[$extension] ?? 'application/octet-stream')
            ->header('Cache-Control', 'public, max-age=604800');
    }
}
