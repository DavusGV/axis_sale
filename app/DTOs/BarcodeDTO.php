<?php

namespace App\DTOs;

use Illuminate\Support\Facades\Validator;

class BarcodeDTO
{
    public static function validate(array $data): array
    {
        return Validator::make($data, [
            'modo'           => 'required|in:todos,especifico',
            'producto_id'    => 'nullable|required_if:modo,especifico|integer',
            'tipo_cantidad'  => 'required|in:unica,personalizada,stock',
            'cantidad'       => 'required_if:tipo_cantidad,personalizada|integer|min:1',
            'incluir_precio' => 'sometimes|boolean',
        ], [
            'modo.required'           => 'Debes indicar el modo de generación.',
            'modo.in'                 => 'El modo seleccionado no es válido.',
            'producto_id.required_if' => 'Debes seleccionar un producto.',
            'tipo_cantidad.required'  => 'Debes indicar el tipo de cantidad.',
            'tipo_cantidad.in'        => 'El tipo de cantidad no es válido.',
            'cantidad.required_if'    => 'Debes indicar la cantidad de etiquetas.',
            'cantidad.min'            => 'La cantidad debe ser al menos 1.',
        ])->validate();
    }
}