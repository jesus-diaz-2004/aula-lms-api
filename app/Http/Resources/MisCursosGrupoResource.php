<?php

namespace App\Http\Resources;

use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un bloque de "Mis cursos": un grupo y los cursos de ese grupo.
 *
 * Recibe un grupo con su semestre y con sus cursos ya cargados (solo los
 * que le tocan a quien hace la petición).
 *
 * @mixin Grupo
 */
class MisCursosGrupoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grupo' => new GrupoResource($this->resource),
            'cursos' => CursoResource::collection($this->cursos),
        ];
    }
}
