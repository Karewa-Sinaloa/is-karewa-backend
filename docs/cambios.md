# Detalle de cambios del desarrollador

Este documento no es una especificacion ni debe utilizarse como tal o como una guia de desarrollo. Este archivo es una lista de peticiones de cambios con explicacion y checklist que será utilizado para generar especificaciones a partir de este con openspec. las nuevas especificaciones solo deben tomar en cuenta los elementos no marcados en el checklist. Al crear la especificacion con openspec se debe marcar los elementos en el checklist que ya fueron transformado en especificaciones para que no se generen nuevamente. Cada elemento del checklist puede estar conetado por medio de un anchor a suna seccion de este documento que explique el cambio solicitado.

## Checklist

1. [x] [Retorno de totales](#retorno-de-totales-de-contratos)
2. [x] [Totales separados por año fiscal y periodo o trimestre](#totales-separados-por-año-fiscal-y-periodo-o-trimestre)
3. [x] [Totales por tipo de procedimiento (licitacion, adjudicacion directa, etc)](#totales-por-tipo-de-procedimiento)
4. [x] [Nuevos campos de contratos](#nuevos-campos-de-contratos)
5. [x] [Helper para descargar archivos desde servidor remoto](#helper-para-descargar-archivos-desde-servidor-remoto)
6. [x] [Llamada para descarga individual](#llamada-para-descarga-individual)
7. [x] [Llamada para descarga masiva](#llamada-para-descarga-masiva)

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

### Nuevos campos de contratos

Se agregan nuevos campos de contratos en la base de datos

#### Nuevos campos

- Respaldo de propuesta: `proposal_backup` Campo para guardar el path del respaldo de la propuesta del contrato, este campo debe ser un string y puede ser nulo.
- Url de fallo de la junta de aclaraciones: `clarification_ruling_url` Campo para guardar la url del fallo de la junta de aclaraciones, este campo debe ser un string y puede ser nulo.
- Resolucion de fallo de la junta de aclaraciones: `clarification_ruling_backup` Campo para guardar el path de la resolucion del fallo de la junta de aclaraciones, este campo debe ser un string y puede ser nulo.

#### Procesos a realizar

- Actualizar el modulo de contratos para que pueda guardar estos nuevos campos en la base de datos,
- Actualizar el openapi para reflejar estos cambios y que puedan ser consultados y guardados por medio del API, se muestran de forma publica.

### Helper para descargar archivos desde servidor remoto

Crear un helper reutilizable para poder descargar desde servidores remotos documentos, de forma individual o masiva, puede ser una clase que permita descargar de forma individual desde un metodo, y crear otro metodo que se pueda llamar para descargar de forma masiva

#### Requisitos

- Descargar el archivo a un directorio local
- Renombrarlo con el ID de la fila + `_` + Nombre de la columna de respaldo + `_` + ID del contrato que se le pase a la clase
- Guardar el path del archivo en la base de datos en la tabla contratos `dev_contratos` en la fila que se le pase el ID, el path es `CONTRACTS_DIR` + el nombre del archivo renombrado, por ejemplo: `CONTRACTS_DIR/123_contract_backup_123456789.pdf`
- Si ocurre algun error al descargar el archivo, se debe guardar en la base de datos un codigo de error en la misma columna donde se guardaria el archivo, los errores pueden ser:
  - `error_404`: El archivo no existe en el servidor remoto
  - `error_400`: Para cualquier otro error que no sea 404, por ejemplo, problemas de conexion, permisos, etc.
- Guardar en el archivo `logs/remote_download.log` un log de la descarga, indicando si fue exitosa o no, y en caso de error, el codigo de error, la fecha, la respuesta y el ID del contrato y la url del archivo que se intento descargar
- la clase debe devolver una respuesta indicando si la descarga fue exitosa o no, y en caso de error, el codigo de error, la fecha, la respuesta y el ID del contrato y la url del archivo que se intento descargar
- El metodo para descarga masiva de contratos solo será utilizado para ser llamado desde un cron job para no afectar el rendimiento o exceder tiempos de espera del servidor, por lo que no debe ser llamado desde el API, este metodo debe buscar en la base de datos todos los contratos que tengan alguna de las columnas de archivos nulas o vacia y que tengan una url de archivo no nula, y descargar los archivos correspondientes, guardando el path en la columna correspondiente y el log en el archivo de log.
- En el metodo de descarga individual antes de realizar la peticion de descarga se debe revisar si la columna del path correspondiente esta vacia o contiene un error, si es asi, se realiza la descarga, si no esta vacia o contiene un path valido, no se realiza la descarga y se devuelve un mensaje indicando que el archivo ya fue descargado.
- Realizar peticiones de descarga por medio de CURL con las siguientes funciones:
  - Realizar la peticion por medio de un proxy, la lista de proxies estan en el archivo `tools/proxies.json` se debe seleccionar un proxy aleatorio de la lista para realizar la peticion, si el proxy no funciona, se debe intentar con otro proxy de la lista como maximo 3 diferentes proxies, si ninguno funciona, se debe devolver un error indicando que no se pudo realizar la descarga por problemas de conexion.
  - Se debe utilizar un user agent aleatorio de la lista de user agents que se encuentra en el archivo `tools/user-agents.json` para realizar la peticion, si el user agent no funciona, se debe intentar con otro user agent de la lista como maximo 3 diferentes user agents, si ninguno funciona, se debe devolver un error indicando que no se pudo realizar la descarga por problemas de conexion.

El uso de proxies es para evitar que el servidor remoto bloquee las peticiones por exceso de peticiones desde la misma IP, y el uso de user agents es para evitar que el servidor remoto bloquee las peticiones por ser realizadas desde un script o bot.

#### Ejemplo de la llamada del modelo de la clase individual para descargar un archivo

```php
$remoteDownloader = new RemoteDownloader();
$response = $remoteDownloader->downloadFile($rowId, $contractId, $fileUrl, $columnName);
```

#### Columnas de urls y sus correspondientes columnas de path donde se guardara el archivo descargado

| Columna de url             | Columna de path               |
| -------------------------- | ----------------------------- |
| `proposal_url`             | `proposal_backup`             |
| `clarification_ruling_url` | `clarification_ruling_backup` |
| `contract_link`            | `contract_backup`             |
| `call_link`                | `announcement_backup`         |

#### Observaciones

- Son 4 diferentes archivos que se deben descargar, por este motivo se pasa el nombre de la columna donde se guardara el path del archivo descargado, para que la clase pueda guardar el path en la columna correcta.
- Las decargas masivas deben devolver un array con la respuesta de cada descarga, indicando si fue exitosa o no, y en caso de error, el codigo de error, la fecha, la respuesta y el ID del contrato y la url del archivo que se intento descargar
- Las descargas de archivos de servidores remotos solo la podrian realizar usuarios autenticados con roles 1, 2 y 3, por lo que se debe validar el rol del usuario antes de realizar la descarga, si el usuario no tiene el rol adecuado, se debe devolver un error indicando que no tiene permisos para realizar la descarga.

### Llamada para descarga individual

Dentro del modelo de contratos, crear un metodo que permita descargar de forma individual los archivos de un contrato, este metodo debe recibir el ID del contrato y el nombre de la columna donde se encuentra la url del archivo a descargar, y llamar al helper para descargar el archivo correspondiente, el helper debe ya saber cual columna de path corresponde a la columna de url que se le paso, para guardar el path del archivo descargado en la columna correcta.

### Llamada para descarga masiva

Crear un archivo php que pueda ser llamado desde un cron job para descargar de forma masiva los archivos de los contratos, este archivo debe llamar al helper para descargar los archivos correspondientes, y guardar el path del archivo descargado en la columna correspondiente, y el log en el archivo de log.
