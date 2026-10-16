# Spec Delta

## MODIFIED Requirements

### Requirement: Pagination and shaping parameters
The API SHALL accept the following query parameters:
- `?embed=pagination` enables pagination metadata
- `?embed=totals` enables aggregate totals over the full filtered result set where the module supports them
- `?page=N` selects the page
- `?limit=N` sets the page size
- `?sort=+field,-field` controls ordering
- `?groupby=field1,field2` groups results
- `?fields=field1,field2` limits the returned fields

#### Scenario: Requesting paginated results
- **WHEN** a client adds `?embed=pagination`
- **THEN** the response includes pagination metadata

#### Scenario: Requesting aggregate totals
- **WHEN** a client adds `?embed=totals` to a listing that supports them
- **THEN** the response includes the module's aggregate totals computed over the full filtered result set, ignoring pagination

#### Scenario: Sorting results
- **WHEN** a client passes `?sort=+field,-field`
- **THEN** results are ordered ascending for `+` and descending for `-`
