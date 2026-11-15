<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa en segundo plano el CSV con los alumnos de un grupo.
 */
class ProcesarImportacionAlumnosGrupo implements ShouldQueue
{
    use Queueable;

    /**
     * Recibe el número de la importación, el grupo y dónde quedó guardado el archivo.
     */
    public function __construct(
        public string $id,
        public int $grupoId,
        public string $ruta,
    ) {}

    /**
     * Nombre con el que se guardan en Redis el estado y el reporte.
     */
    // * Lleva el grupo para que una importación solo se consulte desde su propio grupo.
    public static function claveCache(int $grupoId, string $id): string
    {
        return "importaciones:grupos:{$grupoId}:{$id}";
    }

    public function handle(): void
    {
        // TODO: HU-14a procesar el archivo, guardar el reporte y borrar el archivo
    }
}
