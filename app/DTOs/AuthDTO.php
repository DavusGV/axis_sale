<?php
namespace App\DTOs;

use Illuminate\Support\Facades\Validator;

class AuthDTO
{
    public static function validate(array $data, string $type)
    {
        $rules = [
            'register' => [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ],
            'login' => [
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]
        ];
        return Validator::make($data, $rules[$type])->validate();
    }
}
