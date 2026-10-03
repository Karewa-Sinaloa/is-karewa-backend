# Spec Delta

## MODIFIED Requirements

### Requirement: Configuration sections
The main configuration SHALL include the sections `database`, `jwt`, `session`, `log`, `cors`, `statics`, `mailings`, `hcaptcha`, `uploads`, `valid_requests`, and related settings.

#### Scenario: Locating a setting
- **WHEN** an agent needs a setting
- **THEN** it looks in the corresponding configuration section
