<?php

namespace App\Http\Controllers\Api;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Resources\CursoResource;
use App\Models\Curso;
use App\Models\GrupoAlumno;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Cursos', weight: 5)]
class MisCursosController extends Controller
{
    /**
     * Ver mis cursos
     *
     * El resultado depende de quién lo pide. El docente recibe los cursos que
     * tiene asignados; el alumno, los cursos del grupo en el que está activo.
     * Cada curso trae su materia, su grupo con el semestre y su docente.
     * El administrador recibe 403: para él está la lista de todos los cursos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('verMisCursos', Curso::class);

        $usuario = $request->user();

        // El join permite ordenar por el grupo y por la materia.
        $consulta = Curso::query()
            ->join('grupos', 'grupos.id', '=', 'cursos.grupo_id')
            ->join('materias', 'materias.id', '=', 'cursos.materia_id')
            ->select('cursos.*')
            ->with(['materia', 'docente', 'grupo.semestre']);

        if ($usuario->rol === Rol::Docente) {
            $consulta->where('cursos.docente_id', $usuario->id);
        } else {
            // ! Solo el grupo donde está activo; si lo dieron de baja, no ve nada.
            $grupoId = GrupoAlumno::activos()
                ->where('alumno_id', $usuario->id)
                ->value('grupo_id');

            if ($grupoId === null) {
                return CursoResource::collection([]);
            }

            $consulta->where('cursos.grupo_id', $grupoId);
        }

        $cursos = $consulta->orderBy('grupos.nombre')
            ->orderBy('materias.nombre')
            ->orderBy('cursos.id')
            ->get();

        return CursoResource::collection($cursos);
    }
}
