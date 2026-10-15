# Design

## Context

Evidencia del worktree y del código (hallazgo F-011 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`): `ls -la app/.keys` muestra `-rw-r--r--` para `jwtRS256.key` y `drwxr-xr-x` para `app/.keys/`, pese a que `setup.sh:31-35` hace `mkdir -p app/.keys` + `chmod 600 app/.keys/jwtRS256.key`; `logs/` es `drwxrwxrwx` y sus ficheros `-rwxrwxrwx`; `log.manager.php:15` hace `mkdir(DEBUG_LOG_PATH, 0777)` y `log.manager.php:19` hace `chmod($lfile, 0777)` donde `$lfile` es el recurso devuelto por `fopen()`, no la ruta — la corrección no llega a aplicarse; `image-upload/controller.php:17` y `attachments/controller.php:17` declaran `"permissions" => 0777`, consumidos por `mkdir($folder, $permissions, TRUE)` / `chmod($folder, $permissions)` en `app/core/components/image_upload/create_file_folder.php`. Ver motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: fijar los modos requeridos (0600/0700/0750/0640/0755), hacer que se apliquen de verdad (chmod sobre la ruta), hacer la aplicación idempotente y detectar deriva con un comando verificable.
- **Non-Goals**: el **contenido**, la privacidad o el volcado de secretos en los logs (F-015, cambio `improve-security-logging`); rotar la clave JWT; cambiar el usuario con el que corre php-fpm en el contenedor; políticas SELinux/AppArmor; permisos en sistemas que no sean POSIX.

## Decisions

1. **Detección de deriva por comando del proyecto (`make check-perms`) + reparación idempotente en el instalador, no por comprobación en cada petición** — no existe CI (no hay `.github/workflows`).
   - *Alternativa A*: comprobación de permisos en el bootstrap de cada petición — rechazada: añade I/O de `stat` a cada request, solo detecta la deriva cuando la API ya está en marcha y obliga a fallar (o a ignorar) dentro del ciclo de vida de la respuesta.
   - *Alternativa B*: workflow de CI que verifique modos — rechazado por F-010/design: no hay runner; además Git no puede guardar 0600, así que el propio checkout del CI fallaría siempre sin una reparación previa.
   - Elección: script de verificación en `tools/` invocado desde `make check-perms` (parte de la rutina de verificación local) y `setup.sh` re-aplica los modos en cada ejecución. Como `setup.sh` es interactivo (pide BD/credenciales) e instala dependencias, la parte de claves se extrae a su propio paso idempotente invocado por `setup.sh`, de modo que pueda ejecutarse y verificarse aisladamente. **Asunción material**: sin CI, la detección depende de que el comando se ejecute en la verificación; queda escrito en `tasks.md`.
2. **Modos exactos: clave 0600, `app/.keys/` 0700, directorio de logs 0750, ficheros de log 0640, subidas 0755** — el proceso que sirve la API es el único que necesita leer la clave y escribir logs; 0750/0640 dejan lectura al grupo para un colector de logs sin exponerlo a otros usuarios; 0755 da acceso de lectura/ejecución a todos y escritura solo al dueño (necesaria para los uploads servidos por nginx). **Asunción material**: una sola cuenta de servicio (php-fpm) posee app, logs y subidas; si otro usuario del contenedor necesita leer logs, se comparte grupo y se mantiene 0640/0750.
3. **Aplicar el `chmod` a la ruta, y `chmod` explícito tras el `mkdir`** — `mkdir($dir, $mode)` queda enmascarado por el `umask` (p. ej. 0022 nunca oculta permisos, pero un umask restrictivo sí), y el `chmod` actual sobre el handle de `fopen` no funciona (además, en PHP 8 un recurso como parámetro `string` es error fatal). Se decide: crear con el modo deseado **y** fijarlo explícitamente sobre la ruta para garantizar el valor observable final.
4. **El repo no puede garantizar 0600 (solo Git conserva el bit de ejecución)** — por eso el worktree muestra 0644 aunque `setup.sh` ordene `chmod 600`. La garantía tiene que ser procedural (instalador/verificación), no versionada. **Asunción material**: la clave se genera en el servidor con `setup.sh`, no se commitea.
5. **Alcance: solo permisos** — la F-015 (`improve-security-logging`) es dueña del contenido de los logs y de los secretos en URLs; este cambio no toca qué se escribe, solo con quién se comparte. La corrección del `chmod` sobre la ruta es compartida: este cambio la especifica como comportamiento observable de permisos, y `improve-security-logging` solo exige que un fallo de logging no mate la petición.

## Risks / Trade-offs

- [Un proceso de otro usuario (nginx, agente de métricas, `crontab` de backups) perdía acceso a `logs/` o a los uploads] → mitigación: 0750/0640 conservan el acceso por grupo; verificar en el contenedor el usuario efectivo de php-fpm y nginx antes de desplegar y, hace falta, alinearlos en el mismo grupo.
- [`umask` del contenedor enmascara el modo pedido] → mitigación: `chmod` explícito tras `mkdir` + `make check-perms` sobre el estado real.
- [Corregir permisos sobre ficheros creados por otro usuario puede requerir privilegios] → mitigación: la verificación informa de la ruta y el paso de mantenimiento (`setup.sh`) la repara; no se intenta reparar dentro de la petición.
- [Los tests corren fuera del contenedor, con otro usuario/umask] → mitigación: los tests de permisos usan directorios temporales propios y afirman el modo final observable, no el proceso.

## Migration Plan

1. Desplegar el código (modos correctos en creación) y ejecutar `setup.sh` o `make check-perms` + reparación manual sobre el worktree ya existente para cerrar la deriva actual (`chmod 600 app/.keys/jwtRS256.key; chmod 700 app/.keys; chmod 750 logs; chmod 640 logs/*; chmod -R o-w statics/uploads`).
2. Rollback: revertir el commit; los permisos más restrictivos no dejan estado de datos y no afectan a la API — solo se restauran los modos si algún proceso externo lo requería.

## Open Questions

- _(ninguno — los modos exactos, el punto de detección y el alcance (solo permisos) quedan decididos arriba; si un segundo usuario del contenedor necesita leer logs, se ajusta el grupo, no los requisitos)_
