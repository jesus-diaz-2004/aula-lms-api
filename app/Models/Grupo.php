<?php

namespace App\Models;

use App\Enums\Rol;
use App\Enums\Turno;
use App\Observers\NombreEnMisCursosObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un grupo (salón) que cursa un semestre.
 */
#[ObservedBy(NombreEnMisCursosObserver::class)]
#[Fillable(['semestre_id', 'nombre', 'turno'])]
class Grupo extends Model
{
    protected $table = 'grupos';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'turno' => Turno::class,
        ];
    }

    /**
     * @return BelongsTo<Semestre, $this>
     */
    public function semestre(): BelongsTo
    {
        return $this->belongsTo(Semestre::class);
    }

    /**
     * @return HasMany<GrupoAlumno, $this>
     */
    public function grupoAlumnos(): HasMany
    {
        return $this->hasMany(GrupoAlumno::class);
    }

    /**
     * @return HasMany<Curso, $this>
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * Dice por qué un usuario no puede quedar activo en este grupo, o null si sí puede.
     *
     * Agregar a mano, reingresar e importar desde CSV usan estas mismas reglas.
     */
    public function motivoParaNoActivar(Usuario $usuario): ?string
    {
        if ($usuario->rol !== Rol::Alumno) {
            return 'No tiene rol de alumno.';
        }

        if (! $usuario->activo) {
            return 'Su cuenta no está activa.';
        }

        // * Un alumno solo puede estar activo en un grupo a la vez.
        $filaEnOtroGrupo = GrupoAlumno::activos()
            ->where('alumno_id', $usuario->id)
            ->where('grupo_id', '!=', $this->id)
            ->first();

        if ($filaEnOtroGrupo !== null) {
            return 'Ya está activo en el grupo '.$filaEnOtroGrupo->grupo->nombre.'.';
        }

        return null;
    }
}
