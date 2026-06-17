<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
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
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $existente = User::query()->where('email', $value)->first();
                    if (! $existente) {
                        return;
                    }
                    if ($existente->google_id) {
                        $fail('Este correo ya está registrado con Google. Usá «Continuar con Google» para ingresar.');

                        return;
                    }
                    $fail('Este correo ya está registrado. Iniciá sesión o usá otro email.');
                },
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'telefono' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'titulo' => ['required_if:role,professional', 'nullable', 'string', 'max:150'],
            'categoria' => ['required_if:role,professional', 'nullable', 'string', Rule::in(array_keys(config('professional_categories')))],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'Ingresá un email válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'role.required' => 'Seleccioná si sos cliente o profesional.',
            'titulo.required_if' => 'Indicá un título profesional.',
            'categoria.required_if' => 'Seleccioná una categoría.',
            'categoria.in' => 'La categoría seleccionada no es válida.',
        ];
    }
}
