<?php

namespace App\Observers;

use App\Models\Curso;
use App\Services\MisCursosCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Borra "Mis cursos" de quienes ven el curso cuando se crea o cambia de docente.
 */
class CursoObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Curso $curso): void
    {
        MisCursosCache::olvidar($curso->docente_id);

        // * Al cambiar de docente, el anterior también debe dejar de verlo.
        // getPrevious guarda el valor de antes aunque esto corra después del commit.
        if ($curso->wasChanged('docente_id')) {
            $anteriores = $curso->getPrevious();
            MisCursosCache::olvidar($anteriores['docente_id']);
        }

        // Los alumnos ven el curso nuevo o el nombre del nuevo docente.
        MisCursosCache::olvidarAlumnosDelGrupo($curso->grupo_id);
    }
}
