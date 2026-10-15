# Design

## Context

El módulo `users` expone `store` (público, rol forzado 5 o corregido según caller) y `update` (roles 1-5). `store()` ya contiene la guarda anti-escalada (fuerza rol 5 a anónimos/roles > 3 y corrige `role_id < USER_ROLE` a `USER_ROLE`); `update()` solo hashea la contraseña y delega en el trait CRUD. El campo `role_id` se declara con `saved` por defecto y sin lista `'roles'`, por lo que `queryFields()` lo incluye sin filtro. El filtro de fila para roles > 3 es `id = USER_ID`, de modo que un usuario común edita su propia fila —incluido `role_id`—. Ver hallazgo F-001 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`. Ver motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: cerrar la escalada de privilegios por `role_id` en `update`; aplicar disciplina uniforme a `status_id`; mantener el comportamiento legítimo de `store` sin regresiones.
- **Non-Goals**: rediseñar el modelo de roles, cambiar qué roles pueden editar qué filas (regla existente `role_id >= USER_ROLE` para roles ≤ 3), tocar el flujo de auto-login tras `store`, o cambiar la política de contraseñas (cubierta por F-018 en otro cambio).

## Decisions

1. **Guarda en `update()` espejando la de `store()` + restricción declarativa en `$moduleFields`** (defensa en profundidad) sobre solo una de ellas.
   - *Alternativa A*: solo guarda en `update()` — un olvido futuro en `queryFields` reabre la vía.
   - *Alternativa B*: solo `'roles'` declarado en `$moduleFields` — la guarda de `store()` ya existe y coexistirían dos mecanismos con semántica distinta; además `queryFields` del middleware no aplica listas de roles a la escritura.
   - Elección: ambos — la guarda de `update()` es el control efectivo; la declaración documenta y filtra el campo en lecturas/futuras vías de escritura.
2. **Corrección en silencio vs. rechazo 4xx al intentar escalar**: se corrige al valor permitido (mismo comportamiento que `store()` actual) en vez de rechazar, para no cambiar el contrato de respuesta de `update` más allá del efecto sobre los campos. Si se prefiere rechazo explícito, cambiar los escenarios de spec antes de implementar.
3. **`status_id` escribible solo por roles 1-3** (asunción): la auditoría solo pide "validar con la misma disciplina"; se asume que el cambio de estado es operación administrativa. Queda registrado aquí como decisión de diseño.

## Risks / Trade-offs

- [Clientes que actualizan `role_id` legítimamente desde la UI] → la UI solo envía cambios de rol desde pantallas administrativas; verificar con el frontend (repo separado) antes de desplegar.
- [Corrección silenciosa enmascara intentos de ataque] → mitigación: registrar el evento de escalada intentada en logs de seguridad (sin exponer el intento al caller); se solicita en tasks.
- [Doble guarda (controlador + declaración) puede divergir] → la declaración `'roles'` se deriva de la misma regla; añadir test que fije ambos.

## Migration Plan

Despliegue sin migración de datos (no hay cambio de esquema). Rollback: revertir el commit; no deja estado persistido.

## Open Questions

- _(ninguno — la regla exacta de `status_id` se decidió arriba; si negocio define otra, ajustar el escenario de spec correspondiente antes de implementar)_
