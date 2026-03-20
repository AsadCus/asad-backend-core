# Manifest Form Behavior Specification

This document defines mandatory behavior for the Manifest form so future changes stay consistent across tabs and backend payload normalization.

## Scope

- Frontend files under `resources/js/pages/manifests/*`
- Backend normalization/sync in `app/Http/Controllers/ManifestController.php` and `app/Services/ManifestService.php`
- Validation and typing in `resources/js/pages/manifests/schema.ts`, `resources/js/pages/manifests/types.ts`, `resources/js/pages/manifests/validation.ts`, and `app/Rules/ManifestRule.php`
- Restructure strategy source in `MANIFEST_PAYLOAD_RESTRUCTURE_FOUNDATION.md`

## Restructure Alignment Contract

This file must stay synchronized with `MANIFEST_PAYLOAD_RESTRUCTURE_FOUNDATION.md` during the full migration.

Source-of-truth split:

- `MANIFEST_PAYLOAD_RESTRUCTURE_FOUNDATION.md` defines migration direction, phase plan, and target payload contract.
- `.github/manifest-form-behavior.md` defines runtime behavior guarantees and implementation guardrails.

Mandatory rule:

- Any phase implementation that changes payload shape, naming, endpoint flow, validation assumptions, or cross-tab sync behavior must update both files in the same PR/commit.

## Restructure Phase Workflow (Path A)

Phases follow the strategy document and must preserve current behavior parity:

1. Payload slimming stabilization
2. Canonical read adapter (GET)
3. Canonical submit adapter (POST)
4. Frontend state migration to canonical sections
5. Section-level patch endpoints
6. Receipt file endpoint separation
7. Terminology migration from traveler to member

For all phases:

- Backward compatibility is required until explicit legacy removal phase.
- Room ordering/grouping persistence and shared-field sync across tabs must not regress.
- Phase cannot be marked complete without targeted test evidence.

## Phase Status Governance

Phase status must be tracked in `MANIFEST_PAYLOAD_RESTRUCTURE_FOUNDATION.md` and mirrored in this file's phase notes.

Allowed values:

- `not-started`
- `in-progress`
- `completed`
- `blocked`

Governance rules:

1. Only one phase can be `in-progress` at any time.
2. `completed` requires passing targeted tests and synchronized docs update.
3. `blocked` requires blocker reason and unblock plan in phase notes.
4. Next phase cannot start until current `in-progress` phase is `completed` or explicitly `blocked`.

## Invocation Behavior For "run manifest restructure"

When user provides "run manifest restructure" with this file and `MANIFEST_PAYLOAD_RESTRUCTURE_FOUNDATION.md` as context:

1. Determine active phase from Phase Status Tracker in restructure file.
2. Execute the current `in-progress` phase; if none exists, execute earliest `not-started` phase.
3. Follow phase prompt requirements: analysis first, then implementation, then tests.
4. Update both files with phase notes and status transition.
5. Report next actionable phase at the end.

## Per-Phase Documentation Update Requirement

At phase completion, append a phase note section in both files with the same core facts:

- status
- scope delivered
- payload keys added/changed/deprecated
- compatibility aliases still active
- backend normalization changes
- frontend state/sync changes
- tests executed and result summary
- known risks and next-phase context
- status transition (`old_status -> new_status`)

Template:

```markdown
### Phase X Completion Notes

- Status: completed
- Date:
- Scope delivered:
- Payload contract changes:
- Compatibility aliases still active:
- Backend behavior changes:
- Frontend behavior changes:
- Tests executed and results:
- Known risks / follow-up:
- Context for next phase:
```

## Naming Transition Rule (Traveler -> Member)

During migration:

- Existing `traveler` terms may remain only where compatibility is required.
- New logic should prefer `member` naming when safe.
- Do not break existing payload consumers while aliases are active.
- Final target is consistent `member` terminology across payload keys, comments, and variable names.

## Tab Layout Contract

The Manifest form tab UI is split into three horizontal tab-list rows and must remain consistent:

### Row 1: Main Traveler List

- `Main` — Full traveler list with shared fields (passport, nationality, gender, dates, etc.)

### Row 2: Room & Airline Lists

- `Airline Name List` — Traveler list for flight manifests with airline-specific fields
- `Room List - {Location}` — Dynamic room tabs (one per accommodation location) for room assignment by location
- `Room List for Official to Check - {Location}` — Dynamic official check tabs (one per accommodation location, read-only/reference)
- `Namelist Course & Collection Items` — Course assignments and collection checklist for each traveler

### Row 3: Document & Member Data Tabs

- **Manifest-level document upload tabs** (`documents` payload):
    - `Flight Tickets` — Manifest-level flight document files
    - `Visa` — Manifest-level visa document files
    - `Hotel` — Manifest-level hotel document files
    - `Passport` — Manifest-level passport document files
    - `Photo` — Manifest-level photo document files
- **Additional data tabs** (not manifest documents):
    - `Arabic Names` — Traveler list with editable `arabic_name` field; not a file upload tab
    - `Receipt` — Traveler-level multi-file receipt uploads (`receipt_documents` payload per traveler)

### Manifest Document Definition

Manifest documents are the five file upload tabs in Row 3:

- `flight_tickets`
- `visa`
- `hotel`
- `passport`
- `photo`

Each is stored in the `documents` object keyed by field name. Each field contains an array of document entries with `id`, `file`, `file_name`, `file_path`, and `removed` flag.

## Canonical Data Shape

- Use dynamic `roomLists` only as `Record<string, TravelerRow[]>`.
- Do not introduce or reintroduce fixed keys like `roomListMakkah`, `roomListMadinah`, or `roomListOthers`.
- Keep `travelers` as the canonical list for shared traveler/customer/member data.
- `airlineList` and every room list entry are projections of the same traveler entities, not separate independent records.

Transition note:

- During restructure phases, canonical section keys (`manifest`, `manifest_sharing_groups`, `manifest_rooms`, `documents`) may exist in parallel with legacy keys.
- Implementation must preserve behavior parity while dual-shape compatibility remains active.

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
- Before starting any new phase implementation prompt, read this file and `MANIFEST_PAYLOAD_RESTRUCTURE_FOUNDATION.md` together.
