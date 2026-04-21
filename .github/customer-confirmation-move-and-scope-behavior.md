# Customer Confirmation Move And Scope Behavior

This document defines the expected behavior for data-scope filtering and customer-confirmation move flows.

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

## Customer Confirmation Move Behavior

### Full Move (In-Place, No New CC)

A move must update the existing confirmation in place when selected members equal all active members in source confirmation.

- Confirmed -> Holding:
    - `is_holding = true`
    - `package_id = null`
- Holding -> Confirmed:
    - `is_holding = false`
    - `package_id = target_package_id`

This applies for:

- Group-row move with all active members selected.
- Member-row move only when source has one active member.
- Manifest-triggered move when source has one active member.

### Partial Move (Create New CC)

A move must create a new confirmation when selected members are a strict subset of active source members.

This applies for:

- Member-row move from source with more than one active member.
- Group-row move when only part of active members are selected.
- Manifest-triggered move when source has other active members.

### Leader Assignment Rules

- Target confirmation must have exactly one leader among active members.
- Source confirmation must auto-adjust leader after partial move:
    - If source leader moved, first remaining active member becomes leader.
    - Cancelled members cannot remain the active leader.

### Manifest Relation Rules

- Moved members must be removed from source manifest membership.
- Existing paid-member auto-link behavior to target manifest structures must remain intact.
