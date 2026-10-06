# Proposal

## Why

The ORM data layer (`app/core/model/*.php`) has four robustness gaps that surface as misleading responses: every database exception is collapsed to code `902000` regardless of its real domain code, an empty collection is reported as `404` instead of `[]`, `count` queries silently ignore `GROUP BY`, and multi-statement operations run without a transaction. These make failures hard to diagnose and make legitimate "no rows" results look like errors.

## What Changes

- Data-layer exceptions preserve the domain code they already carry and use a distinct code for genuine SQL/driver failures, instead of forcing `902000` for everything.
- Empty collection results (`index`/`list`) return the standard success envelope with an empty `data` array instead of `404`; a missing single record (`show`) still returns `404`.
- `count` queries honour `GROUP BY`, so pagination totals are correct for grouped queries.
- Multi-statement database operations run inside a transaction and roll back on failure.
- **BREAKING**: clients that relied on `404` for an empty `index`/`list` will now receive `200` with `data: []`. Clients that keyed off `902000` as the only data-layer error must handle the new distinct database code.

## Capabilities

### New Capabilities
- `orm-data-layer`: how the shared data-access layer reports errors, handles empty result sets, counts grouped results, and manages transaction boundaries.

### Modified Capabilities
- Ninguna.

## Impact

- `app/core/model/conexion.php`, `get.php`, `store.php`, `update.php`, `delete.php`.
- `app/core/bootstrap/midelware.php` (`BaseModel::get`, `post`, `put`, `delete`) for empty-result and transaction handling.
- `app/core/config/api_codes.yml` may need one new code for SQL/driver failures.
- Affects every module, since all modules use this layer.
- Test impact: adds model-layer coverage (viable with `pdo_sqlite`), including transaction rollback and empty-list behavior.
