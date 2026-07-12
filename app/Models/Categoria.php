<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Categoria extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nombre', 'slug', 'imagen', 'orden', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Categoria $categoria) {
            if (empty($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
        });
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
