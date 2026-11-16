<?php

namespace App\Http\Requests\Cursos;

use App\Models\Curso;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarMisCursosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('verMisCursos', Curso::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Id del grupo para que el docente vea solo los cursos de ese grupo.
            'grupo_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grupo_id.integer' => 'El grupo no es válido.',
        ];
    }
}
