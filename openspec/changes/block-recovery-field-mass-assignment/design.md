# Design

## Context

`app/core/modules/users/controller.php` define `recovery_code` (línea 28) y `recovery_date` (línea 29) en `$moduleFields` con `filter/listed` en `false` pero sin `'saved' => false` (a diferencia de `hcaptcha_bypass`, que sí lo incluye). El middleware `queryFields` (app/core/bootstrap/midelware.php:458) descarta un campo de los payloads cuando `(!$field_exists && $field['optional']) || (REQUEST_TYPE=='update' && ... empty) || ($field['roles'] && ...) || !$field['saved']`, con defecto `saved => true` (línea 485); ambos campos son `optional => true`, así que si el cliente los envía en `store`/`update` se persisten. Un atacante puede escribir un `recovery_code` que sea el hash bcrypt de un código que elige y un `recovery_date` reciente, y completar `access/reset` sin recibir el correo. Ver F-014 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md` y el porqué en `proposal.md` — Why.

Los requisitos existentes de `openspec/specs/access-recovery-fields/spec.md` describen qué escribe el flujo `access` al emitir/consumir un código y **no cambian**: este cambio añade requisitos sobre quién puede escribir desde cliente.

## Goals / Non-Goals

- **Goals**: que `recovery_code`/`recovery_date` no sean persistibles desde payloads cliente de `users` (`store` y `update`); que el flujo `access` sea el único escritor; que ningún texto plano llegue a persistir.
- **Non-Goals**: cambiar la semántica de columnas existente ni los requisitos ya especificados de la capacidad; reformar `queryFields`; auditar `$moduleFields` de otros módulos (posible hallazgo aparte); modificar el hash del flujo `access` (ya usa hash de un solo sentido).

## Decisions

1. **Añadir `'saved' => false` a ambas definiciones (patrón `hcaptcha_bypass`).**
   - *Alternativa A*: filtrar los campos en `store()`/`update()` del controller con lógica ad-hoc — se descarta: duplica la protección en dos sitios, se olvida al añadir campos nuevos, y `queryFields` ya es el punto declarativo único de "qué se escribe".
   - *Alternativa B*: eliminar las definiciones de `$moduleFields` — se descarta: la definición también alimenta las consultas/lecturas del módulo; `saved => false` solo corta la escritura y conserva el resto del comportamiento (incluido `listed => false` que ya oculta los campos en respuestas).
   - *Alternativa C*: aceptar el valor pero hashearlo antes de persistir — se valora y descarta: dejaría un segundo escritor de las columnas y permitiría envenenar la ventana de recuperación con la fecha que elija el cliente.
   - Elección: `saved => false` cubre `store` y `update` a la vez porque el skip de `queryFields` no distingue `REQUEST_TYPE`.
2. **Garantía de "siempre hash" estructural, no validada**: al ser `access` el único escritor y usar ese flujo hash de un solo sentido con coste por defecto, no hace falta validar el formato al escribir. *Alternativa*: comprobar en cada escritura que el valor tiene forma de hash — se descarta: tras la decisión 1 no existe ningún escritor legítimo que validar.
3. **Confirmar la ausencia de uso legítimo** mediante una prueba que envía los campos en `store` y `update` y verifica que nada cambia; si una prueba existente de la suite dependiera de escribirlos, esa dependencia sería un bug de F-014 y se corregiría la prueba, no el flag. **Asunción material**: ningún cliente legítimo envía estos campos (el frontend no lo hace; el único uso posible era el de F-014) — por eso se marca **BREAKING** en la propuesta.

## Risks / Trade-offs

- [Un cliente que enviara estos campos de forma "legítima" deje de poder hacerlo] → mitigación: marcado **BREAKING** en la propuesta; nota en el changelog del despliegue; rollback = revertir el commit.
- [Otros módulos con `saved` ausente en columnas sensibles] → mitigación: fuera de alcance (F-014 solo cita `users`); se recomienda auditoría de `$moduleFields` similares como cambio aparte.
- [La protección depende de que `queryFields` siga aplicando `saved` en ambos tipos de request] → mitigación: pruebas explícitas para `store` y para `update`; el comportamiento de `queryFields` ya está cubierto por `AccessFieldMappingTest`/`UsersFieldMappingTest`.

## Migration Plan

1. Despliegue del código: sin migraciones ni cambios de configuración.
2. Tras despliegue: verificar con la suite (`./vendor/bin/phpunit`) que ningún flujo existente escribía estas columnas desde `users`.
3. Rollback: revertir el commit (restaura la escritura masiva, reintroduce F-014).

## Open Questions

- _(ninguno — las alternativas de filtrado quedaron resueltas en la decisión 1; cambiarla alteraría los requisitos de spec y las tareas)_
