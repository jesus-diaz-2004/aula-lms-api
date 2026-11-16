<?php

namespace App\Services;

use App\Enums\EstadoGrupoAlumno;
use App\Enums\Rol;
use App\Models\Grupo;
use App\Models\GrupoAlumno;
use App\Models\Usuario;
use App\Rules\Nip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Registra y asigna a un grupo los alumnos de un archivo CSV.
 *
 * Primero revisa que todas las filas sean del grupo elegido; si alguna no lo
 * es, no importa nada. Si todas coinciden, procesa cada fila por separado: una
 * fila con error no detiene a las demás. Subir el mismo archivo dos veces no
 * cambia nada.
 */
class ImportadorAlumnosGrupo
{
    public const ENCABEZADOS = ['numero_cuenta', 'nombre', 'nip', 'correo', 'grupo'];

    private array $reporte;

    /**
     * Devuelve si el archivo se rechazó, cuántos alumnos se crearon y cuántos
     * se asignaron, y por línea los omitidos y los errores.
     */
    public function importar(string $ruta, Grupo $grupo): array
    {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer el archivo de importación.');
        }

        $this->reporte = [
            'archivo_rechazado' => false,
            'creados' => 0,
            'asignados' => 0,
            'omitidos' => [],
            'errores' => [],
        ];

        $filas = $this->leerFilas($ruta);

        // Si el encabezado no sirve, leerFilas ya dejó el error en el reporte.
        if ($filas === null) {
            $this->reporte['archivo_rechazado'] = true;

            return $this->reporte;
        }

        // * Primera vuelta: si una sola fila es de otro grupo, no se importa ninguna.
        $erroresDeGrupo = $this->revisarGrupo($filas, $grupo);

        if ($erroresDeGrupo !== []) {
            $this->reporte['archivo_rechazado'] = true;
            $this->reporte['errores'] = $erroresDeGrupo;

            return $this->reporte;
        }

        // Segunda vuelta: cada fila se crea o se asigna por su cuenta.
        foreach ($filas as $linea => $fila) {
            $this->importarFila($fila, $linea, $grupo);
        }

        return $this->reporte;
    }

    /**
     * Lee el archivo y devuelve las filas con su número de línea, o null si el
     * encabezado no es el esperado.
     */
    private function leerFilas(string $ruta): ?array
    {
        $archivo = fopen($ruta, 'r');

        // Lee las comillas igual que Excel.
        $encabezados = fgetcsv($archivo, escape: '');

        if (! $this->encabezadosValidos($encabezados)) {
            $this->reporte['errores'][] = 'Línea 1: el encabezado debe ser '.implode(',', self::ENCABEZADOS).'.';
            fclose($archivo);

            return null;
        }

        $filas = [];
        $linea = 1;

        while (($fila = fgetcsv($archivo, escape: '')) !== false) {
            $linea++;

            // Salta los renglones vacíos.
            if ($fila === [null]) {
                continue;
            }

            $filas[$linea] = $fila;
        }

        fclose($archivo);

        return $filas;
    }

    private function encabezadosValidos(array|false $encabezados): bool
    {
        if ($encabezados === false || $encabezados === [null]) {
            return false;
        }

        // Quita un carácter invisible que Excel pone al inicio del archivo.
        $encabezados[0] = preg_replace('/^\xEF\xBB\xBF/', '', $encabezados[0]);

        $limpios = [];

        foreach ($encabezados as $encabezado) {
            $limpios[] = trim($encabezado);
        }

        return $limpios === self::ENCABEZADOS;
    }

    /**
     * Devuelve un error por cada línea cuyo grupo no es el elegido.
     */
    private function revisarGrupo(array $filas, Grupo $grupo): array
    {
        $errores = [];

        foreach ($filas as $linea => $fila) {
            // Las filas con columnas de más o de menos se reportan en la segunda vuelta.
            if (count($fila) !== count(self::ENCABEZADOS)) {
                continue;
            }

            $grupoDeLaFila = trim($fila[4]);

            if ($grupoDeLaFila !== $grupo->nombre) {
                $errores[] = "Línea {$linea}: el grupo {$grupoDeLaFila} no coincide con el grupo {$grupo->nombre}.";
            }
        }

        return $errores;
    }

    private function importarFila(array $fila, int $linea, Grupo $grupo): void
    {
        if (count($fila) !== count(self::ENCABEZADOS)) {
            $this->reporte['errores'][] = "Línea {$linea}: número de columnas incorrecto.";

            return;
        }

        $datos = [];

        foreach (self::ENCABEZADOS as $posicion => $columna) {
            $datos[$columna] = trim($fila[$posicion]);
        }

        if ($datos['numero_cuenta'] === '') {
            $this->reporte['errores'][] = "Línea {$linea}: Falta el campo numero_cuenta.";

            return;
        }

        $usuario = Usuario::where('numero_cuenta', $datos['numero_cuenta'])->first();

        // * Si la cuenta ya existe, solo se asigna: no se tocan sus datos ni su NIP.
        if ($usuario !== null) {
            $this->asignarExistente($usuario, $linea, $grupo);

            return;
        }

        $this->crearYAsignar($datos, $linea, $grupo);
    }

    private function asignarExistente(Usuario $usuario, int $linea, Grupo $grupo): void
    {
        $motivo = $grupo->motivoParaNoActivar($usuario);

        if ($motivo !== null) {
            $this->reporte['errores'][] = "Línea {$linea}: {$motivo}";

            return;
        }

        $fila = GrupoAlumno::where('grupo_id', $grupo->id)
            ->where('alumno_id', $usuario->id)
            ->first();

        if ($fila === null) {
            GrupoAlumno::create([
                'grupo_id' => $grupo->id,
                'alumno_id' => $usuario->id,
            ]);
            $this->reporte['asignados']++;
        } elseif ($fila->estado === EstadoGrupoAlumno::Baja) {
            // * Reingreso: se reusa su fila porque la tabla no permite dos iguales.
            $fila->update([
                'estado' => EstadoGrupoAlumno::Activo,
                'baja_en' => null,
            ]);
            $this->reporte['asignados']++;
        } else {
            $this->reporte['omitidos'][] = ['linea' => $linea, 'motivo' => 'Ya está en este grupo.'];
        }
    }

    private function crearYAsignar(array $datos, int $linea, Grupo $grupo): void
    {
        $validador = Validator::make($datos, [
            'numero_cuenta' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'nip' => ['required', new Nip],
            'correo' => ['nullable', 'email', 'max:150'],
        ], [
            'required' => 'Falta el campo :attribute.',
            'max' => 'El campo :attribute supera :max caracteres.',
            'correo.email' => 'El correo no es válido.',
        ]);

        if ($validador->fails()) {
            $this->reporte['errores'][] = "Línea {$linea}: ".implode(' ', $validador->errors()->all());

            return;
        }

        // * Se crea la cuenta y se asigna juntas: si una falla, no queda ninguna de las dos.
        DB::transaction(function () use ($datos, $grupo) {
            $alumno = Usuario::create([
                'numero_cuenta' => $datos['numero_cuenta'],
                'nombre' => $datos['nombre'],
                'rol' => Rol::Alumno,
                // El modelo hashea el NIP al guardarlo.
                'nip_hash' => $datos['nip'],
                'correo' => $datos['correo'] ?: null,
            ]);

            GrupoAlumno::create([
                'grupo_id' => $grupo->id,
                'alumno_id' => $alumno->id,
            ]);
        });

        $this->reporte['creados']++;
    }
}
