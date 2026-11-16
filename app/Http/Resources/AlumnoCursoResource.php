<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lo mínimo de un alumno que sale en la lista de un curso, sin sus datos de contacto
 */
class AlumnoCursoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'alumno_id' => $this->alumno_id,
            'numero_cuenta' => $this->alumno->numero_cuenta,
            'nombre' => $this->alumno->nombre,
        ];
    }
}
