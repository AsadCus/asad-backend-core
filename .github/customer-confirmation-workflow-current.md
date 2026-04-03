# Customer Confirmation and Confirmed Customer Workflow (Current)

## Main Pages

- Confirmed customers index: grouped customer confirmations with `is_holding = false`.
- Holding customers index: grouped customer confirmations with `is_holding = true`.

Both pages use the same Inertia page with different `indexUrl` and title.

## Group Data Model

Each group row includes:

- customer/enquiry/package summary
- totals: paid, total, overpaid
- active member count
- quoted member count
- `can_create_quotation`
- `can_delete` (true only when active member count is 0)

## Member Data Model

Each member row includes:

- identity and contact profile
- sharing plan and relationship
- payment status
- paid/total/overpaid amounts
- latest invoice payment method snapshot

## Group Actions

- Copy public edit link
- Move members
- Refund
- Overpaid refund
- Create quotation (when `can_create_quotation = true`)
- Delete (visible only when `can_delete = true`)

## Member Actions

- Edit
- Move members
- Cancel member
- Refund
- Overpaid refund (shown only when overpaid amount is available)

## Deletion Rule

Customer confirmation delete is allowed only when all members are cancelled.

Behavior:

- Group row `can_delete` controls delete action visibility.
- Backend enforces the same rule in `deleteGroup`.
- Group uses soft delete (`deleted_at`) and members are hard-deleted.
- Related enquiry status reverts from `confirmed` to `contacted` after successful delete.

## Move Members Rule

- Move action creates a new customer confirmation and moves selected members.
- If target package is selected, destination group is non-holding.
- If target package is empty, destination group is holding.

## Refund Dialog Rule

- One purpose per submit (`cancel` or `overpaid`).
- Per-member row supports percentage or fixed amount.
- Payment method options come from payment method masters.

## Billing and Status Reconciliation

- Payment and overpaid values are computed from invoice/receipt and package context.
- Member status sync excludes cancelled members from automatic overwrite.
- Package seat recalculation is triggered after key mutations.

## Routes and Services

Key backend components:

- `CustomerConfirmationController`
- `CustomerConfirmationService`
- `PaymentStatusService`

Main endpoints used by page actions:

- `POST /customer-confirmations/{id}/move-members`
- `POST /customer-confirmations/{id}/refunds`
- `POST /customer-confirmations/{id}/generate-quotations`
- `DELETE /confirmed-customer/{id}`

## Notes for AI Implementers

- Keep frontend visibility rules aligned with backend guards.
- Do not bypass `can_delete` and delete guard logic.
- Keep refund workflow document-centric: refund invoice + refund receipt.
- Keep refund invoices out of order-form editing flow.
