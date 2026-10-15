# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-007 (Medio)**: `app/config.yml:8` tiene `development: true` y `app/core/config/base.php:53-56` vincula ese flag directamente a `ini_set('display_errors', ...)` y `display_startup_errors`. El archivo de configuración desplegado en este entorno vive en claro en el árbol de trabajo, de modo que cualquier warning/fatal puede volcarse al cliente con rutas internas (`/var/www/html/...`), fragmentos de SQL y estado interno.

## What Changes

- El modo desarrollo deja de guardarse como `true` en la configuración productiva: se controla por variable de entorno de despliegue, con valor por defecto **apagado**.
- **BREAKING**: la clave `development` de `app/config.yml` deja de ser la fuente del modo; quien necesite desarrollo debe definir la variable de entorno en su entorno local.
- Con el modo desarrollo apagado, ninguna respuesta expone trazas de pila, rutas internas ni fragmentos SQL (`display_errors`/`display_startup_errors` deshabilitados); el detalle de errores solo llega al log de errores configurado.

## Capabilities

### New Capabilities

- _(ninguna)_

### Modified Capabilities

- `configuration-and-logging`: se añaden requisitos del modo desarrollo controlado por entorno (apagado por defecto) y de no exposición de detalles internos en las respuestas con el modo apagado.

## Impact

- Código: `app/core/config/base.php` (resolución del flag y `ini_set` de `display_errors`, `display_startup_errors`).
- Configuración: `app/config.yml` (clave `development` a `false`/eliminada), `.env` y `.env.example` (documentación de la variable de entorno).
- API: respuestas ante errores inesperados con el modo apagado pasan a envelope estándar sin detalle interno; el detalle sigue disponible en `logs/`.
