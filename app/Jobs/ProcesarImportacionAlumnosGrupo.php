<?php

namespace App\Jobs;

use App\Models\Grupo;
use App\Services\ImportadorAlumnosGrupo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Procesa en segundo plano el CSV con los alumnos de un grupo.
 */
class ProcesarImportacionAlumnosGrupo implements ShouldQueue
{
    use Queueable;

    // Hasta 15 minutos: cada alumno nuevo tarda un poco por el hash del NIP.
    public int $timeout = 900;

    // Si se pasa del tiempo, no lo vuelve a intentar.
    public bool $failOnTimeout = true;

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

    public function handle(ImportadorAlumnosGrupo $importador): void
    {
        $grupo = Grupo::findOrFail($this->grupoId);

        $reporte = $importador->importar(Storage::path($this->ruta), $grupo);

        Cache::put(self::claveCache($this->grupoId, $this->id), ['estado' => 'terminada', ...$reporte], now()->addDay());

        Storage::delete($this->ruta);
    }

    /**
     * Se ejecuta si el proceso falla después de todos sus intentos.
     */
    public function failed(?Throwable $error): void
    {
        // ! El archivo trae NIP en texto plano: se borra aunque el proceso falle.
        Storage::delete($this->ruta);

        Cache::put(self::claveCache($this->grupoId, $this->id), [
            'estado' => 'fallida',
            'mensaje' => 'No se pudo procesar el archivo. Intenta subirlo de nuevo.',
        ], now()->addDay());
    }
}
