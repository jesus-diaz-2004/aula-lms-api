<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\ConsultarAlumnosCursoRequest;
use App\Http\Resources\AlumnoCursoResource;
use App\Models\Curso;
use App\Models\GrupoAlumno;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Cursos', weight: 5)]
class CursoAlumnoController extends Controller
{
    public const ALUMNOS_POR_PAGINA = 15;

    /**
     * Listar los alumnos de un curso
     *
     * Son los alumnos activos del grupo del curso, paginados de 15 en 15 y
     * ordenados por nombre. Solo traen el número de cuenta y el nombre. Acepta
     * en la URL: buscar (parte del número de cuenta o del nombre). La pueden
     * ver el administrador y el docente asignado; el alumno no.
     */
    // * La paginación no la detecta sola la documentación, por eso se declara "page".
    #[QueryParameter('page', 'Número de página.', type: 'int', default: 1)]
    public function index(ConsultarAlumnosCursoRequest $request, Curso $curso): AnonymousResourceCollection
    {
        // El join permite buscar y ordenar por los datos del alumno.
        // ! Los dados de baja no son alumnos del curso.
        $consulta = GrupoAlumno::query()
            ->join('usuarios', 'usuarios.id', '=', 'grupo_alumnos.alumno_id')
            ->select('grupo_alumnos.*')
            ->where('grupo_alumnos.grupo_id', $curso->grupo_id)
            ->activos()
            ->with('alumno');

        if ($request->filled('buscar')) {
            $texto = '%'.$request->buscar.'%';

            $consulta->where(function ($condicion) use ($texto) {
                $condicion->whereLike('usuarios.numero_cuenta', $texto)
                    ->orWhereLike('usuarios.nombre', $texto);
            });
        }

        // El id desempata a los alumnos con el mismo nombre para que no cambien de página.
        $alumnos = $consulta->orderBy('usuarios.nombre')
            ->orderBy('grupo_alumnos.id')
            ->paginate(self::ALUMNOS_POR_PAGINA);

        // Los enlaces de las otras páginas conservan la búsqueda.
        $alumnos->withQueryString();

        return AlumnoCursoResource::collection($alumnos);
    }
}
