# Design

## Context

`app/core/modules/attachments/index.php:10` declara `'store' => [false, NULL]` —autenticación deshabilitada—, frente a `'store' => [true, [1, 2, 3]]` en `image-upload` (app/core/modules/image-upload/index.php:8). El `store()` del controlador recorre `$_FILES`, valida solo el tipo (F-005) y persiste con `move_uploaded_file` en una carpeta por archivo creada con `time() + $key` (app/core/modules/attachments/controller.php:42-56), sin registrar propiedad ni aplicar tamaño o cuota. `ModuleHandler::Validate()` (app/core/auth/module.access.controller.php) ya rechaza tokens ausentes con el código estándar 901004 antes de ejecutar el método. El código 906000 (HTTP 413) ya existe para exceso de tamaño (app/core/config/api_codes.yml:121). Ver hallazgo F-003 en la auditoría; motivación en proposal.md - Why.

## Goals / Non-Goals

- **Goals**: cerrar el acceso anónimo a `attachments.store`; aplicar cuota por cliente y límite de tamaño por archivo con errores claros y sin efectos parciales.
- **Non-Goals**: validar tipo, extensión, contenido o nombre del archivo (F-005, cambio `validate-uploaded-file-content`); cambiar cómo se sirven los archivos estáticos; añadir métodos `index`/`show`/`update`/`destroy` al módulo.

## Decisions

1. **Conjunto de roles `[1, 2, 3]`, idéntico a `image-upload`**, sobre un conjunto más estrecho (solo rol 1) o más amplio (1-5).
   - *Alternativa A*: solo rol 1 — rompe flujos de editores/operadores que hoy suben adjuntos desde el CMS autenticados.
   - *Alternativa B*: roles 1-5 — el hallazgo pide "rol mínimo"; los roles comunes no necesitan publicar archivos en el dominio del API.
   - Elección: coherencia con el módulo vecino y mínimo efectivo.
2. **Rechazo anónimo delegado en la validación de módulos** (declaración `[true, [1, 2, 3]]`) en vez de comprobar identidad dentro del método: un solo mecanismo, mismo error estándar 901004 y misma trazabilidad que el resto de la API.
3. **Cuota por usuario autenticado sobre los adjuntos registrados a su nombre**, con límite configurable en `app/config.yml`. Como el esquema actual no registra propiedad, el controlador pasará a asociar cada subida al `USER_ID` validado. *Asunción material*: solo se contabiliza lo subido a partir del despliegue; los archivos históricos sin dueño quedan fuera de la cuota para no romper datos existentes.
4. **Límite de tamaño por archivo reutiliza el código 906000 (HTTP 413)**; para cuota excedida se añade un código nuevo en `app/core/config/api_codes.yml` (p. ej. 906004, HTTP 413) para distinguirlo del exceso de tamaño. Números concretos (peso máximo y cuota) son configuración de despliegue, no de spec; partir de valores generosos.

## Risks / Trade-offs

- [El frontend/CMS que hoy sube adjuntos anónimamente dejará de funcionar] → mitigación: verificar los consumidores (repo del frontend) antes de desplegar; si existe un flujo legítimo anónimo, migrarlo a token de servicio.
- [La cuota configurable en un valor bajo rompe flujos legítimos] → mitigación: partir de un límite generoso y hacerlo configurable; el test fija el mecanismo, no el número.
- [Lo histórico sin propiedad no cuenta para la cuota] → mitigación: aceptado y documentado; una migración de datos para atribuir archivos antiguos queda fuera de alcance.
- [Chequear cuota requiere sumar tamaños en cada subida] → mitigación: caché/índice ligero si el volumen lo requiere; no afecta al contrato observable.

## Migration Plan

Despliegue sin migración de esquema (la propiedad puede vivir en metadatos de subida o tabla nueva ligera). **BREAKING** para clientes anónimos: coordinar con el frontend el envío de token antes de desplegar. Rollback: revertir el commit; no deja estado incompatible.

## Open Questions

- _(ninguno — el conjunto de roles, la semántica de la cuota y los códigos de error se resolvieron en Decisions; los números concretos de configuración son ajuste de despliegue)_
