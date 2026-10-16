# Detalle de cambios del desarrollador

Este documento no es una especificacion ni debe utilizarse como tal o como una guia de desarrollo. Este archivo es una lista de peticiones de cambios con explicacion y checklist que será utilizado para generar especificaciones a partir de este con openspec. las nuevas especificaciones solo deben tomar en cuenta los elementos no marcados en el checklist. Al crear la especificacion con openspec se debe marcar los elementos en el checklist que ya fueron transformado en especificaciones para que no se generen nuevamente. Cada elemento del checklist puede estar conetado por medio de un anchor a suna seccion de este documento que explique el cambio solicitado.

## Checklist

1. [x] [Retorno de totales](#retorno-de-totales-de-contratos)
2. [x] [Totales separados por año fiscal y periodo o trimestre](#totales-separados-por-año-fiscal-y-periodo-o-trimestre)
3. [x] [Totales por tipo de procedimiento (licitacion, adjudicacion directa, etc)](#totales-por-tipo-de-procedimiento)

### Retorno de totales de contratos

El modulo de contratos del sistema debe implementar un retorno de totales de los contratos al final del listado de contratos, este debe incluir:

- Total general de monto de los contratos con IVA (Con impuestos)
- Total general de monto de los contratos sin IVA (Sin impuestos)
- Total por periodo o trimestre de monto de los contratos con IVA (Con impuestos)
- Total por Año fiscal de monto de los contratos con IVA (Con impuestos)
- Total de resultados de la consulta (Total de contratos devueltos por la consulta, si no esta aplicado aun)

#### Indicaciones generales

- Trata de utilizar el el ORM actual, si necesitas crear nueva consulta con el mismo ORM hazlo, si no te es posible, avisa y dame otras altermativas para poder obtener los totales de los contratos.
- Realiza los cambios necesarios en openapi para que puedan reflejarse al consultarla e indique que esta consulta geta los totales de los contratos.

#### Aclaraciones

- Deben ser totales de todos los contratos devueltos en la consulta, sin tomar en cuenta la paginacion, es decir, si los contratos son 500 pero la paginacion solo devuelve 50, los totales deben ser de los 500 contratos.

### Totales separados por año fiscal y periodo o trimestre

Corregir el retorno de los totales de los periodos en la consulta de contratos para que los totales de los periodos se calculen por aǹo fiscal, no en general y que se devuelva de esa manera en la respuesta del API

#### Ejemplo

```json
{
  "totales": {
    "total_resultados": 500,
    "total_con_iva": 100000,
    "total_sin_iva": 80000,
    "total_por_periodo": {
      "2023": {
        "total": 12345678,
        "periodos": {
          "Q1": {
            "total_con_iva": 25000,
            "total_sin_iva": 20000
          },
          "Q2": {
            "total_con_iva": 30000,
            "total_sin_iva": 24000
          }
        }
      },
      "2024": {
        "total": 12345678,
        "periodos": {
          "Q1": {
            "total_con_iva": 45000,
            "total_sin_iva": 36000
          }
        }
      }
    }
  }
}
```

#### Indicaciones generales

- Actualizar el openapi para reflejar estos cambios
- Actulizar especifi caciones si es necesario

### Totales por tipo de procedimiento

Agregar a la respuesta de totales de contratos un retorno de totales por tipo de procedimiento, es decir, que se devuelva un total de monto de contratos por cada tipo de procedimiento (licitacion, adjudicacion directa, etc). Estos totales deben ser calculados en base a los resultados solicitados no a los totales generales de todos los contratos.

Deben de aparece dentro del objeto `totales`, ejemplo:

```json
{
  "totales": {
    "total_resultados": 500,
    "total_con_iva": 100000,
    "total_sin_iva": 80000,
    "adjudicacion_directa": 123456,
    "adjudicacion_por_invitacion": 123456,
    "licitacion_publica": 123456,
    ...
  }
}
```

Los totales deben ser con IVA, es decir, con impuestos.

#### Indicaciones generales

- Actualizar el openapi para reflejar estos cambios
- Actulizar especificaciones si es necesario
