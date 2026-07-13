<?php

namespace App\Models;

use App\Concerns\HasMediaUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use HasFactory, HasMediaUrl, SoftDeletes;

    protected $fillable = ['imagen', 'destino_tipo', 'destino_valor', 'orden', 'activo'];

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
