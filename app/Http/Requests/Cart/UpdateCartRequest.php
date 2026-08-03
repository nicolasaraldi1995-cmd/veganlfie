<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentacion_id' => ['required', 'integer', 'exists:presentaciones,id'],
            // 0 borra el ítem; el tope superior evita pedidos absurdos cuando el
            // control de stock está apagado.
            'cantidad' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
