# Design

## Context

`tools/generate-openapi.php` construye el contrato a partir de `$moduleFields` y `$get_params` de cada módulo. Los cuerpos de solicitud se generaban con **todos** los campos del mapa (incluidos `saved=false`/`readonly`), sin `required`, y con descripciones fijas `Public field` / `Internal field`; los parámetros de consulta no enumeraban los valores aceptados por `fields`, `sort`, `groupby` ni `embed`. El comportamiento real vive en `BaseModel::queryFields()` (qué campos se escriben) y en `FieldsValidator` (qué reglas se aplican). Ver proposal.md - Why.

## Goals / Non-Goals

**Goals:**

- Que el contrato publique, por operación, exactamente los campos que se pueden enviar y cuáles son obligatorios.
- Derivar tipos, formatos y restricciones de `$rules` en lugar de listas mantenidas a mano.
- Que los parámetros de consulta de `GET` declaren los campos que el backend acepta.
- Mantener el documento regenerable y verificable por `OpenApiSyncTest`.

**Non-Goals:**

- Corregir el comportamiento de la API (reglas malformadas, validación de `required` en PUT, campos `optional`).
- Documentar los endpoints de subida como `multipart/form-data` (`/attachments`, `/image-upload`, `/frontend-logs`).
- Cambiar ejemplos de respuesta, códigos de error, `operationId` o seguridad.

## Decisions

- **Reglas parseadas del código, no anotadas en el JSON.** Se reutiliza el extractor de bloques con balanceo de llaves ya usado para `$moduleFields` para leer `$rules` por módulo. Alternativa descartada: mantener una lista de campos requeridos a mano, que se desincroniza y ya está prohibida por el requisito de sincronización del contrato.
- **Campo enviable = `saved=true` y no `readonly`.** Es la misma semántica que aplica `queryFields()` al construir el `INSERT`/`UPDATE`, por lo que el doc no promete columnas que la API ignora.
- **`required` solo en `POST`.** En `PUT`, `queryFields()` descarta los campos ausentes o vacíos antes de validar, así que el backend no exige nada; el esquema de actualización documenta esa actualización parcial en su descripción.
- **Las reglas mandan sobre las heurísticas de nombre.** El tipo se calcula primero por convención de nombre y luego se sobrescribe con `numeric`, `decimal`, `boolean`, `email`, `url`, `date_format`; `max:N`/`min:N` producen `maxLength`/`minLength` solo en campos de texto y `max_value`/`min_value` producen `maximum`/`minimum`.
- **Descripciones en inglés**, igual que el resto del documento publicado (`page`, `limit`, `sort`, ejemplos de filtro).
- **`additionalProperties` sigue en `true`.** La API ignora los campos desconocidos en vez de rechazarlos con `400`, así que declarar `false` prometería una validación que no existe.

## Risks / Trade-offs

- [Descripciones largas de `fields`/`sort`/`groupby` en módulos grandes (contracts documenta ~49 campos)] → el texto va en la descripción del parámetro, que la UI pliega; se prioriza poder copiar la lista.
- [Una regla malformada en el módulo (p. ej. `max:11:exist:c_procedures:id`) hace que `exist` no se evalúe en runtime] → el documento solo refleja lo que el validador realmente aplica (`max`), evitando prometer una restricción que no existe.
- [Un campo marcado `readonly` sin `saved=false` dejaría de documentarse aunque el backend lo escriba] → se eligió la semántica deseada; hoy ningún módulo tiene esa combinación.
- [Cambios en `$rules` o `$moduleFields` sin regenerar rompen `testPublishedSpecMatchesRegeneratedOutput`] → es el mecanismo de guarda ya existente; se regenera como parte del cambio.

## Migration Plan

1. Regenerar `api/docs/openapi.json` y `httpdocs/api/docs/openapi.json` con `php tools/generate-openapi.php`.
2. Ejecutar `./vendor/bin/phpunit`; `OpenApiSyncTest` compara publicado contra generador y falla si hay deriva.
3. Revertir con `git checkout -- api/docs/openapi.json httpdocs/api/docs/openapi.json tools/generate-openapi.php` si la UI publicada necesita volver al contrato anterior.

## Open Questions

- Ninguno.
