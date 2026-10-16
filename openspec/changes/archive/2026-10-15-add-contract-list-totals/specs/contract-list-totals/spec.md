# Spec Delta

## Purpose

Define el bloque de totales monetarios y de conteo que el módulo de contratos embebe en su listado, calculado sobre el conjunto completo de contratos que cumple la consulta (filtros, búsqueda), sin considerar la paginación de la página solicitada.

## ADDED Requirements

### Requirement: Embedded totals block in contract listing
The contracts list endpoint SHALL accept `embed=totals` and, when present, include a `totals` object in the standard success response, alongside `data` and any other embedded blocks.

#### Scenario: Listing with totals embedded
- **WHEN** a client sends `GET /api/v5/contracts?embed=totals`
- **THEN** the response envelope contains a `totals` object in addition to the contract rows in `data`

#### Scenario: Listing without totals embedded
- **WHEN** a client sends `GET /api/v5/contracts` without `embed=totals`
- **THEN** the response does not include a `totals` object

### Requirement: General monetary totals
The `totals` object SHALL include the sum of `total_amount` (amount with IVA) and the sum of `subtotal` (amount without IVA) across all contracts matching the query filters and search, ignoring pagination.

#### Scenario: Totals span the whole filtered result set
- **WHEN** the query filters match 500 contracts and the requested page holds 50
- **THEN** `totals` reports the with-IVA and without-IVA sums of all 500 matching contracts, not just the 50 on the page

#### Scenario: Totals reflect applied filters
- **WHEN** a client filters the listing (e.g. `?status_id=eq:1&embed=totals`)
- **THEN** the monetary totals include only contracts matching the filter

### Requirement: Totals grouped by period and fiscal year
The `totals` object SHALL include the sum of `total_amount` (with IVA), grouped by contract period (`c_periods`) and grouped by fiscal year, over the full filtered result set.

#### Scenario: Totals per period
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals`
- **THEN** `totals` includes one entry per period present in the matching contracts, each with that period's summed `total_amount`

#### Scenario: Totals per fiscal year
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals`
- **THEN** `totals` includes one entry per fiscal year present in the matching contracts, each with that year's summed `total_amount`

### Requirement: Result count in totals
The `totals` object SHALL include the total number of contracts matching the query (filters, search, grouping), ignoring pagination.

#### Scenario: Result count ignores pagination
- **WHEN** the query matches 500 contracts and the page size is 50
- **THEN** `totals` reports a result count of 500

### Requirement: Totals honor filters and search
All values in the `totals` object SHALL be computed with the same joins, filters, and normalized search conditions as the listing query itself.

#### Scenario: Totals with a search term
- **WHEN** a client requests `GET /api/v5/contracts?search=<term>&embed=totals`
- **THEN** the totals and result count cover only contracts matched by that search

### Requirement: Totals for an empty result set
When the query matches no contracts, the endpoint SHALL still respond with the standard success envelope and a `totals` object reporting zero amounts and a result count of zero, with no per-period or per-fiscal-year entries.

#### Scenario: No contracts match
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals` and the filters match nothing
- **THEN** the response contains `data: []` and `totals` with zero general amounts and a result count of zero

### Requirement: Totals documented in the published OpenAPI document
The published OpenAPI document SHALL describe the `embed=totals` value and the `totals` response block for the contracts list operation.

#### Scenario: Reading the OpenAPI document
- **WHEN** a client requests the published OpenAPI JSON document
- **THEN** the contracts list operation documents `totals` as a supported value of the `embed` parameter
- **AND** the documented response for the contracts list operation includes the `totals` block with its fields (with-IVA total, without-IVA total, totals per period, totals per fiscal year, result count)

#### Scenario: Published copies stay in sync
- **WHEN** the OpenAPI document is updated for this capability
- **THEN** both published copies remain byte-identical
