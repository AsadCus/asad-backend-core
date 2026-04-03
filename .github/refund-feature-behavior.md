# Refund Feature Behavior

## Scope

This document defines refund behavior for customer confirmation members.

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

## Financial Snapshot Behavior

- Refund invoices are excluded when resolving latest source invoice.
- Refund receipts are included in paid amount calculation using proportional allocation by absolute item subtotal.
- This allows overpaid balance to reduce after refund documents are created.

## Visibility and Lock Rules

- Refund invoices are visible in Invoice index.
- Refund invoices are hidden from Order form editing flow.
- Refund invoices cannot be edited or deleted.
- Manual receipt creation is blocked for refund invoices.

## Status Domain

Invoice status values are centralized in `app/Support/InvoiceStatus.php` and include `refund`.

## Related Files

- `app/Services/CustomerConfirmationService.php`
- `app/Services/PaymentStatusService.php`
- `app/Services/OrderService.php`
- `app/Services/InvoiceService.php`
- `app/Services/ReceiptService.php`
- `resources/js/pages/confirmed-customer/index.tsx`
- `resources/js/pages/invoices/index.tsx`
- `resources/js/pages/orders/index.tsx`
