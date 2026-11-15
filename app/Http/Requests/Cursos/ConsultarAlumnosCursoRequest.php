<?php

namespace App\Http\Requests\Cursos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarAlumnosCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('verAlumnos', $this->route('curso'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Parte del número de cuenta o del nombre del alumno.
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buscar.max' => 'La búsqueda no puede tener más de 100 caracteres.',
        ];
    }
}
