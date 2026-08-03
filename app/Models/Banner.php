<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use App\Observers\BannerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(BannerObserver::class)]
class Banner extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = ['imagen', 'ancho', 'alto', 'posicion', 'ajuste', 'destino_tipo', 'destino_valor', 'orden', 'activo'];

    /**
     * Cómo entra la imagen en la franja del banner.
     */
    public const AJUSTES = [
        'cover' => 'Recortar para llenar el banner',
        'contain' => 'Mostrar la imagen completa (sin recortar)',
    ];

    /**
     * Qué parte de la imagen se conserva cuando se recorta para llenar la
     * franja del banner (valores de object-position de CSS).
     */
    public const POSICIONES = [
        'center' => 'Centro',
        'top' => 'Arriba',
        'bottom' => 'Abajo',
        'left' => 'Izquierda',
        'right' => 'Derecha',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    public function getUrlAttribute(): ?string
    {
        return match ($this->destino_tipo) {
            'marca' => $this->destino_valor ? route('productos.index', ['vista' => 'marcas', 'marca' => $this->destino_valor]) : null,
            'categoria' => $this->destino_valor ? route('productos.index', ['vista' => 'categorias', 'categoria' => $this->destino_valor]) : null,
            'seccion' => $this->destino_valor ? route('productos.index', ['vista' => $this->destino_valor]) : null,
            'url' => $this->destino_valor ?: null,
            default => null,
        };
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->imagen);
    }
}
