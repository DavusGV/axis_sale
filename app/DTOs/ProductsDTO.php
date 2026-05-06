<?php
namespace App\DTOs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProductsDTO
{
    public static function validate(array $data, $scenario)
    {
        $rules = [
            'store' => [
                'autogenerar' => 'nullable|boolean',
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
                'codigo' => [
                    // si autogenerar es true, el codigo se ignora; si es false, se valida unique por establecimiento
                    Rule::requiredIf(fn() => empty($data['autogenerar'])),
                    'nullable',
                    'string',
                    'max:100',
                    function($attribute, $value, $fail) use ($data) {
                        // si esta en autogenerar, no validamos unique porque el backend lo generara
                        if (!empty($data['autogenerar'])) {
                            return;
                        }
                        // unicidad por establecimiento
                        $exists = \App\Models\Products::where('codigo', $value)
                            ->where('establecimiento_id', $data['establecimiento_id'] ?? 1)
                            ->exists();
                        if ($exists) {
                            $fail('Ya existe un producto con ese codigo en este establecimiento.');
                        }
                    }
                ],
                'descripcion' => 'nullable|string',
                'categoria_id' => 'required|exists:categorias,id',
                'precio_compra' => 'required|numeric|min:0',
                'precio_venta' => 'required|numeric|min:0',
                'iva' => 'nullable|numeric|min:0|max:100',
                'unidad_medida_id' => 'nullable|exists:unidades_medidas,id',
                'es_servicio' => 'nullable|boolean',
                'stock' => 'required|integer|min:0',
                'clave' => [
                    // misma logica que codigo
                    Rule::requiredIf(fn() => empty($data['autogenerar'])),
                    'nullable',
                    'string',
                    'max:100',
                    function($attribute, $value, $fail) use ($data) {
                        if (!empty($data['autogenerar'])) {
                            return;
                        }
                        $exists = \App\Models\Products::where('clave', $value)
                            ->where('establecimiento_id', $data['establecimiento_id'] ?? 1)
                            ->exists();
                        if ($exists) {
                            $fail('Ya existe un producto con esa clave en este establecimiento.');
                        }
                    }
                ],
                'imagen' => 'nullable'
            ],
            'update' => [
                'nombre' => 'sometimes|string|max:255',
                'codigo' => 'sometimes|string|max:255',
                'descripcion' => 'sometimes|string|max:255',
                'categoria_id' => 'sometimes|exists:categorias,id',
                'precio_compra' => 'sometimes|numeric|min:0',
                'precio_venta' => 'sometimes|numeric|min:0',
                'unidad_medida_id' => 'nullable|exists:unidades_medidas,id',
                'es_servicio' => 'nullable|boolean',
                'iva' => 'nullable|numeric|min:0|max:100',
                'stock' => 'sometimes|integer|min:0',
                'clave' => 'sometimes|string|max:255',
                'imagen' => 'nullable',
            ]
        ];

        $validator = Validator::make($data, $rules[$scenario]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
