# Customer Confirmation Move Behavior

This document defines the expected behavior for customer-confirmation member move flows.

## Full Move (In-Place, No New CC)

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

## Partial Move (Create New CC)

A move must create a new confirmation when selected members are a strict subset of active source members.

This applies for:

- Member-row move from source with more than one active member.
- Group-row move when only part of active members are selected.
- Manifest-triggered move when source has other active members.

## Leader Assignment Rules

- Target confirmation must have exactly one leader among active members.
- Source confirmation must auto-adjust leader after partial move:
    - If source leader moved, first remaining active member becomes leader.
    - Cancelled members cannot remain the active leader.

## Manifest Relation Rules

- Moved members must be removed from source manifest membership.
- Existing paid-member auto-link behavior to target manifest structures must remain intact.
