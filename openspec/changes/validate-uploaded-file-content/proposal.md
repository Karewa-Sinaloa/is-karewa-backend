# Proposal

## Why

La auditoría OWASP 2026-10-14 confirma el hallazgo **F-005 (Medio)**: `Files::Validate()` decide el tipo permitido usando únicamente `$file["type"]`, la cabecera `Content-Type` controlada por el atacante (app/core/validation/files.php:40), sin inspeccionar firma ni extensión real. En `attachments` el archivo se guarda tal cual con un nombre que preserva letras y puntos (`preg_replace('/([^a-zA-Z0-9\.\r]+)/', '-', ...)`, app/core/modules/attachments/controller.php:24-32,50), de modo que `shell.php` o `nota.pdf.html` conservan su nombre. nginx limita la ejecución de PHP a `api.php`, lo que reduce el riesgo de RCE, pero el tipo engañado permite alojar contenido activo (HTML/JS embebido en PDFs u otros archivos servidos como estáticos) en el dominio del API.

## What Changes

- El tipo permitido se determina por lista blanca de extensiones **y** inspección del contenido real (sniffing de firma/MIME); la cabecera `Content-Type` del cliente deja de ser la decisión.
- El archivo se almacena con nombre aleatorio no adivinable (p. ej. UUID); el nombre original se conserva solo como metadato.
- Una subida rechazada produce error de validación sin persistir archivo ni directorio.
- Los archivos servidos se entregan como contenido estático no ejecutable (tipo fijado por el servidor, sin sniffing ni interpretación de scripts).
- **BREAKING**: la respuesta de la subida deja de exponer el nombre sanitizado como nombre del archivo almacenado (pasa a metadato), lo que afecta a clientes que regeneren URLs a partir del nombre.
- Alcance: los módulos que validan subidas (`attachments` y `image-upload`, ambos usuarios de `Files::Validate`).

## Capabilities

### New Capabilities

- `file-upload-integrity`: los archivos subidos se validan por su contenido real y se almacenan con nombres no adivinables, de modo que las subidas no puedan introducir contenido activo.

### Modified Capabilities

- _(ninguna)_

## Impact

- Código: `app/core/validation/files.php` (`Files::Validate`), `app/core/modules/attachments/controller.php` (`store()`, `uploadFile()`), `app/core/modules/image-upload/controller.php` (`store()`).
- API: respuesta del módulo `attachments` (campo `name` pasa a metadato; la URL servida usa nombre aleatorio) — coordinar con el frontend.
- Servido estático: cabeceras de los archivos bajo `statics/uploads/` (nginx/php-fpm) — `X-Content-Type-Options` ya activo vía `security.headers.nosniff`.
