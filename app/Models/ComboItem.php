<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['combo_id', 'presentacion_id', 'cantidad'];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }
}
