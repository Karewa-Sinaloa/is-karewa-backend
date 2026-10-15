# Design

## Context

`composer audit` (ejecutado sobre este worktree) confirma 5 avisos en 2 paquetes: `smarty/smarty` v4.5.6 — CVE-2026-62993 y CVE-2026-62992 (ambos `<4.5.7`, severidad media) — y `symfony/yaml` v5.4.45 — CVE-2026-45304, CVE-2026-45305 y CVE-2026-45133 (todos `<5.4.52`, severidad baja). `composer.json` ya permite ambos parches con los rangos actuales (`smarty/smarty: ^4.1`, `symfony/yaml: ^5.4`). No existe CI: no hay `.github/workflows`, y el `Makefile` solo define `test` (PHPUnit en el contenedor `php`) y `composer-install`. Ver hallazgo F-010 en `docs/auditoria-seguridad/auditoria-owasp-2026-10-14.md`. Ver motivación en `proposal.md` — Why.

## Goals / Non-Goals

- **Goals**: dejar `composer audit` en cero avisos; hacer que la auditoría sea un paso verificable del rutina de verificación del proyecto; fijar una política de parcheo por severidad.
- **Non-Goals**: crear infraestructura de CI/CD (no existe runner ni workflows); auditar dependencias no-PHP (imágenes Docker, npm del frontend); actualizaciones mayores de paquetes (Symfony 6/7, Smarty 5) que amplíen rangos; rotación de advisories transitorios de herramientas de desarrollo fuera del lock.

## Decisions

1. **Puerta de auditoría como objetivo del `Makefile` (`make audit`), no como workflow de CI** — dado que no existe `.github/workflows` ni otro runner.
   - *Alternativa A*: añadir un workflow de GitHub Actions con `composer audit` — rechazada: no hay infraestructura de CI que lo ejecute, y el workflow sería código muerto que da una falsa sensación de control (mismo patrón que F-009).
   - *Alternativa B*: incrustar la auditoría dentro de la suite PHPUnit — rechazada: PHPUnit no debe depender de red ni de la base de advisories externa; un fallo de red rompería la suite sin ser una regresión real.
   - Elección: objetivo `make audit` que ejecuta `composer audit` dentro del contenedor `php` (mismo patrón que `make test`/`make composer-install`), documentado en `help`, `README.md` y `SECURITY.md`. Si en el futuro existe CI, se añade el mismo comando allí sin cambiar esta spec (la spec exige "un comando documentado del proyecto", no un runner concreto).
2. **Actualización dentro de los rangos existentes, sin tocar `composer.json`** — `composer update smarty/smarty symfony/yaml --with-dependencies` actualiza a 4.5.7+ / 5.4.52+ respetando `^4.1` / `^5.4`.
   - *Alternativa A*: ampliar rangos a versiones mayores — rechazada: cambiaría la superficie de la librería y obligaría a revisar plantillas Smarty y parseo YAML con riesgo injustificado para avisos de severidad media/baja.
   - Elección: mínima actualización parche/minor; si un aviso futuro no tuviera arreglo dentro del rango, este cambio marcaría **BREAKING** en la propuesta antes de ampliarlo.
3. **Política de parcheo por severidad (asunción material)**: crítico/alto → parche inmediato, bloquee la release en curso; medio → como máximo en la siguiente release (≤ 30 días desde la publicación del aviso); bajo → en la siguiente release menor (≤ 90 días). Los 5 avisos actuales (2 medios, 3 bajos) entran en la ventana de 30 días. Si negocio exige otra cadencia, cambia solo `design.md` y la documentación, no los requisitos de spec.
4. **Fuente de advisories: la base oficial de Composer (Packagist/GitHub)** — se asume acceso a red desde el entorno de verificación (el propio `composer audit` lo necesita). No se implementa caché local de advisories.

## Risks / Trade-offs

- [Una actualización de Smarty/YAML rompe el renderizado de plantillas o el parseo de configuración] → mitigación: la suite completa (`./vendor/bin/phpunit`) es verificación obligatoria del grupo 1, más smoke manual del endpoint de plantillas `mailings/login-recovery` y del arranque que parsea YAML.
- [La auditoría necesita red y puede fallar por indisponibilidad del servicio de advisories] → mitigación: distinguir explícitamente "auditoría no ejecutable" (fallo ruidoso, no silencio) de "cero avisos"; no se trata un error de red como aprobación.
- [Aparecen avisos en otros paquetes después de este cambio] → mitigación: la puerta `make audit` falla y el trabajo entra por el mismo ciclo de la política de parcheo; queda fuera de este cambio concreto.
- [Desfase entre lo auditado en local y el `composer install` del despliegue] → mitigación: el lock commiteado es la fuente; `make composer-install` instala exactamente ese lock.

## Migration Plan

Cambio de lock sin migración de datos ni de esquema. Despliegue: `make composer-install` (instala el lock actualizado). Rollback: revertir el commit de `composer.lock` y reinstalar; no deja estado persistido.

## Open Questions

- _(ninguno — la ausencia de CI, la política de parcheo y el alcance de la auditoría quedan decididos arriba; nada de lo diferido cambiaría specs ni tareas)_
