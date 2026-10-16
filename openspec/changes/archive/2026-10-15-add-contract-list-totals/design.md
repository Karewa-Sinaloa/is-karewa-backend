# Design

## Context

- `GET /api/v5/contracts` despacha a `contractsComponent` (`app/api/contracts/controller.php`), que usa el trait `Crud` sobre `BaseModel`; el método `get()` vive en `app/core/bootstrap/midelware.php` y construye `$get_params` (tabla `contratos c`, joins, filtros, búsqueda) para `DBGet::Get()` (`app/core/model/get.php`).
- `DBGet::Get()` ya soporta tres acciones: `count` (contador de resultados respetando filtros/joins/groupby), `list` (con `LIMIT`/`OFFSET` vía `get_limits`) y default (un registro, `LIMIT 1`).
- El bloque embebido actual es `?embed=pagination`, procesado en `midelware.php::get()` después de obtener la página: repite la consulta de conteo con los mismos filtros/joins y agrega `$data['pagination']`.
- Columnas relevantes en `contratos`: `total_amount` (monto con IVA), `subtotal` (monto sin IVA), `fiscal_year`, `period_id` (FK a `c_periods`).
- El resultado pasa por `ApiResponse::Set('SUCCESS', $data)`; el envelope ya tolera bloques extra (`pagination`).
- El documento OpenAPI publicado vive en `httpdocs/api/docs/openapi.json` con una copia byte-idéntica en `api/docs/openapi.json`; `tests/OpenApiSyncTest.php` protege ambas copias contra desfase. El parámetro `embed` del GET de `/contracts` hoy documenta `enum: ["pagination"]`.

## Goals / Non-Goals

**Goals:**

- Devolver totales agregados (con IVA, sin IVA, por periodo, por año fiscal, conteo) junto al listado de contratos, calculados sobre el conjunto completo filtrado (sin `LIMIT`).
- Mecanismo reutilizable en el ORM/middleware para que futuros módulos puedan declarar sus propios agregados, sin acoplar la lógica al módulo de contratos.
- El OpenAPI publicado refleja el nuevo valor de `embed` y la forma del bloque `totals`.
- Comportamiento idéntico al actual cuando no se pide `embed=totals`.

**Non-Goals:**

- Totales en otros módulos (solo contratos los declara en este cambio).
- Cambios de esquema, índices o migraciones.
- Modificar el bloque `pagination` existente ni su semántica.
- Cambios en el frontend (repo separado).

## Decisions

1. **Agregación en una nueva acción de `DBGet` (ej. `sum`/`totals`) en vez de sumar en PHP.**
   - Restricción del solicitante: usar el ORM actual; la única forma aceptable de salirse sería avisar y proponer alternativas antes de implementar. La nueva acción de `DBGet` cumple la restricción: se calcula con `SUM()`/`COUNT()` en SQL sobre el conjunto filtrado, sin `LIMIT`, reutilizando `get_joins()`/`get_filters()` igual que la acción `count`. No se requiere ninguna alternativa fuera del ORM.
   - Alternativa descartada: traer todas las filas y sumarlas en PHP — inviable con listados grandes y duplica el costo de red/memoria; descartada también cualquier consulta cruda fuera del ORM.

2. **Los agregados se declaran por módulo (opt-in) en el componente, no se calculan para todos los módulos.**
   - `contractsComponent` declara qué campos sumar (`total_amount`, `subtotal`), las columnas de agrupación por período (`period_id` + join a `c_periods` para el nombre) y por año fiscal (`fiscal_year`). El middleware solo ejecuta la agregación si el módulo la declara y el cliente pidió `embed=totals`.
   - Alternativa descartada: calcularlo genéricamente para cualquier módulo — desperdicia consultas y no todos los módulos tienen columnas monetarias.

3. **Los agregados se calculan con los mismos `$get_params['filters']`, `joins` y `search` que el listado**, incluida la normalización de búsqueda, para que los totales y el listado siempre coincidan. No se reutiliza el `groupby` del usuario: los agrupamientos por período/año fiscal son fijos del módulo.

4. **Se responden totales aunque el resultado esté vacío** (ceros y conteo 0), ajustando el camino de "sin resultados" del `get()` para no saltarse el bloque embebido cuando se pidieron totales.

5. **Tipos de salida**: importes como cadenas numéricas decimales (o `float` con precisión controlada) y conteo como entero, documentados en la spec. Se evita `float` binario en la suma mostrada redondeando a 2 decimales en SQL (`ROUND(SUM(...), 2)`), consistente con columnas `float(11,2)`.

6. **El OpenAPI se actualiza en ambas copias publicadas y se mantiene byte-idéntica.**
   - Se edita `httpdocs/api/docs/openapi.json` y se replica exactamente en `api/docs/openapi.json`: el `enum` de `embed` del GET de `/contracts` pasa a `["pagination", "totals"]`, la descripción explica que `totals` agrega el bloque de agregados del conjunto completo filtrado, y el ejemplo/esquema de respuesta del GET incluye el bloque `totals` (suma con IVA, suma sin IVA, totales por período, totales por año fiscal, conteo de resultados).
   - Verificación: `tests/OpenApiSyncTest.php` valida sincronía y calidad del documento; se ejecuta con la suite completa.
   - Alternativa descartada: derivar el OpenAPI automáticamente desde los componentes — el proyecto mantiene el documento a mano con una prueba de deriva; cambiar ese mecanismo está fuera del alcance.

## Risks / Trade-offs

- [Consultas agregadas adicionales por request] → Solo se ejecutan cuando el cliente pide `embed=totals`; se limitan a una consulta (o dos: general + por agrupamiento con `GROUP BY` en subconsulta).
- [Rendimiento de `SUM()` sin índice sobre tablas grandes] → Los totales se calculan sobre filtros ya acotados; si en el futuro el volumen lo exige, agregar índices es una optimización posterior fuera de este cambio.
- [Discrepancia entre conteo con `groupby` del usuario y conteo de totales] → El conteo de totales se define explícitamente como "contratos que cumple la consulta"; cuando hay `groupby`, se documenta y testea el comportamiento acordado (contar registros, no grupos).
- [Respuesta más pesada para clientes que no la piden] → El bloque solo aparece con `embed=totals`; no es breaking.

## Migration Plan

- Despliegue sin migración de datos ni de esquema; rollback = revertir el código (el comportamiento sin `embed=totals` no cambia).

## Open Questions

- Ninguno material. Se asume que "monto con IVA" = `total_amount` y "monto sin IVA" = `subtotal` (nombres canónicos ya usados por el módulo).
