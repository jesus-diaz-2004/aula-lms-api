<?php

namespace App\Rules;

use App\Enums\Rol;
use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El usuario debe tener rol de docente y estar activo para dar un curso.
 *
 * Va después de exists:usuarios,id; si el usuario no existe, esa regla ya da el error.
 */
class DocenteActivo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $usuario = Usuario::find($value);

        if ($usuario === null) {
            return;
        }

        if ($usuario->rol !== Rol::Docente) {
            $fail('El usuario no tiene rol de docente.');
        } elseif (! $usuario->activo) {
            $fail('El docente no está activo.');
        }
    }
}
