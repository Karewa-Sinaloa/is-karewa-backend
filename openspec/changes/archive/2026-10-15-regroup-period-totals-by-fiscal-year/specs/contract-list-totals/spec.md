# Spec Delta

## MODIFIED Requirements

### Requirement: Totals grouped by period and fiscal year
The `totals` object SHALL group contract sums by fiscal year, and within each fiscal year group the sums by contract period, over the full filtered result set. Each fiscal-year entry SHALL carry that year's totals plus one entry per period present in that year's matching contracts, with the period's summed `total_amount` (with IVA) and `subtotal` (without IVA). The response SHALL NOT include a separate top-level period grouping outside the fiscal-year entries.

#### Scenario: Totals per period
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals`
- **THEN** each fiscal year in `totals.by_fiscal_year` holds a `periods` array with one entry per contract period present in that year's matching contracts
- **AND** each entry identifies its period (`period_id`, `period_name`) and reports that period's `total_amount` summed only from contracts of that same fiscal year

#### Scenario: Totals per fiscal year
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals`
- **THEN** `totals.by_fiscal_year` includes one entry per fiscal year present in the matching contracts, each with the year's summed `total_amount` and `subtotal` plus its nested `periods`

#### Scenario: Same period code across different fiscal years
- **WHEN** matching contracts of period "1o" exist in fiscal years 2023 and 2024
- **THEN** each fiscal year has its own "1o" entry under `periods` carrying only that year's amounts, instead of a single aggregated period entry

#### Scenario: No standalone period grouping
- **WHEN** a client requests `GET /api/v5/contracts?embed=totals`
- **THEN** the `totals` object has no top-level per-period array outside `by_fiscal_year`
