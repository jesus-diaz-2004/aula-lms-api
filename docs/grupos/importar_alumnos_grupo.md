# Importar los alumnos de un grupo desde un archivo CSV

Guía para el administrador y para el equipo web (pantalla del grupo, HU-14a).

## Plantilla

Descárgala desde la API:

```
GET /plantillas/plantilla_alumnos_grupo.csv
```

## Instrucciones para el administrador

Este texto se puede mostrar tal cual en la pantalla del grupo.

1. Entra al grupo al que pertenece la lista.
2. Descarga la plantilla y ábrela en Excel.
3. Llena una fila por alumno. No cambies los encabezados.
4. En la columna `grupo` escribe el nombre del grupo tal como aparece en el
   sistema (por ejemplo `1-5`). Si una sola fila trae otro grupo, **no se
   importa ninguna** y el reporte te dice qué líneas revisar.
5. Guarda el archivo como **CSV UTF-8 (delimitado por comas)**.
6. Súbelo aquí. Puede pesar hasta 2 MB.
7. Espera el reporte: verás cuántas cuentas se crearon, cuántos alumnos que ya
   existían se asignaron, quiénes ya estaban en el grupo y qué filas tienen
   error.
8. Si hay errores, corrige esas filas y vuelve a subir el archivo completo.
   Lo que ya se importó no se duplica ni cambia.
9. Al terminar, **borra el archivo** de tu computadora y de la memoria USB:
   contiene los NIP.

| Columna | Obligatoria | Qué poner |
|---|---|---|
| `numero_cuenta` | Sí | Hasta 20 caracteres. |
| `nombre` | Sí, si la cuenta es nueva | Nombre completo, hasta 100 caracteres. |
| `nip` | Sí, si la cuenta es nueva | De 4 a 6 letras o números, sin espacios ni símbolos. |
| `correo` | No | Correo válido. Puede ir vacío. |
| `grupo` | Sí | El nombre del grupo al que estás subiendo la lista. |

Qué pasa con cada fila:

- **Cuenta nueva:** se crea como alumno y se asigna al grupo.
- **Alumno que ya existe:** solo se asigna al grupo. No cambian su nombre, su
  correo ni su NIP.
- **Alumno dado de baja de este grupo:** se reingresa.
- **Ya está en el grupo:** no cambia nada y aparece en `omitidos`.
- **Error** (no es alumno, su cuenta no está activa, está activo en otro grupo
  o le faltan datos): no se importa y aparece en `errores` con su línea.

Ejemplo:

```csv
numero_cuenta,nombre,nip,correo,grupo
20990001,Ana López Hernández,AB12,a.lopez@info.uas.edu.mx,1-5
20990002,José Luis Pérez Martínez,XY99,,1-5
```

El nombre del archivo no importa: el grupo se toma de la pantalla donde lo
subes. Usar nombres como `lista_1-5.csv` solo te ayuda a no confundirlos.

## Endpoints para el equipo web

Todos piden el token del administrador (`Authorization: Bearer ...`).

### Subir el archivo

```
POST /api/grupos/{grupo}/alumnos/importaciones
Content-Type: multipart/form-data
archivo: (el archivo .csv)
```

Respuesta `202`:

```json
{ "id": "6b9efd6f-6fe9-4210-847d-9a99846748b4", "estado": "en_proceso" }
```

Errores: `401` sin sesión, `403` si no es administrador, `404` si el grupo no
existe y `422` si falta el archivo, no es `.csv` o pesa más de 2 MB.

### Consultar el estado

```
GET /api/grupos/{grupo}/alumnos/importaciones/{id}
```

Consúltalo cada pocos segundos hasta que el estado deje de ser `en_proceso`.
Usa el mismo grupo al que se subió: desde otro grupo responde `404`.

Terminada (`200`):

```json
{
  "id": "6b9efd6f-6fe9-4210-847d-9a99846748b4",
  "estado": "terminada",
  "archivo_rechazado": false,
  "creados": 30,
  "asignados": 5,
  "omitidos": [{ "linea": 4, "motivo": "Ya está en este grupo." }],
  "errores": ["Línea 9: Ya está activo en el grupo 3-1."]
}
```

Rechazada porque alguna fila era de otro grupo (`200`, no se importó nada):

```json
{
  "id": "6b9efd6f-6fe9-4210-847d-9a99846748b4",
  "estado": "terminada",
  "archivo_rechazado": true,
  "creados": 0,
  "asignados": 0,
  "omitidos": [],
  "errores": ["Línea 3: el grupo 1-2 no coincide con el grupo 1-5."]
}
```

Fallida (`200`):

```json
{
  "id": "6b9efd6f-6fe9-4210-847d-9a99846748b4",
  "estado": "fallida",
  "mensaje": "No se pudo procesar el archivo. Intenta subirlo de nuevo."
}
```

Si el `id` no existe o ya pasaron 24 horas, responde `404`.
