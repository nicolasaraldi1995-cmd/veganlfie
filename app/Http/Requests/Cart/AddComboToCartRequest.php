<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddComboToCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'combo_id' => ['required', 'integer', 'exists:combos,id'],
        ];
    }
}
