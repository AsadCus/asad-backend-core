# Manifest Payload Restructure Foundation

## Why This Proposal Exists

Current manifest payloads can become very large for medium and big manifests (for example 50+ members) because the same traveler information is repeated across multiple sections (main travelers, room lists, airline list, checklist, etc).

The goal of this proposal is to make both:

- initial data load (ManifestService -> ManifestForm), and
- submit payload (ManifestForm -> ManifestController)

more compact, more explicit, and easier to evolve.

## Main Design Principles

- Single source of truth for traveler identity and core fields.
- Reference by IDs in nested sections instead of duplicating full traveler objects.
- Separate mutable section payloads (rooms, docs, checklist) from base traveler registry.
- Keep shape aligned with database concepts:
    - manifest
    - manifest_sharing_groups
    - manifest_rooms
    - documents
- Backward-compatible transition plan (support old + new shape during migration).

## Proposed Canonical Contract

### 1) manifest

Flat manifest-level fields only.

Suggested fields:

- id
- package_id
- in_charge_official_id
- manifest_number
- status
- notes

### 2) manifest_sharing_groups

Array of groups, each with group-level metadata and members.

Group fields:

- id
- customer_confirmation_id (nullable)
- sort_order
- relation
- remarks

Group members array item fields:

- id (manifest_member_id, nullable for new)
- customer_confirmation_member_id (nullable for officials)
- package_official_id (nullable for non-officials)
- role
- sharing_plan
- sort_order
- remarks

Optional member detail patch (only changed fields):

- name_as_per_passport
- arabic_name
- contact_no
- passport_number
- nationality
- gender
- date_of_birth
- date_of_issue
- date_of_expiry
- issue_place
- birth_place
- address
- first_time_umrah
- has_chronic_disease
- chronic_disease_details
- passport_path
- photo_path
- status

### 3) manifest_rooms

Array of rooms, each with room fields and room members.

Room fields:

- id
- location
- sort_order
- relationship
- room_label
- room_number
- room_type
- bed_type
- sharing_plan
- capacity
- meal
- number_of_beds_checked
- remarks

Room members array item fields:

- manifest_traveler_id (preferred)
- customer_confirmation_member_id (fallback)
- package_official_id (fallback)
- sort_order
- remarks

Important:

- Do not send customer_id and customer_confirmation_id in room member rows.
- Resolve those server-side using customer_confirmation_member_id / manifest_traveler_id.

### 4) documents

Manifest-level document tabs only.

Documents object keys:

- flight_tickets
- visa
- hotel
- passport
- photo

Entry fields:

- id
- file (new upload)
- file_name
- file_path
- removed

### 5) traveler_receipt_documents (optional explicit section)

To avoid large nested traveler duplication, consider moving traveler receipts out of traveler rows.

Suggested shape:

- traveler_receipt_documents: Record<manifest_traveler_id or customer_confirmation_member_id, receipt_entries[]>

This keeps receipt updates targeted and avoids repeating many traveler fields.

## GET Payload (Server -> Form) Foundation

Return:

- manifest
- manifest_sharing_groups
- manifest_rooms
- documents
- lightweight helper maps (optional):
    - traveler_index_by_member_id
    - traveler_index_by_official_id

This removes the need to send the same full traveler rows in multiple tabs.

## SUBMIT Payload (Form -> Server) Foundation

Send:

- manifest
- manifest_sharing_groups
- manifest_rooms
- documents
- optional changed-only member detail patches

Server responsibilities:

- Normalize and validate nested arrays.
- Resolve IDs and relations from member references.
- Keep unchanged values when compact payload omits optional fields.
- Handle create/update/delete for documents and nested members.

## Migration Strategy (Safe Incremental)

1. Add support for new payload keys in controller normalizer while still accepting current payload.
2. Internally transform old shape -> new canonical shape in one place.
3. Update frontend submit shape feature-by-feature:
    - roomLists first
    - checklist/airline split
    - receipts extraction
4. Remove legacy shape once all tabs submit canonical data.

## Tradeoffs To Discuss

Pros:

- Much smaller request size for large manifests.
- Cleaner contracts and less repeated data.
- Easier backend normalization and future maintenance.

Cons / Costs:

- Initial refactor complexity (frontend state adapters + backend normalizers).
- Temporary dual-shape support during migration.
- Need careful test coverage for relation resolution.

## Open Questions

- Should manifest_sharing_groups be the only source of traveler membership ordering?
  My Answer: well, what we have currently in main tab and other tabs that same member list, the ordering first from sharing group order then manifest member order.
- Should rooms always reference manifest_traveler_id only, or keep member_id fallback for pre-save forms?
  My Answer: what do you mean with member_id? what i think about the manifest_rooms then to its members, the members data must source from manifest member first. because manifest member have fields similar like customer, that have purpose as history data for manifest so in the future when customer data updated it will not effect manifest member.
- Should traveler receipt docs move to a separate endpoint for very large updates?
  My Answer: i think we should, we will discuss it further.
- Do we want section-level patch endpoints (rooms/docs/checklist) instead of one big submit?
  My Answer: i think we should, we will discuss it further.

My Discussion & Suggestion:
~ i think we need rename manifest "traveler" to manifest "member" in all model/migrations/controller/service/etc, so we dont call it again manifest_traveler_id but manifest_member_id, and in any comment & variable will call it "member" not "traveler".
~ for your questions about separate endpoints, section-level patch endpoints, etc. i think same but with specific way, we separate it between manifest fields then manifest sharing group with its members then manifest room with its room member then document then member receipt files. what do you think?

## Recommended Next Step

Pick one pilot path:

- Path A: canonical room payload first (already started by removing redundant IDs), then full contract migration.
- Path B: full contract in one feature branch with dual-shape compatibility and dedicated migration tests.

My Answer: I prefer path A, but can you suggest & tell me how much request/phase you need to make sure the this restructure is success and achieve same with current manifest form but with efficient payload and backend and with ai agent gpt-5.3-codex? and if you can, make prompt for each phase needed, so we have the structured prompt to ai agent to perfectly improve manifest form without regressing the current feature.

## Version 2: Concrete Example (Discussion Draft)

This section provides realistic JSON examples for quick team review.

### A) Current Verbose Submit Shape (Excerpt)

```json
{
    "id": 1,
    "package_id": 1,
    "in_charge_official_id": 9,
    "manifest_number": "KTG-UMR-2026-001",
    "status": "open",
    "notes": "TEST",
    "travelers": [
        {
            "id": 306,
            "customer_id": 1,
            "customer_confirmation_member_id": 1,
            "customer_confirmation_id": 1,
            "name_as_per_passport": "Asad Baqir Baozhi",
            "passport_number": "KT5393267",
            "nationality": "Singaporean",
            "gender": "male",
            "date_of_birth": "06 May 2005",
            "course_1": false,
            "course_2": false,
            "sharing_group_key": "group-186",
            "sort_order": 1,
            "receipt_documents": []
        }
    ],
    "roomLists": {
        "makkah": [
            {
                "manifest_traveler_id": 306,
                "customer_id": 1,
                "customer_confirmation_member_id": 1,
                "customer_confirmation_id": 1,
                "sharing_group_key": "room-300",
                "room_number": "Room 1",
                "room_type": "double",
                "bed_type": "king",
                "meal": "Full Board"
            }
        ]
    },
    "airlineList": [
        {
            "id": 306,
            "name_as_per_passport": "Asad Baqir Baozhi",
            "passport_number": "KT5393267",
            "nationality": "Singaporean"
        }
    ],
    "documents": {
        "flight_tickets": [
            {
                "file_name": "Manifest Flight Tickets #1 - KTG-UMR-2026-001",
                "file": "<binary>"
            }
        ],
        "visa": [],
        "hotel": [],
        "passport": [],
        "photo": []
    }
}
```

Main issue: traveler and identity fields are duplicated in multiple sections.

### B) Proposed Compact Submit Shape

```json
{
    "manifest": {
        "id": 1,
        "package_id": 1,
        "in_charge_official_id": 9,
        "manifest_number": "KTG-UMR-2026-001",
        "status": "open",
        "notes": "TEST"
    },
    "manifest_sharing_groups": [
        {
            "id": 186,
            "customer_confirmation_id": 1,
            "sort_order": 1,
            "relation": "Husband & Wife",
            "remarks": "Auto-linked from quotation #QTN-2026-005",
            "members": [
                {
                    "id": 306,
                    "customer_confirmation_member_id": 1,
                    "package_official_id": null,
                    "role": "Husband",
                    "sharing_plan": "double",
                    "sort_order": 1,
                    "status": "confirmed",
                    "patch": {
                        "name_as_per_passport": "Asad Baqir Baozhi",
                        "arabic_name": "اساد باقير باوزهي"
                    }
                },
                {
                    "id": 307,
                    "customer_confirmation_member_id": 2,
                    "package_official_id": null,
                    "role": "Wife",
                    "sharing_plan": "double",
                    "sort_order": 2,
                    "status": "confirmed"
                }
            ]
        }
    ],
    "manifest_rooms": [
        {
            "id": 300,
            "location": "makkah",
            "sort_order": 1,
            "relationship": "Husband & Wife (Makkah)",
            "room_number": "Room 1",
            "room_type": "double",
            "bed_type": "king",
            "sharing_plan": "double",
            "capacity": 2,
            "meal": "Full Board",
            "number_of_beds_checked": false,
            "remarks": "TEST",
            "members": [
                {
                    "manifest_traveler_id": 306,
                    "customer_confirmation_member_id": 1,
                    "sort_order": 1
                },
                {
                    "manifest_traveler_id": 307,
                    "customer_confirmation_member_id": 2,
                    "sort_order": 2
                }
            ]
        }
    ],
    "documents": {
        "flight_tickets": [
            {
                "id": null,
                "file_name": "Manifest Flight Tickets #1 - KTG-UMR-2026-001",
                "file": "<binary>",
                "file_path": null,
                "removed": false
            }
        ],
        "visa": [],
        "hotel": [],
        "passport": [],
        "photo": []
    },
    "traveler_receipt_documents": {
        "306": [
            {
                "id": 86,
                "file_name": "Manifest Receipt #1 - KTG-UMR-2026-001",
                "file_path": "manifests/receipt/amyjOKjgtOaqaOGQuGquTYQCuXt2TjtQYph7XYbQ.pdf",
                "removed": false
            }
        ]
    }
}
```

Key reduction rule in this proposal: no `customer_id` and no `customer_confirmation_id` in room member rows.

### C) Proposed Compact GET Shape (Server -> Form)

```json
{
    "manifest": {
        "id": 1,
        "package_id": 1,
        "in_charge_official_id": 9,
        "manifest_number": "KTG-UMR-2026-001",
        "status": "open",
        "notes": "TEST"
    },
    "manifest_sharing_groups": [
        {
            "id": 186,
            "customer_confirmation_id": 1,
            "sort_order": 1,
            "relation": "Husband & Wife",
            "remarks": "Auto-linked from quotation #QTN-2026-005",
            "members": [
                {
                    "id": 306,
                    "customer_confirmation_member_id": 1,
                    "package_official_id": null,
                    "role": "Husband",
                    "sharing_plan": "double",
                    "sort_order": 1,
                    "status": "confirmed",
                    "name_as_per_passport": "Asad Baqir Baozhi",
                    "arabic_name": "اساد باقير باوزهي"
                }
            ]
        }
    ],
    "manifest_rooms": [
        {
            "id": 300,
            "location": "makkah",
            "sort_order": 1,
            "room_number": "Room 1",
            "room_type": "double",
            "bed_type": "king",
            "meal": "Full Board",
            "members": [
                {
                    "manifest_traveler_id": 306,
                    "customer_confirmation_member_id": 1,
                    "sort_order": 1
                }
            ]
        }
    ],
    "documents": {
        "flight_tickets": [],
        "visa": [],
        "hotel": [],
        "passport": [],
        "photo": []
    },
    "traveler_receipt_documents": {
        "306": []
    }
}
```

### D) Backend Mapping Rules

1. Resolve `customer_id` and `customer_confirmation_id` via `customer_confirmation_member_id` when needed.
2. Prefer `manifest_traveler_id` when syncing room members.
3. Treat missing optional `patch` fields as unchanged values.
4. Treat missing receipt section as no receipt changes.

### E) Expected Impact

- Less repeated traveler data across tabs and sections.
- Lower risk of hitting `max_input_vars` for large manifests.
- Cleaner migration path to section-based update endpoints later.

## Response To Your Answers And Suggestions

### 1) Ordering source of truth

Agreed. Ordering should stay:
- primary: `manifest_sharing_group.sort_order`
- secondary: `manifest_member.sort_order`

This should be the canonical ordering rule for main tab and all derived tabs.

### 2) Room member reference model

Agreed. Room members should reference manifest members as the primary source.

Direction:
- primary reference: `manifest_member_id` (currently still `manifest_traveler_id` in existing code)
- fallback only during migration: `customer_confirmation_member_id` and `package_official_id`

This preserves manifest snapshot behavior and avoids coupling to mutable customer records.

### 3) Receipt files endpoint separation

Agreed. Receipt files should move to a dedicated endpoint (or dedicated section endpoint) in later phases.

Reason:
- receipt operations are high-churn and file-heavy
- separating them reduces full-form request size and risk of PHP input limits

### 4) Section-level patch endpoints

Agreed with your structure. Target split should be:
- manifest fields endpoint
- manifest sharing groups + members endpoint
- manifest rooms + room members endpoint
- manifest documents endpoint
- manifest member receipt files endpoint

This is the best long-term shape for reliability and payload efficiency.

### 5) Rename `traveler` to `member`

Strongly agreed, with migration safety.

Direction:
- target terminology everywhere: `member`
- avoid big-bang rename; run staged compatibility aliasing first
- keep old names temporarily where needed to avoid regressions

Suggested final naming targets:
- `manifest_traveler_id` -> `manifest_member_id`
- `travelers` payload key -> `members`
- variable names/comments/services/controllers updated gradually

## Final Direction Chosen

Selected strategy: **Path A (incremental migration)**.

Why Path A:
- lower regression risk
- easier rollback
- faster measurable improvements early
- compatible with active manifest feature development

## Execution Plan (Path A)

Estimated total phases: **7 phases**.

Each phase should complete with:
- code changes
- targeted tests
- no behavior regression in existing manifest flow

### Phase 1: Payload Slimming Stabilization

Scope:
- keep current shape
- remove redundant fields from submit payload (already started for roomLists)
- ensure backend resolves missing redundant fields from member identifiers

Exit criteria:
- roomLists submits without `customer_id` / `customer_confirmation_id`
- no regression in room assignment and document persistence

### Phase 2: Canonical Read Adapter (GET)

Scope:
- add server adapter to produce canonical sections in parallel:
  - `manifest`
  - `manifest_sharing_groups`
  - `manifest_rooms`
  - `documents`
- keep legacy response fields for compatibility

Exit criteria:
- frontend can consume legacy unchanged
- canonical sections are available for migration

### Phase 3: Canonical Submit Adapter (POST)

Scope:
- controller accepts canonical keys in parallel with legacy keys
- add transform layer: canonical -> existing service sync shape

Exit criteria:
- both payload styles are accepted
- validation and persistence parity confirmed

### Phase 4: Frontend State Migration To Sections

Scope:
- migrate ManifestForm internal state to canonical section structure
- keep UI behavior identical
- submit canonical shape while retaining fallback if needed

Exit criteria:
- all tabs save correctly with canonical payload
- payload size reduced further for 50-member scenario

### Phase 5: Section Endpoints Introduction

Scope:
- create patch endpoints:
  - manifest core
  - sharing groups + members
  - rooms + room members
  - documents
- keep full submit endpoint for backward compatibility

Exit criteria:
- section save flows work independently
- full submit still works during transition

### Phase 6: Receipt Files Separation

Scope:
- move receipt file updates to dedicated endpoint
- remove receipt-heavy payload from full form submit

Exit criteria:
- receipt upload/remove works independently
- full form payload remains compact

### Phase 7: Terminology Rename (`traveler` -> `member`)

Scope:
- staged rename across payload keys, variables, comments, and endpoints
- DB field rename only when compatibility layer is ready

Exit criteria:
- external contract and internal code consistently use `member`
- compatibility fallback removed only after full migration

## Prompt Pack For GPT-5.3-Codex (Per Phase)

Use each prompt in sequence. Each prompt assumes the previous phase is merged and green.

### Prompt Phase 1

```text
You are GPT-5.3-Codex working on Laravel + Inertia React manifest form.

Goal:
Reduce manifest submit payload size without changing behavior.

Tasks:
1) In manifest form submit mapper, ensure roomLists rows do not send customer_id and customer_confirmation_id.
2) Keep identifiers needed for relation: manifest_traveler_id (current name), customer_confirmation_member_id, package_official_id.
3) Ensure backend normalization resolves missing redundant IDs from existing identifiers.
4) Do not modify protected input components.
5) Run targeted tests:
    - multi_location_room_edits_and_non_receipt_document_tabs
    - room_list_order_can_be_different_between_hotels_and_is_persisted

Output:
- list changed files
- summary of payload reduction
- test results
```

### Prompt Phase 2

```text
You are GPT-5.3-Codex.

Goal:
Add canonical GET response sections in parallel with existing legacy response shape.

Tasks:
1) In manifest service/controller response builder, include:
    - manifest
    - manifest_sharing_groups (with members)
    - manifest_rooms (with room members)
    - documents
2) Keep current legacy response fields unchanged for compatibility.
3) Add tests asserting canonical keys are present and consistent with legacy data.

Output:
- changed files
- example GET JSON snapshot
- test results
```

### Prompt Phase 3

```text
You are GPT-5.3-Codex.

Goal:
Accept canonical submit payload in parallel with legacy payload.

Tasks:
1) Extend controller normalizer to accept canonical keys:
    - manifest
    - manifest_sharing_groups
    - manifest_rooms
    - documents
2) Transform canonical payload into the currently supported internal sync format.
3) Preserve backward compatibility with legacy submit payload.
4) Add tests for both payload styles producing same persisted result.

Output:
- changed files
- normalization mapping rules
- test matrix and results
```

### Prompt Phase 4

```text
You are GPT-5.3-Codex.

Goal:
Migrate frontend manifest state to canonical section structure without UI regressions.

Tasks:
1) Refactor ManifestForm state adapters to derive tab data from canonical sections.
2) Keep current tab UX and ordering rules:
    - group sort_order then member sort_order.
3) Submit canonical payload; keep temporary fallback transform if needed.
4) Verify all tabs: main, rooms, airline, checklist, documents, receipts.

Output:
- changed files
- before/after payload example
- test results and manual QA checklist
```

### Prompt Phase 5

```text
You are GPT-5.3-Codex.

Goal:
Introduce section-level patch endpoints while keeping full submit endpoint.

Tasks:
1) Add endpoints and handlers for:
    - manifest core
    - sharing groups + members
    - rooms + room members
    - documents
2) Reuse existing validation and sync logic where possible.
3) Keep full submit endpoint operational.
4) Add feature tests per new endpoint.

Output:
- endpoint list
- changed files
- tests and results
```

### Prompt Phase 6

```text
You are GPT-5.3-Codex.

Goal:
Separate member receipt file updates into dedicated endpoint.

Tasks:
1) Add dedicated API/route for member receipt file CRUD.
2) Remove receipt file bulk payload from full manifest submit path.
3) Ensure existing receipt documents remain intact for unchanged members.
4) Add tests for add/remove/replace receipt files.

Output:
- changed files
- request/response examples
- test results
```

### Prompt Phase 7

```text
You are GPT-5.3-Codex.

Goal:
Complete terminology migration from traveler to member.

Tasks:
1) Rename code-level symbols and comments from traveler to member.
2) Introduce compatibility aliases where contract cannot change immediately.
3) Plan and execute safe rename of manifest_traveler_id to manifest_member_id.
4) Keep all existing behavior and tests green.

Output:
- rename map (old -> new)
- changed files
- migration notes and rollback plan
```

## Success Criteria For The Whole Restructure

1. No regressions in current manifest workflows.
2. 50-member manifest submit remains under practical PHP input limits.
3. Room, document, and receipt persistence stays correct.
4. Canonical section contract is adopted for both GET and submit.
5. Terminology is consistently migrated to `member` by final phase.
