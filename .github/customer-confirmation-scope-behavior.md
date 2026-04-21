# Customer Confirmation Scope Behavior

This document defines the expected behavior for data-scope filtering in customer confirmation related modules.

## Data Scope Apply Behavior

### Country Scope

- Nav Apply selection must filter these modules consistently:
    - Dashboard enquiry widgets/data
    - Enquiry index
    - Confirmed Customer index
    - Customer Holding index
    - Completed Customer index
    - Cancelled Customer index
    - Package index
    - Manifest index
    - Ops Movement index
- If one country is selected, only that country data is visible.
- If multiple countries are selected, only those countries are visible.
- Selected countries are persisted per user account and reused across devices.

### Enforcement Rules

- Scope uses selected country IDs, constrained by assignable country IDs.
- Enquiry and Customer Confirmation indexes must not bypass scope by ownership.
- Customer Confirmation rows without enquiry are filtered by package country.
