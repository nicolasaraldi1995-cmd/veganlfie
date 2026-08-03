<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentacion_id' => ['required', 'integer', 'exists:presentaciones,id'],
            // Con el control de stock apagado nada acotaba la cantidad: un tope
            // alto pero sensato evita pedidos absurdos por un cero de más.
            'cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
