# Scheduled Jobs and Console Commands

This document tracks background jobs and operational Artisan commands in this project, including purpose and usage.

## Active Scheduled Tasks

### 1) `FinancialYearRolloverJob`

- Type: queued job
- File: `app/Jobs/FinancialYearRolloverJob.php`
- Schedule: daily
- Registered in: `routes/console.php`
- Purpose:
    - Progresses financial year state by calling `FinancialYear::progressFinancialYear()`.
    - Runs inside a DB transaction.

### 2) `packages:sync-lifecycle-status`

- Type: Artisan command
- File: `app/Console/Commands/SyncPackageLifecycleStatusCommand.php`
- Schedule: daily
- Registered in: `routes/console.php`
- Purpose:
    - Recalculates `seats_left` and package lifecycle status (`open`, `full`, `closed`, `completed`).
    - Applies lifecycle rules consistently even when no user actions happen that day.

## Console Commands (Operational)

### 1) `customer-confirmations:backfill-numbers`

- File: `app/Console/Commands/BackfillCustomerConfirmationNumbers.php`
- Purpose:
    - Backfills missing customer confirmation numbers.

### 2) `invoices:backfill-refund-invoice-numbers`

- File: `app/Console/Commands/BackfillRefundInvoiceNumbersCommand.php`
- Purpose:
    - Clears `invoice_number` for refund invoices.
    - Preserves latest invoice numbering counter.
- Options:
    - `--dry-run` to preview without database writes.

### 3) `financial:generate-transactions`

- File: `app/Console/Commands/GenerateFinancialTransactions.php`
- Purpose:
    - Generates financial transactions from paid invoices/receipts.
    - Cleans orphaned and cancelled-quotation transaction records.
- Options:
    - `--year=<ID>` process one financial year
    - `--all` process all active financial years

### 4) `receipts:normalize-invoice-status`

- File: `app/Console/Commands/NormalizeReceiptInvoiceStatusCommand.php`
- Purpose:
    - Normalizes receipt totals against invoice totals.
    - Re-syncs invoice and member payment statuses.
- Options:
    - `--dry-run` to preview without database writes.

### 5) `financial:test-rollover`

- File: `app/Console/Commands/TestFinancialYearRollover.php`
- Purpose:
    - Interactive manual test for financial-year rollover behavior.

## Shared Hosting (Hostinger) Notes

### Scheduler cron

Set a cron job to execute Laravel scheduler every minute:

```bash
php /home/<USER>/<PROJECT_PATH>/artisan schedule:run
```

### Queue note

`FinancialYearRolloverJob` is queued. Ensure queue worker processing is available in hosting environment, otherwise queued jobs will not execute.
