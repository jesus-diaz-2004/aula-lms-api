<?php

namespace App\Observers;

use App\Models\GrupoAlumno;
use App\Services\MisCursosCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Borra "Mis cursos" del alumno cuando lo agregan, lo dan de baja o lo
 * reingresan, desde cualquier lugar (pantalla o archivo CSV).
 */
class GrupoAlumnoObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(GrupoAlumno $grupoAlumno): void
    {
        MisCursosCache::olvidar($grupoAlumno->alumno_id);
    }
}
