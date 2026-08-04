<?php

/**
 * Los mensajes de validación salían con la clave cruda: el cliente veía
 * "validation.max.numeric" en la pantalla de finalizar la compra. Están sólo
 * las reglas que el sitio usa de verdad, en el tono de la casa.
 */
return [
    'required' => 'Falta completar :attribute.',
    'email' => 'Ese correo no parece estar bien escrito.',
    'unique' => 'Ya hay una cuenta con ese :attribute.',
    'confirmed' => 'Las dos contraseñas no coinciden.',
    'current_password' => 'La contraseña actual no es la correcta.',
    'exists' => 'No encontramos ese producto.',
    'integer' => 'La cantidad tiene que ser un número entero.',
    'numeric' => ':Attribute tiene que ser un número.',
    'boolean' => ':Attribute sólo puede ser sí o no.',
    'date' => ':Attribute no es una fecha válida.',
    'in' => 'Esa opción no está entre las posibles.',
    'lt' => [
        'numeric' => ':Attribute tiene que ser menor que :value.',
    ],
    'min' => [
        'numeric' => 'La cantidad mínima es :min.',
        'string' => ':Attribute tiene que tener al menos :min caracteres.',
        'array' => 'Elegí al menos :min.',
    ],
    'max' => [
        'numeric' => 'No se pueden pedir más de :max unidades de una vez.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
        'file' => 'El archivo no puede pesar más de :max kilobytes.',
    ],
    'mimes' => 'El archivo tiene que ser de tipo: :values.',
    'mimetypes' => 'Ese tipo de archivo no se acepta acá.',
    'image' => 'El archivo tiene que ser una imagen.',
    'password' => [
        'letters' => 'La contraseña tiene que tener al menos una letra.',
        'mixed' => 'La contraseña tiene que combinar mayúsculas y minúsculas.',
        'numbers' => 'La contraseña tiene que tener al menos un número.',
        'symbols' => 'La contraseña tiene que tener al menos un símbolo.',
        'uncompromised' => 'Esa contraseña apareció en una filtración. Elegí otra.',
    ],

    'custom' => [
        'password' => [
            'min' => ['string' => 'La contraseña tiene que tener al menos :min caracteres.'],
        ],
    ],

    /**
     * Para que los mensajes digan "Falta completar el correo" y no
     * "Falta completar email".
     */
    'attributes' => [
        'name' => 'el nombre',
        'nombre' => 'el nombre',
        'email' => 'el correo',
        'password' => 'la contraseña',
        'celular' => 'el celular',
        'direccion' => 'la dirección',
        'ciudad' => 'la ciudad',
        'provincia' => 'la provincia',
        'negocio' => 'el negocio',
        'cantidad' => 'la cantidad',
        'presentacion_id' => 'el producto',
        'entrega' => 'la forma de entrega',
        'notas' => 'las notas',
        'monto' => 'el monto',
        'metodo' => 'el método de pago',
        'fecha' => 'la fecha',
        'precio' => 'el precio',
        'archivo' => 'el archivo',
        'cliente_id' => 'el cliente',
    ],
];
