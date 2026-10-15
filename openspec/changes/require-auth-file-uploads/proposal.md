# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-003 (Alto)**: el método `store` del módulo `attachments` se declara con autenticación deshabilitada (`'store' => [false, NULL]`, app/core/modules/attachments/index.php:10), mientras que el módulo vecino `image-upload` exige `[true, [1, 2, 3]]`. Cualquier cliente anónimo puede subir archivos (imágenes/PDF) al servidor: abuso de disco y anchos de banda, y alojamiento de contenido malicioso en el dominio de confianza del API. Además no existe límite de tamaño por archivo ni cuota por cliente, de modo que el almacenamiento es ilimitado desde el exterior.

## What Changes

- El método `store` de `attachments` exige autenticación con el mínimo de roles administrativos `[1, 2, 3]`, igual que `image-upload`.
- **BREAKING**: una subida anónima recibe el error estándar de autenticación (401) y no se procesa ningún archivo; un cliente con rol fuera del conjunto recibe el error estándar de autorización.
- Se aplica límite de tamaño por archivo (código existente 906000, HTTP 413) y cuota de almacenamiento por cliente autenticado, cada uno con código de error claro y sin persistir nada cuando se rechaza.
- Fuera de alcance: validación de tipo/contenido y de nombre de archivo (F-005, cambio `validate-uploaded-file-content`).

## Capabilities

### New Capabilities

- `file-upload-access`: quién puede subir archivos adjuntos y bajo qué límites de cliente se aceptan las subidas.

### Modified Capabilities

- _(ninguna)_

## Impact

- Código: `app/core/modules/attachments/index.php` (declaración de `store`), `app/core/modules/attachments/controller.php` (`store()`, `uploadFile()`).
- Configuración: `app/config.yml` (límites de tamaño y cuota), posiblemente `app/core/config/api_codes.yml` (nuevo código para cuota excedida).
- API: `POST` del módulo `attachments` deja de ser anónimo — los consumidores (frontend Vue, repo separado) deben enviar token.
