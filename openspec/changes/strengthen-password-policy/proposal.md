# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-018 (Bajo)**: no hay una única política de contraseñas. El alta exige mínimo 8 caracteres (`app/core/modules/users/controller.php:100`, `required|min:8`), pero la actualización usa la regla de clase `password => min:6` (`app/core/modules/users/controller.php:62`), con lo que cualquier usuario puede **debilitar** su contraseña a 6 caracteres sin complejidad; el restablecimiento usa `min:8` (`app/core/modules/access/local_login.php:239`) y la verificación de login `min:6` (`app/core/modules/access/local_login.php:41`). En conjunto, la política es débil e inconsistente y debilita la resistencia a fuerza bruta offline si la base de datos se filtra.

## What Changes

- Una sola política de contraseñas, aplicada de forma consistente en los tres puntos donde una contraseña se **fija**: alta de usuario (`store`), actualización (`update`) y restablecimiento (`Reset`).
- La actualización no puede dejar la contraseña por debajo de la política (hoy permite bajar a 6 caracteres).
- La misma política sin importar el rol del caller (anónimo en el alta, administrador editando a otro, usuario restableciendo su propia contraseña).
- **BREAKING**: contraseñas de menos de 10 caracteres dejan de aceptarse en alta, actualización y restablecimiento. Los usuarios existentes con contraseñas más cortas **siguen pudiendo iniciar sesión** (la verificación de credenciales no aplica la política de creación) hasta que actualicen su contraseña.
- Decisión de diseño (detalle en `design.md`): mínimo de **10** caracteres, **sin** reglas de complejidad (mayúsculas/dígitos/símbolos), **con** bloqueo de contraseñas comunes.

## Capabilities

### New Capabilities

- `password-policy`: una única política de contraseñas aplicada en todos los puntos donde se aceptan (alta, actualización, restablecimiento), independientemente del rol del caller, sin permitir debilitar una contraseña existente.

### Modified Capabilities

- _(ninguna)_

## Impact

- Código: `app/core/modules/users/controller.php:62` (regla de clase `min:6`) y `:100` (regla de `store`), `app/core/modules/access/local_login.php:239` (`Reset`); `app/core/modules/access/local_login.php:41` (`Login`) queda **intencionadamente sin subir** el mínimo para no bloquear credenciales existentes; posible fuente única de la política (constante/helper compartido) y recurso de contraseñas comunes.
- API: respuestas `400000` de validación en `POST /api/v5/users`, `PUT /api/v5/users/{id}` y el reset de `access` cuando la contraseña nueva no cumpla la política.
- Tests: test nuevo (p. ej. `tests/PasswordPolicyTest.php`) que fija la política en los tres flujos y prueba que el login de credenciales existentes no cambia.
