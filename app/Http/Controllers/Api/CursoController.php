<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\CambiarDocenteCursoRequest;
use App\Http\Requests\Cursos\ConsultarCursosRequest;
use App\Http\Requests\Cursos\RegistrarCursoRequest;
use App\Http\Resources\CursoResource;
use App\Models\Curso;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Cursos', weight: 5)]
class CursoController extends Controller
{
    public const CURSOS_POR_PAGINA = 15;

    /**
     * Listar cursos
     *
     * Paginada de 15 en 15 y ordenada por el nombre de la materia. Cada curso
     * trae su grupo, con sus alumnos activos, y su docente; docente en null
     * significa que no tiene. Acepta en la URL: buscar (parte del nombre o la clave de la materia),
     * grupo_id, semestre_id y docente_id. Se pueden combinar.
     */
    // * La paginación no la detecta sola la documentación, por eso se declara "page".
    #[QueryParameter('page', 'Número de página.', type: 'int', default: 1)]
    public function index(ConsultarCursosRequest $request): AnonymousResourceCollection
    {
        // El join permite buscar, filtrar y ordenar por los datos de la materia.
        $consulta = Curso::query()
            ->join('materias', 'materias.id', '=', 'cursos.materia_id')
            ->select('cursos.*')
            ->with([
                'materia',
                'docente',
                // * Los alumnos del curso son los activos de su grupo; los dados de baja no cuentan.
                'grupo' => function ($grupos) {
                    $grupos->with('semestre')
                        ->withCount(['grupoAlumnos as alumnos_activos_count' => function ($filas) {
                            $filas->activos();
                        }]);
                },
            ]);

        if ($request->filled('buscar')) {
            $texto = '%'.$request->buscar.'%';

            $consulta->where(function ($condicion) use ($texto) {
                $condicion->whereLike('materias.nombre', $texto)
                    ->orWhereLike('materias.clave', $texto);
            });
        }

        if ($request->filled('grupo_id')) {
            $consulta->where('cursos.grupo_id', $request->grupo_id);
        }

        // * La materia y el grupo de un curso siempre son del mismo semestre.
        if ($request->filled('semestre_id')) {
            $consulta->where('materias.semestre_id', $request->semestre_id);
        }

        if ($request->filled('docente_id')) {
            $consulta->where('cursos.docente_id', $request->docente_id);
        }

        // El id desempata a las materias con el mismo nombre para que no cambien de página.
        $cursos = $consulta->orderBy('materias.nombre')
            ->orderBy('cursos.id')
            ->paginate(self::CURSOS_POR_PAGINA);

        // Los enlaces de las otras páginas conservan la búsqueda y los filtros.
        $cursos->withQueryString();

        return CursoResource::collection($cursos);
    }

    /**
     * Registrar un curso
     *
     * Une una materia con un grupo. Una materia solo puede tener un curso por
     * grupo. El docente es opcional. El nombre y la clave salen de la materia.
     */
    public function store(RegistrarCursoRequest $request): JsonResponse
    {
        $curso = Curso::create([
            'materia_id' => $request->materia_id,
            'grupo_id' => $request->grupo_id,
            'docente_id' => $request->docente_id,
        ]);

        return response()->json([
            'message' => 'Curso registrado.',
            'curso' => new CursoResource($curso),
        ], 201);
    }

    /**
     * Asignar o cambiar el docente de un curso
     *
     * Sirve igual para un curso sin docente que para uno que ya tiene. El curso
     * solo guarda un docente, así que el anterior deja de serlo; el contenido y
     * las calificaciones son del curso y se quedan.
     */
    public function cambiarDocente(CambiarDocenteCursoRequest $request, Curso $curso): JsonResponse
    {
        $curso->update(['docente_id' => $request->docente_id]);

        // Se carga el nuevo docente para devolverlo en la respuesta.
        $curso->load('docente');

        return response()->json([
            'message' => 'Docente del curso actualizado.',
            'curso' => new CursoResource($curso),
        ]);
    }
}
