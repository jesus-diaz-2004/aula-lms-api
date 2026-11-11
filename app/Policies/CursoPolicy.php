<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Curso;
use App\Models\Usuario;

/**
 * Permisos para gestionar los cursos.
 *
 * $actor es quien hace la petición.
 */
class CursoPolicy
{
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
}
