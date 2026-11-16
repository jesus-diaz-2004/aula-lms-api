<?php

namespace App\Http\Controllers\Api;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\ConsultarMisCursosRequest;
use App\Http\Resources\MisCursosGrupoResource;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\GrupoAlumno;
use App\Models\Usuario;
use App\Services\MisCursosCache;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

#[Group('Cursos', weight: 5)]
class MisCursosController extends Controller
{
    /**
     * Ver mis cursos
     *
     * El resultado depende de quién lo pide y viene organizado por grupo: cada
     * elemento trae el grupo (con su semestre) y sus cursos (con su materia y
     * su docente). El docente recibe los cursos que tiene asignados y puede
     * filtrarlos con grupo_id; el alumno, los cursos del grupo en el que está
     * activo. El administrador recibe 403: para él está la lista de todos los
     * cursos.
     */
    public function index(ConsultarMisCursosRequest $request): AnonymousResourceCollection
    {
        $usuario = $request->user();

        // * Se guarda en Redis; MisCursosCache la borra cuando algo cambia.
        $grupos = Cache::remember(MisCursosCache::clave($usuario->id), now()->addHour(), function () use ($usuario) {
            return $this->buscarGrupos($usuario);
        });

        // El filtro se aplica sobre la lista guardada, así hay una sola caché por persona.
        if ($usuario->rol === Rol::Docente && $request->filled('grupo_id')) {
            $filtrados = [];

            foreach ($grupos as $grupo) {
                if ($grupo->id === (int) $request->grupo_id) {
                    $filtrados[] = $grupo;
                }
            }

            $grupos = $filtrados;
        }

        return MisCursosGrupoResource::collection($grupos);
    }

    /**
     * Busca en la base de datos los grupos del usuario, cada uno con sus cursos.
     *
     * @return array<int, Grupo>
     */
    private function buscarGrupos(Usuario $usuario): array
    {
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
                return [];
            }

            $consulta->where('cursos.grupo_id', $grupoId);
        }

        // * Ordenar por grupo deja juntos los cursos de cada grupo.
        $cursos = $consulta->orderBy('grupos.nombre')
            ->orderBy('cursos.grupo_id')
            ->orderBy('materias.nombre')
            ->orderBy('cursos.id')
            ->get();

        // Junta los cursos de cada grupo, en el mismo orden de la consulta.
        $grupos = [];
        $cursosPorGrupo = [];

        foreach ($cursos as $curso) {
            $grupo = $curso->grupo;

            if (! isset($grupos[$grupo->id])) {
                $grupos[$grupo->id] = $grupo;
                $cursosPorGrupo[$grupo->id] = [];
            }

            // El grupo ya sale arriba; así no se repite dentro de cada curso.
            $curso->unsetRelation('grupo');

            $cursosPorGrupo[$grupo->id][] = $curso;
        }

        // ! Se cargan a mano solo los cursos de esta persona, no todos los del grupo.
        foreach ($grupos as $grupoId => $grupo) {
            $grupo->setRelation('cursos', new Collection($cursosPorGrupo[$grupoId]));
        }

        return array_values($grupos);
    }
}
