# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-010 (Medio)**: `composer audit` reporta **5 avisos de seguridad en 2 paquetes instalados** — `smarty/smarty` v4.5.6 (CVE-2026-62993 SSRF vía `{fetch}`, CVE-2026-62992 traversal por symlink) y `symfony/yaml` v5.4.45 (CVE-2026-45304 Billion Laughs, CVE-2026-45305 ReDoS, CVE-2026-45133 stack exhaustion) — y el proyecto no tiene ningún proceso visible de actualización de dependencias: no hay workflows en `.github/workflows`, y el `Makefile` solo expone `test` y `composer-install`.

## What Changes

- `smarty/smarty` se actualiza a `>= 4.5.7` y `symfony/yaml` a `>= 5.4.52` dentro de los rangos ya declarados en `composer.json` (`^4.1`, `^5.4`), de modo que `composer audit` reporte **cero avisos conocidos**.
- Se añade una puerta de auditoría de dependencias verificable con un solo comando del proyecto (`make audit`, dado que no existe CI) y se documenta junto a los comandos de verificación existentes.
- Se fija la política de parcheo por severidad (diseño en `design.md`): una corrección de aviso de seguridad se libera, como máximo, en la siguiente release.
- No se esperan cambios de comportamiento de la API (**no BREAKING**): las actualizaciones son parche/minor dentro de los rangos ya permitidos. Si un aviso exigiera ampliar un rango de versión, este cambio marcaría **BREAKING** en esta sección antes de implementarse.

## Capabilities

### New Capabilities

- `dependency-auditing`: las dependencias PHP instaladas se mantienen libres de avisos de seguridad conocidos mediante una puerta de auditoría automatizada y verificable.

### Modified Capabilities

- _(ninguna)_

## Impact

- Código/lock: `composer.lock` (versiones de `smarty/smarty`, `symfony/yaml`), `composer.json` solo si un rango tuviera que ampliarse.
- Verificación: `Makefile` (nuevo objetivo `audit` + línea en `help`), `README.md` / `SECURITY.md` (documentación del comando y de la política de parcheo).
- Pruebas: ninguna regresión esperada en `./vendor/bin/phpunit`; la suite completa es la verificación de no-rotura.
- Dependencias externas: la auditoría necesita red (base de advisories de Packagist/GitHub).
