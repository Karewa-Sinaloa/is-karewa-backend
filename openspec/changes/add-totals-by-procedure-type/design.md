# Design

## Context

El bloque `totals` lo calcula `getTotals()` en `app/core/bootstrap/midelware.php:384` a partir de la declaración `$get_params['totals']` del módulo (`sums` + `groups`, con `nest` opcional). Cada grupo es una consulta `DBGet::Get(..., 'sum')` con `group_by`, reutilizando `table`, `filters` y `joins` del listado — o sea, mismos filtros/búsqueda, sin paginar, ya garantizado por la requisito "Totals honor filters and search". El módulo de contratos ya declara `by_fiscal_year` y `by_fiscal_year_period` en `app/api/contracts/controller.php:110-141`, y el join a `c_procedures p` ya existe en `$get_params['joins']` (`app/api/contracts/controller.php:66-70`). La motivación está en `proposal.md` (Why).

## Goals / Non-Goals

**Goals:**

- Expor `totals.by_procedure` en `GET /api/v5/contracts?embed=totals`, calculado sobre el conjunto filtrado completo (sin paginar), con suma solo de `total_amount` (con IVA).
- Sin tocar el motor de totales del ORM ni la forma de los campos existentes.
- Documentar el nuevo bloque en el OpenAPI publicado (ambas copias sincronizadas).

**Non-Goals:**

- No agregar totales por procedimiento con IVA y sin IVA (solo con IVA, por decisión del usuario en `docs/cambios.md`).
- No incluir tipos de procedimiento sin contratos en los resultados (entregas vacías no se emiten; igual que `by_fiscal_year`).
- No cambiar el contrato de `embed=totals` ni la paginación.
- No modificar otros módulos ni la API de `procedimientos`.

## Decisions

1. **Forma de la respuesta: arreglo estructurado `by_procedure`, no claves planas.**
   - Elegido: `[{"procedure_id": n, "procedure_name": "...", "total_amount": "..."}]`.
   - Alternativa (rechazada): claves planas por tipo (`"licitacion_publica": 123456`) como en el ejemplo de `docs/cambios.md`. Se rechaza porque los tipos viven en `c_procedures` (datos dinámicos y editables, nombres con espacios/acentos: requiere slug inestable si se renombra un registro) y porque rompería la consistencia con `by_fiscal_year`, que ya se aceptó con arreglo estructurado en los items 1 y 2 del checklist. El usuario eligió esta opción explícitamente.
2. **Implementación: nueva entrada en `totals.groups` del módulo, sin tocar el motor.**
   - Elegido: declarar `'by_procedure' => ['group_by' => ['c.procedure_id', 'p.name'], 'fields' => [...], 'sums' => ['total_amount' => 'c.total_amount']]` en `app/api/contracts/controller.php`; `getTotals()` ya la procesa (grupo sin `nest` → clave top-level).
   - Alternativa (rechazada): consulta SQL nueva en el componente o en el ORM. Solo sería necesaria si el motor de grupos no cubriera el caso — sí lo cubre, y la vía declarativa reutiliza filtros, joins y búsqueda sin duplicar lógica (misma razón por la que existieron los grupos anteriores).
3. **Suma solo `total_amount` (con IVA).** Lo pide el documento de cambios; `subtotal` no se agrega al grupo para no ensuciar el contrato. Los totales generales `subtotal`/`total_amount` siguen intactos.
4. **Nombre de clave `by_procedure`** — coherente con `by_fiscal_year` y con los campos `procedure_id`/`procedure_name` ya existentes en el módulo.
5. **OpenAPI se regenera con `tools/generate-openapi.php`** en `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json`; `tests/OpenApiSyncTest.php` exige que ambas copias sean byte-idénticas. La descripción del parámetro `embed` y del schema `totals` se actualiza para mencionar los totales por tipo de procedimiento.

## Risks / Trade-offs

- [GROUP BY con columna de tabla unida (`p.name`)] → se agrupa por `c.procedure_id, p.name` juntos; MySQL acepta la expresión y `ORDER BY` el primer grupo (`c.procedure_id`) mantiene salida determinista. Si `ONLY_FULL_GROUP_BY` quejara, `p.name` es funcionalmente dependiente de la clave primaria `p.id` y ya está en el `group_by`.
- [Tipos de procedimiento sin fila en `c_procedures` (join no matchea)] → el join es el mismo que usa el listado y las sumas generales, así que `by_procedure` es consistente con el resto del bloque; un contrato huérfano aparecería con `procedure_name: null` en su grupo, no desaparece de `results`.
- [Costo: una consulta SQL extra por petición con `embed=totals`] → ya se pagan una por grupo; el group-by sobre `contratos` con join a `c_procedures` es barato y solo se ejecuta cuando se pide el embed.
- [Contrato del frontend] → aditivo; campos existentes no cambian de nombre ni forma, por lo que no es **BREAKING**.

## Migration Plan

Sin migración de datos ni de esquema. Despliegue: código + OpenAPI regenerado en el mismo commit. Rollback: revertir el commit (los clientes ignoran campos desconocidos; eliminar el campo es retrocompatible para lectores tolerantes).

## Open Questions

<!-- ninguna: la forma de la respuesta la decidió el usuario; el alcance lo define docs/cambios.md -->
