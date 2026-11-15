<?php

namespace App\Http\Requests\Grupos;

use App\Http\Requests\Usuarios\ImportarUsuariosRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportarAlumnosGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gestionarAlumnos', $this->route('grupo'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Archivo CSV con la lista de alumnos del grupo, de hasta 2 MB.
            // * El mismo límite que la importación de usuarios.
            'archivo' => ['required', 'file', 'extensions:csv', 'mimes:csv,txt', 'max:'.ImportarUsuariosRequest::TAMANO_MAXIMO_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Selecciona el archivo CSV con los alumnos del grupo.',
            'archivo.file' => 'El archivo no se subió correctamente.',
            'archivo.extensions' => 'El archivo debe ser .csv.',
            'archivo.mimes' => 'El archivo debe ser un CSV de texto.',
            'archivo.max' => 'El archivo no puede pesar más de 2 MB.',
        ];
    }
}
