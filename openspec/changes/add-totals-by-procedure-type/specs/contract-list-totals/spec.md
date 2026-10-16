# Spec Delta

## ADDED Requirements

### Requirement: Totals grouped by procedure type
The `totals` object SHALL include a `by_procedure` array with one entry per procedure type present in the contracts matching the query filters and search, ignoring pagination. Each entry SHALL carry the `procedure_id`, the `procedure_name`, and the sum of `total_amount` (amount with IVA) of that type's matching contracts. The sums SHALL cover only the with-IVA amount.

#### Scenario: One entry per procedure type in the results
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals`
- **THEN** `totals.by_procedure` contains one entry per procedure type present in the matching contracts, each with its `procedure_id`, `procedure_name`, and summed `total_amount`

#### Scenario: Totals per procedure ignore pagination
- **WHEN** the query matches 500 contracts spread over several procedure types and the requested page holds 50
- **THEN** each `by_procedure` entry sums the with-IVA amounts of all 500 matching contracts, not just the 50 on the page

#### Scenario: Grouping follows applied filters and search
- **WHEN** a client filters the listing (e.g. `?status_id=eq:1&embed=totals`) or supplies a search term
- **THEN** `by_procedure` covers only the contracts matching that filter or search, and procedure types absent from those results do not appear

#### Scenario: Procedure types without matching contracts are absent
- **WHEN** a procedure type exists in the system but none of the matching contracts use it
- **THEN** `totals.by_procedure` contains no entry for that procedure type

#### Scenario: Empty result set
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals` and the filters match no contracts
- **THEN** `totals.by_procedure` is an empty array

## MODIFIED Requirements

### Requirement: Totals documented in the published OpenAPI document
The published OpenAPI document SHALL describe the `embed=totals` value and the `totals` response block for the contracts list operation.

#### Scenario: Reading the OpenAPI document
- **WHEN** a client requests the published OpenAPI JSON document
- **THEN** the contracts list operation documents `totals` as a supported value of the `embed` parameter
- **AND** the documented response for the contracts list operation includes the `totals` block with its fields (with-IVA total, without-IVA total, totals per period, totals per fiscal year, totals per procedure type, result count)

#### Scenario: Published copies stay in sync
- **WHEN** the OpenAPI document is updated for this capability
- **THEN** both published copies remain byte-identical
