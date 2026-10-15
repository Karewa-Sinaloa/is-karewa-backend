# Design

## Context

`app/config.yml:8` define `development: true`; `app/core/config/base.php:53-56` hace `ini_set('display_errors', $_config->development)` y `display_startup_errors` con ese mismo valor, con `log_errors` activo y `error_log` apuntando a `logs/`. El archivo está gitignored pero presente en claro en el árbol de trabajo de despliegue, de modo que el modo producción refleja el flag del archivo. `.env` ya define `APP_ENV` (usado por docker-compose) pero la aplicación no lo consume para el modo desarrollo. El proyecto ya tiene `tests/ModuleFatalErrorsTest.php` como referencia del manejo de errores fatales. Ver hallazgo F-007 en la auditoría; motivación en proposal.md - Why.

## Goals / Non-Goals

- **Goals**: que el modo desarrollo esté apagado por defecto y se active solo por variable de entorno; que con el modo apagado ninguna respuesta filtre trazas, rutas internas o SQL, manteniendo el detalle en el log.
- **Non-Goals**: rediseñar la estrategia de logging (capa existente `error_logs()`); añadir páginas de error personalizadas; gestionar entornos con múltiples niveles (solo se distingue desarrollo apagado/encendido).

## Decisions

1. **Flag de entorno `APP_ENV`, con el modo desarrollo activo solo si vale `development`** (cualquier otro valor o ausencia ⇒ apagado), sobre reutilizar la clave `development` del archivo.
   - *Alternativa A*: mantener la clave en `app/config.yml` y que el entorno la sobrescriba — el archivo de despliegue ya demostró quedar en `true`; la fuente debe ser el despliegue, no el archivo.
   - *Alternativa B*: variable nueva dedicada (`DEVELOPMENT_MODE`) — más explícita, pero fragmenta la configuración cuando `APP_ENV` ya existe en `.env`. *Asunción material*: se reutiliza `APP_ENV` y su semántica actual no colisiona; si operaciones prefieren otra variable, cambiar el nombre aquí antes de implementar.
2. **El flag de entorno es la única fuente; la clave `development` de `app/config.yml` se elimina** (o se fija a `false` como placeholder inerte), de modo que editar el archivo productivo no pueda reactivar el modo.
3. **`display_errors` y `display_startup_errors` siguen amarrados al mismo flag resuelto**, con `html_errors` apagado cuando el modo está off para no inyectar marcado de error en respuestas; el detalle ya se escribe en `logs/` vía `error_log` y `error_logs()`.
4. **Respuesta al cliente con el modo off: envelope estándar de error** (patrón de `api-responses`), sin cuerpo de detalle; los errores fatales ya tienen manejo testeado (`tests/ModuleFatalErrorsTest.php`) que se extiende a esta garantía.

## Risks / Trade-offs

- [Developers locales dependían de `development: true` en su `config.yml`] → mitigación: documentar la variable en `.env.example`; el valor por defecto apagado es el seguro y el arranque local la define.
- [Dificultad de debugging en local si algo falla con el modo off] → mitigación: el flag existe para eso; además `logs/` siempre conserva el detalle.
- [Un despliegue olvide la variable y crea que el modo está activo] → mitigación: es el caso deseado (default seguro); la verificación de tasks comprueba que sin variable el display está apagado.

## Migration Plan

1. Actualizar `app/config.yml` del despliegue (quitar `development: true`) y definir `APP_ENV=development` solo en entornos locales.
2. Desplegar y verificar que el log de errores recibe el detalle mientras las respuestas muestran envelope estándar.
Rollback: revertir el commit; la clave del archivo vuelve a existir y el comportamiento anterior se restaura.

## Open Questions

- _(ninguno — variable de entorno, fuente única y formato de respuesta se resolvieron en Decisions 1, 2 y 4)_
