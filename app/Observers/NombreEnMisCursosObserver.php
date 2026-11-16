<?php

namespace App\Observers;

use App\Enums\Rol;
use App\Models\Usuario;
use App\Services\MisCursosCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo usan Grupo, Materia y Usuario: sus datos salen en las tarjetas de
 * "Mis cursos" de muchas personas, así que al editarlos se deja sin usar
 * la caché de todos.
 */
class NombreEnMisCursosObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Model $modelo): void
    {
        // * De un usuario solo importan los datos del docente que salen en la tarjeta.
        if ($modelo instanceof Usuario) {
            if (! $modelo->wasChanged(['nombre', 'numero_cuenta', 'rol'])) {
                return;
            }

            // getPrevious trae el rol de antes como texto, tal como está en la tabla.
            $anteriores = $modelo->getPrevious();
            $eraDocente = isset($anteriores['rol']) && $anteriores['rol'] === Rol::Docente->value;

            if ($modelo->rol !== Rol::Docente && ! $eraDocente) {
                return;
            }
        }

        MisCursosCache::olvidarTodo();
    }
}
