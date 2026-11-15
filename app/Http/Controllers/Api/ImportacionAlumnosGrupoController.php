<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grupos\ImportarAlumnosGrupoRequest;
use App\Jobs\ProcesarImportacionAlumnosGrupo;
use App\Models\Grupo;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

#[Group('Grupos', weight: 4)]
class ImportacionAlumnosGrupoController extends Controller
{
    /**
     * Importar los alumnos de un grupo desde un CSV
     *
     * Crea las cuentas nuevas con rol alumno y asigna al grupo a todos los de
     * la lista. Si alguna fila trae otro grupo, no se importa ninguna. El
     * archivo se procesa en segundo plano: responde de inmediato (202) con un
     * id para consultar el resultado.
     * Encabezado del CSV: numero_cuenta,nombre,nip,correo,grupo.
     */
    // * Si cambia ImportadorAlumnosGrupo::ENCABEZADOS, actualiza la descripción.
    public function store(ImportarAlumnosGrupoRequest $request, Grupo $grupo): JsonResponse
    {
        $id = (string) Str::uuid();

        $ruta = $request->file('archivo')->storeAs('importaciones', "{$id}.csv");

        Cache::put(ProcesarImportacionAlumnosGrupo::claveCache($grupo->id, $id), ['estado' => 'en_proceso'], now()->addDay());

        ProcesarImportacionAlumnosGrupo::dispatch($id, $grupo->id, $ruta);

        return response()->json([
            'id' => $id,
            'estado' => 'en_proceso',
        ], 202);
    }

    /**
     * Consultar una importación de alumnos del grupo
     *
     * Devuelve el estado (en_proceso, terminada o fallida). Mientras sea
     * en_proceso, vuelve a consultar cada pocos segundos. Si terminó trae
     * archivo_rechazado (true si alguna fila era de otro grupo y no se importó
     * nada), creados (cuentas nuevas), asignados (alumnos que ya existían),
     * omitidos (los que ya estaban en el grupo, con su línea) y errores (por
     * línea); si falló trae un mensaje. El resultado se guarda 24 horas;
     * después responde 404. Solo se encuentra desde el grupo al que se subió.
     *
     * @param  string  $id  El id que devolvió la subida.
     */
    // * El contenido depende del estado, por eso se muestra un ejemplo de cada uno.
    #[Response(200, 'Estado de la importación', examples: [
        ['id' => '9b1c4f3e-2a7d-4c1e-8f0a-1d2e3f4a5b6c', 'estado' => 'en_proceso'],
        ['id' => '9b1c4f3e-2a7d-4c1e-8f0a-1d2e3f4a5b6c', 'estado' => 'terminada', 'archivo_rechazado' => false, 'creados' => 30, 'asignados' => 5, 'omitidos' => [['linea' => 4, 'motivo' => 'Ya está en este grupo.']], 'errores' => ['Línea 9: Ya está activo en el grupo 3-1.']],
        ['id' => '9b1c4f3e-2a7d-4c1e-8f0a-1d2e3f4a5b6c', 'estado' => 'terminada', 'archivo_rechazado' => true, 'creados' => 0, 'asignados' => 0, 'omitidos' => [], 'errores' => ['Línea 3: el grupo 1-2 no coincide con el grupo 1-1.']],
        ['id' => '9b1c4f3e-2a7d-4c1e-8f0a-1d2e3f4a5b6c', 'estado' => 'fallida', 'mensaje' => 'No se pudo procesar el archivo. Intenta subirlo de nuevo.'],
    ])]
    public function show(Grupo $grupo, string $id): JsonResponse
    {
        // * No tiene Form Request, por eso el permiso se revisa aquí.
        Gate::authorize('gestionarAlumnos', $grupo);

        $importacion = Cache::get(ProcesarImportacionAlumnosGrupo::claveCache($grupo->id, $id));

        // El reporte solo se guarda 24 horas.
        if ($importacion === null) {
            return response()->json([
                'message' => 'La importación no existe o ya expiró.',
            ], 404);
        }

        return response()->json(['id' => $id, ...$importacion]);
    }
}
