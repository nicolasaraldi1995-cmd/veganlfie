<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentacion_id' => ['required', 'integer', 'exists:presentaciones,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ];
    }
}
