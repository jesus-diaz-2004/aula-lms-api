<?php

namespace App\Http\Requests\Cursos;

use App\Models\Curso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarCursosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Curso::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Parte del nombre o de la clave de la materia.
            'buscar' => ['nullable', 'string', 'max:100'],
            // Id del grupo para ver solo sus cursos.
            'grupo_id' => ['nullable', 'integer'],
            // Id del semestre para ver solo sus cursos.
            'semestre_id' => ['nullable', 'integer'],
            // Id del docente para ver solo sus cursos.
            'docente_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'buscar.max' => 'La búsqueda no puede tener más de 100 caracteres.',
            'grupo_id.integer' => 'El grupo no es válido.',
            'semestre_id.integer' => 'El semestre no es válido.',
            'docente_id.integer' => 'El docente no es válido.',
        ];
    }
}
