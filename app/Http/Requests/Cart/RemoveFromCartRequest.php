<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class RemoveFromCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentacion_id' => ['required', 'integer'],
        ];
    }
}
