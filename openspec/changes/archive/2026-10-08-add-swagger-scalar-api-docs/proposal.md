## Why

El proyecto necesita documentación pública y consumible por implementadores sin depender de acceso al código o autenticación. Un contrato OpenAPI separado facilita publicar y mantener la documentación, y deja una base limpia para futura distribución en GitHub Pages.

## What Changes

- Se incorpora una especificación OpenAPI versionada en JSON como fuente canónica de la documentación.
- Se publica Scalar como interfaz pública sin autenticación para explorar la API.
- Se documentan endpoints públicos y protegidos en la misma spec por ahora.
- Se incluye el esquema de seguridad para los endpoints que lo requieren, aunque la documentación sea pública.
- **BREAKING**: no aplica cambios de comportamiento en la API; el cambio afecta documentación y exposición de contrato.
- Se deja preparado el enfoque para que la documentación pueda montarse en GitHub Pages en el futuro sin rehacer la spec.

## Capabilities

### New Capabilities
- `swagger-scalar-api-docs`: documentación pública de la API con OpenAPI JSON versionado y UI Scalar.

### Modified Capabilities
- Ninguna.

## Impact

- Nuevo artefacto canónico en `api/docs/openapi.json`.
- Exposición pública de documentación y UI de exploración.
- Futura compatibilidad de hosting estático separado de `httpdocs`.
- Posible ajuste de rutas de despliegue y publicación del JSON en entornos de release.
