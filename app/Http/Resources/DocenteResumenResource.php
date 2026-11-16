<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lo mínimo de un docente que sale dentro de un curso, sin sus datos de contacto
 */
class DocenteResumenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_cuenta' => $this->numero_cuenta,
            'nombre' => $this->nombre,
        ];
    }
}
