<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Curso;
use App\Models\GrupoAlumno;
use App\Models\Usuario;

/**
 * Permisos para gestionar y consultar los cursos.
 *
 * $actor es quien hace la petición.
 */
class CursoPolicy
{
    /**
     * Quién puede entrar a un curso. Lo usan también las historias de
     * contenido, actividades, asistencia y calificaciones.
     */
    public function view(Usuario $actor, Curso $curso): bool
    {
        if ($actor->rol === Rol::Administrador) {
            return true;
        }

        if ($this->esSuDocente($actor, $curso)) {
            return true;
        }

        if ($actor->rol !== Rol::Alumno) {
            return false;
        }

        // ! Un alumno dado de baja del grupo ya no entra a sus cursos.
        return GrupoAlumno::activos()
            ->where('grupo_id', $curso->grupo_id)
            ->where('alumno_id', $actor->id)
            ->exists();
    }

    /**
     * Quién puede ver la lista de alumnos de un curso. El alumno no.
     */
    public function verAlumnos(Usuario $actor, Curso $curso): bool
    {
        if ($actor->rol === Rol::Administrador) {
            return true;
        }

        return $this->esSuDocente($actor, $curso);
    }

    public function viewAny(Usuario $actor): bool
    {
        return $actor->rol === Rol::Administrador;
    }

    public function create(Usuario $actor): bool
    {
        return $actor->rol === Rol::Administrador;
    }

    public function cambiarDocente(Usuario $actor, Curso $curso): bool
    {
        return $actor->rol === Rol::Administrador;
    }

    // * Solo el docente asignado; si le cambian el curso, pierde el acceso.
    private function esSuDocente(Usuario $actor, Curso $curso): bool
    {
        return $actor->rol === Rol::Docente && $curso->docente_id === $actor->id;
    }
}
