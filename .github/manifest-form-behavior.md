# Manifest Form Behavior Specification

This document defines mandatory behavior for the Manifest form so future changes stay consistent across tabs and backend payload normalization.

## Scope

- Frontend files under `resources/js/pages/manifests/*`
- Backend normalization/sync in `app/Http/Controllers/ManifestController.php` and `app/Services/ManifestService.php`
- Validation and typing in `resources/js/pages/manifests/schema.ts`, `resources/js/pages/manifests/types.ts`, `resources/js/pages/manifests/validation.ts`, and `app/Rules/ManifestRule.php`

## Canonical Data Shape

- Use dynamic `roomLists` only as `Record<string, TravelerRow[]>`.
- Do not introduce or reintroduce fixed keys like `roomListMekkah`, `roomListMadinah`, or `roomListOthers`.
- Keep `travelers` as the canonical list for shared traveler/customer/member data.
- `airlineList` and every room list entry are projections of the same traveler entities, not separate independent records.

## Field Unification Rules

- Passport field must be `passport_number` only.
- Do not add alias fields such as `ppt_no`, `passport_no`, or duplicated passport keys.
- For relationship/role fields, keep consistency between traveler-level role and room-level relationship mapping.

## Cross-Tab Sync Contract

When a shared field is edited in any tab, update the same traveler across all tabs immediately.

Shared fields that must stay synchronized:

- `passport_number`
- `nationality`
- `gender`
- `date_of_birth`
- `age` (derived from `date_of_birth`)
- `date_of_issue`
- `date_of_expiry`
- `issue_place`
- `role` and room relationship equivalents when applicable

### Travelers/Main Tab Required Columns

- Status badge style should match the visual pattern used for confirmation member status badges.
- Include these columns/data points:
    - package category (from confirmation package category)
    - sign-up date (from enquiry `created_at`, fallback to confirmation `created_at`)
    - first time umrah (`customer.first_time_umrah`)
    - room type (from member `sharing_plan`)
    - passport number
    - gender
    - date of birth + age

## Backend Responsibilities

- Controller/service normalization must accept and persist dynamic `roomLists` ordering and grouping.
- Sync methods must propagate shared traveler updates to underlying member/customer sources where required by business rules.
- Keep room regroup/reorder persistence behavior intact when saving and reloading.

## Validation and Tests

- Validation rules and TypeScript schemas must use the same field names and constraints.
- Any behavior change must update/add tests that cover:
    - grouped payload normalization
    - room list reorder/regroup persistence
    - shared field sync across tabs
    - passport field unification

## Implementation Guardrails

- Prefer extending existing manifest helpers before introducing new parallel transformation paths.
- Avoid one-off per-tab mappings that can drift from canonical traveler data.
- If a new field is introduced, define it once in schema/types and wire sync from canonical traveler source.
