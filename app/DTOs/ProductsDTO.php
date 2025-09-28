<?php
namespace App\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductsDTO
{
    public static function validate(array $data, $scenario)
    {
        $rules = [
            'store' => [
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                    // Regla única por establecimiento
                    // Asegúrate que 'establecimiento_id' esté en $data
                    function($attribute, $value, $fail) use ($data) {
                        $exists = \App\Models\Products::where('nombre', $value)
                            ->where('establecimiento_id', $data['establecimiento_id'] ?? 1)
                            ->exists();
                        if ($exists) {
                            $fail('Ya existe un producto con ese nombre en este establecimiento.');
                        }
                    }
                ],
                'codigo' => 'nullable|string|max:100|unique:productos,codigo',
                'descripcion' => 'nullable|string',
                'categoria_id' => 'required|exists:categorias,id',
                'precio_compra' => 'required|numeric|min:0',
                'precio_venta' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
            ],
            'update' => [
                'nombre' => 'sometimes|string|max:255',
                'codigo' => 'sometimes|string|max:255',
                'descripcion' => 'sometimes|string|max:255',
                'categoria_id' => 'sometimes|exists:categorias,id',
                'precio_compra' => 'sometimes|numeric|min:0',
                'precio_venta' => 'sometimes|numeric|min:0',
                'stock' => 'sometimes|integer|min:0',
            ]
        ];

        $validator = Validator::make($data, $rules[$scenario]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
