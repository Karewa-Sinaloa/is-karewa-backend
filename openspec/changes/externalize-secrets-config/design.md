# Design

## Context

`app/config.yml` está gitignored (regla 3 de `.gitignore`) pero se mantiene en claro en el árbol de trabajo con valores reales: `database.password` (:26), `mailing.password` —API key de SendGrid— (:99), `hash` —secreto HMAC de hash-auth, leído como `HASH_AUTH_PASS` en app/core/config/base.php:188— (:120) y `hcaptcha.secret` (:124, usado en app/core/helpers/hcaptcha.php:47). `MYSQL_PSWD` se define en base.php:130 desde el archivo. `.env` (gitignored) ya transporta `DB_PASSWORD`/`DB_ROOT_PASSWORD` para docker-compose, pero la aplicación no los lee. Existe el flujo `git-secret` (`app/config.yml.secret`, revelado por `setup.sh`) y el patrón overlay de variables de entorno para correo (`app/core/config/mail_env.php`, requisito existente "Mail settings can be overridden by the environment"). Ver hallazgo F-002 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`; motivación en proposal.md - Why.

## Goals / Non-Goals

- **Goals**: que los cuatro secretos de ejecución lleguen por variable de entorno o por el flujo revelado de git-secret; que ni el árbol de trabajo ni el repositorio contengan valores secretos reales; dejar documentada y verificable la rotación de las credenciales expuestas.
- **Non-Goals**: implementar la rotación en código (es un paso de operaciones); rediseñar el overlay de correo ya resuelto; externalizar las claves JWT de `app/.keys/` (tratadas por F-011 en `restrict-file-permissions`); gestionar secretos con un vault externo.

## Decisions

1. **Overlay de variables de entorno con prelación entorno > archivo revelado**, reutilizando el patrón ya probado por `mail_env.php`.
   - *Alternativa A*: solo git-secret — quien no ejecuta `setup.sh` (CI, arranque rápido) queda sin valor seguro y no hay parametrización por entorno.
   - *Alternativa B*: solo variables de entorno — rompe el flujo revelado que el proyecto ya opera y obliga a migrar todas las instalaciones de golpe.
   - Elección: ambas, coherente con el requisito de correo existente y con migración incremental.
2. **Nombres de variables** (asunción material): `DB_PASSWORD` (reutiliza la variable ya presente en `.env`), `SENDGRID_API_KEY`, `HCAPTCHA_SECRET`, `HASH_AUTH_SECRET`. El mapa completo se documenta en `.env.example`; si negocio u operaciones prefieren otros nombres, cambiar antes de implementar.
3. **Fallo seguro ante secreto ausente**: si una operación requiere un secreto que ni el entorno ni el archivo revelado proveen, se produce un error de configuración en esa operación en lugar de arrancar con un placeholder. *Asunción material*: no se aborta el arranque completo (la app puede servir salud/otros módulos), pero nunca se usa un valor por defecto.

## Risks / Trade-offs

- [Instalaciones existentes dependen del `config.yml` en claro] → mitigación: el despliegue define las variables en su `.env` (o regenera el archivo revelado) antes de actualizar; tasks incluye verificación con el stack local.
- [Las credenciales expuestas siguen siendo válidas hasta rotar] → mitigación: la rotación es grupo propio de tasks con verificación explícita; el cambio solo reduce exposición futura, no la pasada.
- [Superposiciones futuras pueden olvidar externalizar un secreto nuevo] → mitigación: el requisito de spec prohíbe valores reales en cualquier configuración en claro; revisar en cada PR que agregue configuración.

## Migration Plan

1. Definir en el entorno de despliegue las variables de la decisión 2 **con valores ya rotados**.
2. Sustituir los secretos de `app/config.yml` por placeholders y regenerar `app/config.yml.secret` con git-secret.
3. Desplegar y verificar: login, envío de correo, verificación hCaptcha y hash-auth funcionan con las variables.
Rollback: revertir el commit de configuración; las variables de entorno son aditivas y no rompen la versión anterior.

## Open Questions

- _(ninguno — nombres de variables y comportamiento ante secretos ausentes se resolvieron en Decisions 2 y 3)_
