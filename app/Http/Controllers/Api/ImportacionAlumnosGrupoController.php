<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grupos\ImportarAlumnosGrupoRequest;
use App\Jobs\ProcesarImportacionAlumnosGrupo;
use App\Models\Grupo;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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
}
