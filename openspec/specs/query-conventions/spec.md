# Query Conventions

## Purpose

Describes the query parameters the Monitor Karewa API accepts for pagination, sorting, field selection, grouping, filtering, and search. Agents MUST honor these conventions when implementing list endpoints.

## Requirements

### Requirement: Pagination and shaping parameters
The API SHALL accept the following query parameters:
- `?embed=pagination` enables pagination metadata
- `?page=N` selects the page
- `?limit=N` sets the page size
- `?sort=+field,-field` controls ordering
- `?groupby=field1,field2` groups results
- `?fields=field1,field2` limits the returned fields

#### Scenario: Requesting paginated results
- **WHEN** a client adds `?embed=pagination`
- **THEN** the response includes pagination metadata

#### Scenario: Sorting results
- **WHEN** a client passes `?sort=+field,-field`
- **THEN** results are ordered ascending for `+` and descending for `-`

### Requirement: Filtering
Filters SHALL use the form `?field=op:value`. The API SHALL support the operators `eq`, `lt`, `gt`, `gte`, `lte`, `ne`, `lk`, `isn`, `non`, and `in`.

#### Scenario: Filtering with an operator
- **WHEN** a client passes `?field=gte:100`
- **THEN** the query filters that field with the `>=` operator

### Requirement: Search normalization
Free-text search SHALL normalize the input before building filters by stripping Spanish accents and common stopwords.

#### Scenario: Accented search term
- **WHEN** a client searches with an accented term
- **THEN** the term is normalized (accents removed, lowercased) before matching
