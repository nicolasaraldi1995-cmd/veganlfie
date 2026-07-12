<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entrega' => ['required', 'in:retiro,envio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
