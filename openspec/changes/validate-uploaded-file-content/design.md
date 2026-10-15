# Design

## Context

`Files::Validate()` (app/core/validation/files.php:31-41) solo compara `$file["type"]` —cabecera `Content-Type` del cliente— contra una regex de formatos pasada por el módulo (`attachments`: `(jpe?g|png|pdf)`; `image-upload`: `(jpe?g|png|gif)`), y devuelve 906002 (HTTP 415) si no coincide. El controlador de `attachments` sanitiza el nombre preservando letras, números, puntos y CR (`preg_replace('/([^a-zA-Z0-9\.\r]+)/', '-', ...)`) y guarda con `move_uploaded_file` en una carpeta por archivo cuyo id es `time() + $key` (app/core/modules/attachments/controller.php:42-56) —nombre y carpeta predecibles—. nginx ya bloquea la ejecución de PHP salvo `api.php` y `security.headers.nosniff` ya está activo (app/config.yml `security.headers`). El código 906002 ya existe para formato inválido (app/core/config/api_codes.yml:129). Ver hallazgo F-005 en la auditoría; motivación en proposal.md - Why.

## Goals / Non-Goals

- **Goals**: decidir el tipo por extensión + contenido real; almacenar con nombre aleatorio; garantizar que un rechazo no deja nada en disco; servir los uploads como estático no ejecutable.
- **Non-Goals**: cuota, límite de tamaño o control de acceso a la subida (F-003, cambio `require-auth-file-uploads`); servir los uploads desde un origen/dominio separado (recomendación adicional de la auditoría, requiere cambio de despliegue); antivirus o sandbox.

## Decisions

1. **Sniffing de contenido real (finfo/MIME por firma) combinado con whitelist de extensión por módulo**, sobre confiar en el `Content-Type` del cliente.
   - *Alternativa A*: solo extensión — un `.jpg` con HTML dentro seguiría entrando.
   - *Alternativa B*: solo sniffing — archivos legítimamente ambiguos (p. ej. PDF sin firma clara en cabecera) podrían rechazarse; la combinación es lo que recomienda la auditoría.
   - Elección: ambas comprobaciones, con las whitelist existentes por módulo como contrato de tipo.
2. **Rechazo con el código existente 906002 (HTTP 415)** en lugar de un código nuevo: el significado observable ("formato inválido") no cambia y se evita expandir el catálogo sin necesidad.
3. **Nombre aleatorio UUID con la extensión validada; el nombre original solo como metadato** en la respuesta/registro. *Asunción material*: el id de carpeta actual (`time() + $key`) es adivinable, así que también pasa a ser aleatorio para cumplir "no adivinable" de extremo a extremo. *Alternativa descartada*: hash del contenido como nombre — no aporta sobre UUID y crea colisiones de nombres repetidos.
4. **Compatibilidad**: la respuesta de `attachments` cambia el campo `name` (antes nombre sanitizado) a nombre aleatorio + metadato original — **BREAKING** para clientes que regeneren la URL desde `name`. Verificar el frontend antes de desplegar.
5. **Servido no ejecutable**: cumplir con el tipo fijado por el servidor + `nosniff` ya activo, verificando además que el directorio de uploads no admite interpretación de scripts (nginx ya lo garantiza salvo `api.php`). Si se detecta contenido activo servible, añadir `Content-Disposition` para tipos no-visuales.

## Risks / Trade-offs

- [Sniffing rechaza archivos borde legítimos (p. ej. PDF atípicos)] → mitigación: whitelist por módulo estrecha y test de aceptación con archivos legítimos de cada tipo; ampliar solo con evidencia.
- [Clientes que regeneran URLs desde `name`] → mitigación: verificar el frontend antes de desplegar; el metadato original queda disponible en la respuesta.
- [El nombre aleatorio rompe correlación con nombres conocidos en auditorías forenses] → mitigación: el nombre original persiste como metadato consultable.

## Migration Plan

Despliegue sin migración de datos: solo cambia el nombre de los archivos futuros; los ya almacenados siguen siendo servibles. **BREAKING** en el contrato de respuesta de `attachments` — coordinar con el frontend. Rollback: revertir el commit; los archivos ya renombrados no vuelven al nombre original (inofensivo).

## Open Questions

- _(ninguno — técnica de sniffing, códigos de error, aleatoriedad de nombre y política de servido se resolvieron en Decisions)_
