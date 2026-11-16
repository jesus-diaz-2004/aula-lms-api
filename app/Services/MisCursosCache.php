<?php

namespace App\Services;

use App\Models\GrupoAlumno;
use Illuminate\Support\Facades\Cache;

/**
 * La caché de "Mis cursos": guarda la lista de cada persona y la borra
 * cuando algo cambia, para que el cambio se vea de inmediato.
 */
class MisCursosCache
{
    // * Subir este número deja sin usar la caché de todos a la vez.
    private const CLAVE_VERSION = 'mis-cursos:version';

    /**
     * Nombre con el que se guarda la lista de un usuario.
     */
    public static function clave(int $usuarioId): string
    {
        $version = Cache::get(self::CLAVE_VERSION, 0);

        return "mis-cursos:v{$version}:usuario:{$usuarioId}";
    }

    /**
     * Borra la lista de un usuario. Se usa cuando cambian sus cursos.
     */
    public static function olvidar(?int $usuarioId): void
    {
        // Un curso puede no tener docente todavía.
        if ($usuarioId === null) {
            return;
        }

        Cache::forget(self::clave($usuarioId));
    }

    /**
     * Borra la lista de los alumnos activos de un grupo.
     */
    public static function olvidarAlumnosDelGrupo(int $grupoId): void
    {
        $alumnoIds = GrupoAlumno::activos()
            ->where('grupo_id', $grupoId)
            ->pluck('alumno_id');

        foreach ($alumnoIds as $alumnoId) {
            self::olvidar($alumnoId);
        }
    }

    /**
     * Deja sin usar la lista de todos. Se usa cuando cambia un nombre que
     * sale en muchas listas (un grupo, una materia o un docente).
     */
    public static function olvidarTodo(): void
    {
        // Las listas viejas ya no se leen y Redis las borra cuando vencen.
        Cache::increment(self::CLAVE_VERSION);
    }
}
