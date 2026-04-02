# Manifest Form Behavior Specification

This document is the single source of truth for Manifest form behavior, payload structure, save flow, and regression guardrails after the restructure rollout.

## Scope

- Frontend: `resources/js/pages/manifests/*`
- Backend controller and normalization: `app/Http/Controllers/ManifestController.php`
- Backend service sync/read mapping: `app/Services/ManifestService.php`
- Validation: `app/Rules/ManifestRule.php`
- Frontend schema/types/validation: `resources/js/pages/manifests/schema.ts`, `resources/js/pages/manifests/types.ts`, `resources/js/pages/manifests/validation.ts`
- Manifest routes: `routes/web.php`
- Regression suites: `tests/Feature/ManifestWorkflowTest.php`, `tests/Feature/PackageOfficialManifestSyncTest.php`, `tests/Feature/ManifestSeederTest.php`, `tests/Feature/ReceiptMemberStatusSyncTest.php`

## Post-Restructure Runtime Contract

### Primary Save Flow

Manifest writes are section-first and must use section endpoints in this order:

1. `core`
2. `sharing-groups`
3. `rooms`
4. `documents`
5. `receipt-documents`

Section save rules:

1. Omitted section payload means no change.
2. Section write failure must not wipe other sections.
3. Explicit clear operations must be intentional and section-scoped.

### Legacy Full Submit

Legacy full submit can exist only as compatibility/rollback behavior. New features must not depend on full-submit-only payload semantics.

## Canonical Payload Structure

### Canonical GET Shape (Server -> Form)

Top-level canonical keys:

- `manifest`
- `manifest_sharing_groups`
- `manifest_rooms`
- `documents`

Legacy keys may still be present for compatibility, but new code must consume canonical keys first.

### Canonical Write Shapes

#### 1) Manifest Core

`manifest` contains manifest-level fields only, such as:

- `id`
- `package_id`
- `in_charge_official_id`
- `manifest_number`
- `status`
- `notes`

#### 2) Sharing Groups

`manifest_sharing_groups` contains group rows and group member rows.

Group-level ordering and member-level ordering are mandatory:

1. Primary ordering: `group.sort_order`
2. Secondary ordering: `member.sort_order`

#### 3) Rooms

`manifest_rooms` contains room rows and room member rows.

Room member identity rules:

1. Prefer manifest member identity fields.
2. Fallback aliases may be accepted only for compatibility.
3. Do not add redundant customer confirmation identifiers in room member rows when they can be derived server-side.

#### 4) Documents

`documents` is a manifest-level object keyed by:

- `flight_tickets`
- `visa`
- `hotel`
- `passport`
- `photo`

Each document entry supports:

- `id`
- `file`
- `file_name`
- `file_path`
- `removed`

#### 5) Receipt Documents

Receipt file updates are section-scoped and must not rely on unrelated full payload writes.

## Tab Behavior Contract

The UI remains split into three rows:

### Row 1: Main

- Main member list with shared identity/profile fields.

### Main Tab Financial Display Rules (Critical)

These fields are visualization helpers for users in Main tab and are not accounting-truth settlement fields:

- `discount`
- `deposit_payment`
- `second_payment`
- `third_payment`
- `balance_due`

Required behavior:

1. Use simple projection logic from quotation item amounts grouped by member and invoice order.
2. Discount allocation must be payer-first within the same quotation.
3. If payer allocation is exhausted and discount remains, spill the remaining discount to the next member(s) in the same quotation.
4. Payment bucket projection must apply member discount sequentially: deposit first, then second, then third.
5. Date columns for payment buckets must be sourced from receipt dates by invoice order for the same member scope:
    - first invoice receipt date -> `date_of_deposit_payment`
    - second invoice receipt date -> `date_of_second_payment`
    - third and later invoices receipt dates -> `date_of_third_payment` (first available date in third-plus order)
6. Do not switch these Main tab display columns to receipt-paid settlement logic during refactors.
7. Keep this behavior stable unless explicitly requested by product/business owner and accompanied by regression-test updates.

### Row 2: Room and Airline Views

- Airline list
- Dynamic room list tabs per location
- Dynamic official check tabs per location
- Namelist course and collection items tab

### Row 3: Documents and Additional Member Data

- Manifest-level document tabs: flight tickets, visa, hotel, passport, photo
- Arabic names tab
- Receipt tab

## Canonical Data and Naming Rules

1. Keep `roomLists` dynamic (`Record<string, TravelerRow[]>`) where compatibility structures are still required.
2. Do not reintroduce fixed room list keys such as location-specific hardcoded properties.
3. Passport must use `passport_number` only.
4. Shared member entities remain the source for projections shown in airline/room/checklist views.
5. New code must use member-first naming for symbols, helpers, and payload handling.
6. Compatibility aliases are allowed only when needed for external contract safety and must be isolated in normalization layers.

## Cross-Tab Synchronization Rules

When a shared field changes in one tab, the same member data must stay synchronized in all tabs.

Shared fields that must stay in sync:

- `passport_number`
- `nationality`
- `gender`
- `date_of_birth`
- `age` (derived)
- `date_of_issue`
- `date_of_expiry`
- `issue_place`
- `role` and room relationship equivalents

## Persistence Guardrails

1. Repeated submit without namelist edits must preserve namelist values.
2. Receipt files must remain after unrelated section saves.
3. Room regrouping/reordering must persist after save and reload.
4. Document CRUD must remain isolated from member receipt persistence.

## Backend Guardrails

1. Normalize compatibility inputs in one place, then sync using canonical internal shape.
2. Keep deterministic ordering and grouping behavior.
3. Prevent hidden cross-section clearing when a section is omitted.
4. Reuse existing sync helpers rather than adding parallel one-off pipelines.

## Validation and Test Requirements

Any behavior or contract change must include targeted regression evidence.

Minimum test matrix:

1. `tests/Feature/ManifestWorkflowTest.php`
2. `tests/Feature/PackageOfficialManifestSyncTest.php`
3. `tests/Feature/ManifestSeederTest.php`
4. `tests/Feature/ReceiptMemberStatusSyncTest.php`

Coverage expectations for touched behavior:

1. Grouped payload normalization.
2. Room reorder/regroup persistence.
3. Cross-tab shared-field synchronization.
4. Receipt persistence across unrelated saves.
5. Manifest document CRUD isolation.

## Change Management Rules

1. This file is authoritative for manifest runtime behavior and payload structure.
2. If a compatibility alias is introduced or removed, document the reason, rollback path, and affected tests in the same change.
3. Do not add new legacy-key dependencies to frontend runtime paths.
4. Keep changes incremental and prove parity with focused tests before broader cleanup.
