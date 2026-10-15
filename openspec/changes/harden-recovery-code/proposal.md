# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-006 (Medio)**: el código de recuperación se genera con `mt_rand(100000, 999999)` (app/core/modules/access/local_login.php:170) — PRNG no criptográfico y espacio de solo 10⁶ — y la verificación en `Reset` (app/core/modules/access/local_login.php:269-273) no lleva contador de intentos fallidos: dentro de la ventana de 1 h (`REC_CODE_TIME: 3600`) solo el rate limit global del endpoint (10/min) acota la fuerza bruta.

## What Changes

- El código de recuperación se genera con una fuente de aleatoriedad criptográfica (`random_int`); se elimina el PRNG no criptográfico.
- Los intentos de verificación fallidos por código/cuenta se limitan a 5; al superarlos el código queda invalidado y cualquier intento posterior se rechaza hasta solicitar uno nuevo.
- El código queda invalidado tras su uso exitoso (uso único).
- Se mantiene la vigencia existente del código (`REC_CODE_TIME`, 3600 s) ya especificada operativamente.
- No se modifica el formato del código (numérico de 6 dígitos) ni la columna `recovery_code`: la capacidad existente `access-recovery-fields` no cambia (el hash y las columnas se mantienen; la decisión y su justificación están en design.md).

## Capabilities

### New Capabilities

- `recovery-code`: generación criptográficamente fuerte, límite de intentos, uso único y vigencia de los códigos de recuperación de cuenta.

### Modified Capabilities

- _(ninguna — `access-recovery-fields` no cambia: su contrato de columnas/hash sigue siendo correcto)_

## Impact

- Código: `app/core/modules/access/local_login.php:170` (generación), `:269-273` (verificación y nuevo contador), posible nueva columna/migración para el contador de intentos (`resources/migrations/`).
- API: tras 5 códigos incorrectos, un `POST /api/v5/access/reset` con el código correcto también se rechaza (nuevo comportamiento observable); el resto de respuestas no cambien.
- Tests: `tests/AccessFieldMappingTest.php` como referencia del patrón; tests nuevos de generación, límite de intentos y uso único (SQLite in-memory, patrón de `tests/AccessLogoutTest.php`).
