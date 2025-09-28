<?php
namespace App\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BuildingsDTO
{
    public static function validate(array $data, $scenario)
    {
        $rules = [
            'store' => [
                'nombre' => 'required|string|max:255',
                'direccion' => 'required|string|max:500',
                'usuario_id' => 'required|exists:users,id',
            ],
            'update' => [
                'nombre' => 'sometimes|string|max:255',
                'direccion' => 'sometimes|string|max:500',
            ]
        ];

        $validator = Validator::make($data, $rules[$scenario]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
