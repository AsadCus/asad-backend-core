# Refund Feature Behavior

## Scope

This document defines refund behavior for customer confirmation members,
including reconciliation rules when package/pricing-plan changes happen after
billing documents already exist.

## Entry Points

- UI: Confirmed Customer page refund dialog.
- Endpoint: `POST /customer-confirmations/{id}/refunds`.
- Service: `CustomerConfirmationService::createRefundReceipts`.

## Refund Purposes

- `cancel`
- `overpaid`

Purpose applies to all selected rows in one submit action.

## Validation Rules

- Member must belong to the selected customer confirmation.
- Member must have paid amount greater than 0.
- `overpaid` purpose requires member overpaid amount greater than 0.
- Refund amount is resolved from mode:
- `percentage`: 0 to 100
- `fixed`: 0 to max allowed
- Max allowed:
- `cancel`: paid amount
- `overpaid`: overpaid amount

## Generated Documents

Each refunded member creates a dedicated refund document set:

1. Quotation item with negative rate.
2. Invoice with:

- `status = refund`
- negative `amount`
- same order as the source billing invoice

3. Receipt linked to the refund invoice with negative `amount`.

This design supports repeated refunds for the same member because each submission creates new refund invoice and receipt records.

## Payment Method and Description

- Payment method priority:

1. Request payload row payment method
2. Latest non-refund invoice payment method or latest receipt payment method
3. Fallback `refund`

- Description default: `Receipt For Refund`

## Member Status Effect

- `cancel` refund: member status is set to `cancelled`.
- `overpaid` refund: member stays active and status is recalculated by financial snapshot.

## Package/Pricing Change Reconciliation

When member package/pricing changes after billing exists, do not hard-delete
historical billing documents that already represent the prior sales flow.

Use adjustment documents instead:

- Upward delta (new required amount is higher): create balance invoice.
- Downward delta with unpaid obsolete billed portion: create void adjustment
  invoice (status `cancelled`) with negative amount and no receipt.
- Downward delta with paid overage: use refund flow (`overpaid`) to return
  overpaid cash amount.

### Core Formula

For one member:

- `paid_amount` = proportional paid allocation from receipts.
- `billed_amount` = active member-linked quotation item total.
- `required_amount` = package price by current sharing plan.

Downward-change void amount:

- `excess_billed = max(0, billed_amount - required_amount)`
- `unpaid_amount = max(0, billed_amount - paid_amount)`
- `void_amount = min(excess_billed, unpaid_amount)`

If `void_amount > 0`, create:

1. Quotation header + detail item with `rate = -void_amount`.
2. Invoice with:
    - `status = cancelled` (void)
    - `amount = -void_amount`
    - same order as source billing flow
3. No receipt for void adjustment invoice.

This ensures only unpaid obsolete amount is voided while paid overage remains
handled via refund.

### Example

- Previous billed: 7000
- Paid: 5000
- New required: 4000

Computation:

- `excess_billed = 7000 - 4000 = 3000`
- `unpaid_amount = 7000 - 5000 = 2000`
- `void_amount = min(3000, 2000) = 2000`

Outcome:

- Create void adjustment `-2000` (cancelled invoice, no receipt)
- Remaining overpaid against new required can be refunded via `overpaid` flow.

## Financial Snapshot Behavior

- Refund invoices are excluded when resolving latest source invoice.
- Refund receipts are included in paid amount calculation using proportional allocation by absolute item subtotal.
- This allows overpaid balance to reduce after refund documents are created.

## Visibility and Lock Rules

- Refund invoices are visible in Invoice index.
- Refund invoices are hidden from Order form editing flow.
- Refund invoices cannot be edited or deleted.
- Manual receipt creation is blocked for refund invoices.
- Void adjustment invoices use `cancelled` status and have no receipts.

## Sales Index Default Status Filters

To keep sales indexes focused and reduce noise, default status filters are:

- Quotation index: show all quotation statuses except `rejected`, `expired`,
  and `cancelled` (void).
- Order index: filter by linked quotation status with the same default as
  quotation index.
- Invoice index: show all invoice statuses except `cancelled` (void).
- Receipt index: filter by linked invoice status and show all except
  `cancelled` (void).

Users can still change filters manually from the status column filter controls.

## Status Domain

Invoice status values are centralized in `app/Support/InvoiceStatus.php` and include `refund`.

## Related Files

- `app/Services/CustomerConfirmationService.php`
- `app/Services/PaymentStatusService.php`
- `app/Services/OrderService.php`
- `app/Services/InvoiceService.php`
- `app/Services/ReceiptService.php`
- `resources/js/pages/confirmed-customer/index.tsx`
- `resources/js/pages/quotations/index.tsx`
- `resources/js/pages/orders/index.tsx`
- `resources/js/pages/invoices/index.tsx`
- `resources/js/pages/receipts/index.tsx`
