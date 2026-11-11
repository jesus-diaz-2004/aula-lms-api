<?php

namespace App\Http\Requests\Cursos;

use App\Rules\DocenteActivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarDocenteCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cambiarDocente', $this->route('curso'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // bail: si no es un número, no se busca en la base de datos.
        return [
            // Id del nuevo docente del curso. Debe ser un docente activo.
            'docente_id' => ['bail', 'required', 'integer', 'exists:usuarios,id', new DocenteActivo],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'docente_id.required' => 'El docente es obligatorio.',
            'docente_id.integer' => 'El docente no es válido.',
            'docente_id.exists' => 'El docente no existe.',
        ];
    }
}
