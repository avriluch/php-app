<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'telefono' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'titulo' => ['required_if:role,professional', 'nullable', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
