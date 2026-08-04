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
        // Windows guarda los JPEG como .jfif y los celulares sacan .avif.
        // Sin estas dos el navegador recibía octet-stream y no mostraba nada.
        'jfif' => 'image/jpeg',
        'avif' => 'image/avif',
    ];

    /**
     * Las únicas carpetas del disco que son de cara al público: todas de
     * imágenes. Se listan a mano y no al revés (prohibir "imports") para que
     * una carpeta interna nueva nazca cerrada, no abierta.
     */
    private const CARPETAS_PUBLICAS = [
        'banners',
        'categorias',
        'combos',
        'marcas',
        'presentaciones',
        'productos',
    ];

    /**
     * Sirve los archivos del disco configurado (S3 u otro) a través del
     * propio dominio de la app, en vez de que el navegador pida el dominio
     * público del bucket directamente -- ese dominio devuelve 503 en el
     * embed cruzado de <img> aunque el archivo esté perfecto (confirmado con
     * curl y con navegación directa, ambos siempre 200).
     *
     * En el mismo disco vive "imports", que es lo que sube el Importador: la
     * lista del proveedor con los costos. Sin el filtro de abajo, se bajaba
     * entera desde /media/imports/... sin estar siquiera logueado.
     */
    public function show(string $path): Response
    {
        abort_unless($this->esPublico($path), 404);

        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        abort_unless($disk->exists($path), 404);

        $contenido = $disk->get($path) ?? '';

        return response($contenido)
            ->header('Content-Type', $this->tipoDe($path, $contenido))
            ->header('Cache-Control', 'public, max-age=604800')
            // Un SVG es texto y puede traer <script> adentro; servido desde
            // este dominio, ese script corre con la sesión de quien abra la
            // imagen. Los 63 iconos de categoría son SVG y tienen que seguir
            // dibujándose, así que en vez de dejar de servirlos se les corta la
            // capacidad de ejecutar: sin scripts, sin pedidos a la red, sin
            // formularios. Vale para todo /media, que son todas imágenes.
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    /**
     * La ruta tiene que empezar por una carpeta de la lista y no salirse de
     * ella. Se rechaza cualquier ".." antes de mirar el disco: la ruta viene
     * de un comodín de la URL, así que la arma quien pide.
     */
    private function esPublico(string $path): bool
    {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        $carpeta = strtok($path, '/');

        return $carpeta !== false
            && in_array($carpeta, self::CARPETAS_PUBLICAS, true)
            && str_contains($path, '/');
    }

    /**
     * Manda el contenido, no el nombre: el servidor reprocesa las imágenes de
     * banners y marcas y las deja en JPEG sin renombrar el archivo, así que un
     * ".png" puede tener un JPEG adentro. La extensión queda de respaldo para
     * lo que finfo no reconoce, como los SVG.
     */
    private function tipoDe(string $path, string $contenido): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'svg') {
            return self::TIPOS['svg'];
        }

        $detectado = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);

        if (is_string($detectado) && str_starts_with($detectado, 'image/')) {
            return $detectado;
        }

        return self::TIPOS[$extension] ?? 'application/octet-stream';
    }
}
