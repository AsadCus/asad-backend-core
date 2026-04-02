# Order Form and Invoice Builder Behavior Specification

This document is the single source of truth for Order form invoice-building behavior, numbering flow, and regression guardrails.

## Scope

- Frontend orchestration: `resources/js/pages/orders/form.tsx`
- Frontend create handoff: `resources/js/pages/orders/create.tsx`
- Frontend invoice build and numbering engine: `resources/js/pages/orders/lib/invoice-builders.ts`
- Frontend model number input behavior: `resources/js/components/model-number-input.tsx`
- Frontend order validation: `resources/js/pages/orders/validation.ts`
- Backend create flow seed source: `app/Http/Controllers/OrderController.php`
- Backend order service: `app/Services/OrderService.php`
- Backend numbering service: `app/Services/NumberingService.php`
- Numbering batch route: `routes/web.php`
- Invoice model fallback number hook: `app/Models/Invoice.php`

## Core Objective

Make Order form behavior deterministic and easier to maintain with less logic and fewer lines by using one canonical numbering pipeline.

## Product Goals

1. Build installment invoice numbers as one sequence, not row-by-row async mutation.
2. Keep create-mode initial invoice numbers ready at first render.
3. Reuse one increment parser/normalizer in one place only.
4. Support batch-capable numbering through the standardized numbering service.
5. Prevent hidden overwrite loops from multiple numbering sources.

## Runtime Contract

### Create Flow Contract

1. Backend must generate invoice seed for create mode based on payment plan and initial invoice count.
2. Create page must pass seed values into Order form as props.
3. Order form must build initial invoices synchronously and apply canonical sequence in builder utilities.
4. Invoice row number inputs in Order form must not auto-suggest on mount.

### Rebuild Contract

1. Payment plan, deposit, and installment-count changes must rebuild via one shared function.
2. Rebuild must preserve valid existing invoice numbers when possible.
3. Missing or duplicate numbers must be repaired only through canonical sequence logic.

### Save Contract

1. Frontend submits resolved invoice numbers from form state.
2. Backend model-level auto number generation is fallback-only when invoice number is empty.
3. Backend fallback must not be treated as primary UI numbering source.

## Canonical Numbering Standard

### Single Source of Truth

`applyInvoiceNumberingSequence` is the canonical invoice numbering normalizer.

Location:

- `resources/js/pages/orders/lib/invoice-builders.ts`

### Seed Generation and Handoff

- Controller create flow computes `invoiceNumberSeed`.
- Service method `suggestDraftInvoiceNumbers()` uses batch-capable numbering service.
- Create page passes `initialInvoiceNumberFormatId` and `initialInvoiceNumbers` into `OrderForm`.

Locations:

- `app/Http/Controllers/OrderController.php`
- `app/Services/OrderService.php`
- `app/Services/NumberingService.php`
- `resources/js/pages/orders/create.tsx`

### Batch Model Number Capability

The numbering system must continue supporting batch suggestions through:

- Service: `NumberingService::suggestBatchNumbers()`
- Route: `POST /numbering-formats/suggest-batch`

Location:

- `routes/web.php`

## Efficiency and Simplification Rules

1. Prefer deterministic pure transforms over async correction effects.
2. Keep all invoice numbering repair in invoice builder utilities.
3. Keep form file focused on orchestration, not numbering internals.
4. Avoid duplicate parser/increment logic in form-level code.
5. Remove dead, redundant, or debugging-only runtime logic.
6. In create-mode build/rebuild, derive contiguous invoice numbers from the first backend seed number (index-based sequence), then map by invoice index.

## Critical Guardrails

1. Never have more than one active runtime numbering pipeline in Order form.
2. Do not reintroduce create-time async numbering effects that mutate invoice numbers post-initialization.
3. Keep `skipInitialAutofill` enabled for invoice `ModelNumberInput` rows in Order form.
4. Do not silently overwrite user-entered invoice numbers unless normalization is explicitly required by a rebuild action.
5. Keep numbering behavior idempotent for repeated rebuild triggers.
6. Invoice row mutations must use functional `setData` updates to avoid stale-snapshot overwrite (`last row wins`) when multiple row components emit updates during the same render cycle.
7. Do not add runtime invoice-number repair/normalization `useEffect` in `OrderForm`; numbering correctness must be guaranteed by initial invoice construction (`createInitialInvoices`) and rebuild path (`rebuildInvoicesFromSource`) only.
8. Seeded installment numbering must be deterministic by index (`seed[0]`, `seed[0]+1`, `seed[0]+2`, ...), not dependent on row mount/update timing.

## Findings (2026-04-02)

### Confirmed Good

1. Backend seed generation exists and is batch-capable.
2. Seed values are passed from create page to Order form.
3. Builder already supports deterministic sequence application via `applyInvoiceNumberingSequence`.
4. `ModelNumberInput` supports `skipInitialAutofill` and is used in invoice rows.

### Confirmed Risk (Resolved by latest simplification)

1. A create-only async effect in `OrderForm` was an additional numbering path and could lock partial results.
2. That redundant effect was removed to keep numbering deterministic from builder-only flow.
3. Row-level invoice callbacks previously wrote from stale `data.invoices` snapshots; when multiple invoice rows emitted updates, the last callback could overwrite earlier invoice numbers. This is mitigated by functional `setData` updates and index-scoped updater helpers.

### Still Important to Preserve

1. Backend fallback numbering in `Invoice::creating` remains a safety net only.
2. Order form should continue using preseeded synchronous initialization for create mode.

## Key Features to Preserve

1. Installment minimum 3 invoices validation.
2. Installment rebuild from quotation items with deposit logic.
3. Payment-method extension application and recalculation in builder.
4. Paid/receipt safeguards during order edit/update.

## Acceptance Criteria

1. Installment create shows sequential invoice numbers on first render.
2. No first-two-blank/third-filled numbering anomaly in create mode.
3. Rebuild actions preserve or deterministically repair sequence.
4. Invoice numbering behavior remains stable without async post-init numbering effects.
5. Payload invoice numbers match rendered form state.

## Testing Guidance

Minimum checks after numbering-related changes:

1. Create order from installment quotation and verify all invoice numbers prefilled sequentially.
2. Increase installment invoice count and verify appended sequence continuity.
3. Change payment plan or deposit settings and verify sequence remains stable.
4. Run focused frontend lint/type checks for touched order files.
5. Run relevant feature tests if backend numbering flow is touched.

## Change Management Rules

1. This file is authoritative for order-form invoice builder behavior.
2. Any new numbering path must document reason, risk, and rollback strategy in the same change.
3. Any numbering behavior change must include regression verification for create and rebuild flows.
4. Keep this specification updated whenever numbering ownership shifts between frontend and backend.
