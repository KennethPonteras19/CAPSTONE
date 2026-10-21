# Staff workspace

The six staff screens use the configured Laravel database. Run `php artisan migrate` when installing this change on another machine. The current workspace database has already been migrated; its pre-migration SQL backup is in `storage/app/backups`.

## Daily use

1. Add products, prices, and stock through the existing admin product and inventory pages. The staff catalog reads those same records. An empty database displays empty states.
2. Add customer contact details under **Customers**. Contact-only records do not create login credentials. Existing customer accounts retain their credentials.
3. In **Point of Sale**, select products, quantities, discounts, order type, and payment details. Use **Charge** for an unpaid order. Delivery orders require a customer and address; partial payments require a customer.
4. **Hold Order** and **Save Quotation** persist the cart without reserving stock or recording sales. Resume from **Orders**. Current prices and stock are rechecked at checkout.
5. Checkout opens a printable receipt. **Orders** manages fulfillment and additional payments. Pending → Processing → Ready → Completed, with Out for Delivery before Completed for deliveries.
6. Cancelling an eligible unpaid order restores stock exactly once. Completed orders and orders with received payments cannot be cancelled here. A refund workflow is outside this change.

## Synchronization and totals

- Checkout, payments, and stock changes run inside database transactions. Products are locked while stock is deducted. Duplicate submission keys prevent repeat checkout/payment writes. Draft/status versions reject stale edits.
- POS refreshes products every 30 seconds and has a manual Refresh button. Idle dashboard, inventory, orders, and transaction lists refresh every 60 seconds. Refresh pauses after form changes to preserve edits.
- Prices come from the database; the POS rejects prices that changed after the cart was reviewed. Amounts are calculated in integer cents. Cash can include change; Online and Cheque require references and cannot exceed the balance.
- Online and Cheque are records of payments received outside this application; no payment gateway or bank settlement is performed.
- Sales totals include delivery fees and discounts. No additional tax or withholding calculation is introduced; business tax settings would need a separate agreed specification.
- Checked-out order items are mirrored into the existing `sales` table for admin sales/reports. Delivery fees are allocated once to the first sales row. Payment updates from admin Sales also update the order/payment history. Linked sale rows cannot be independently deleted.
- Legacy receipts remain available in Payment History and are grouped by their existing receipt number. Customer and method details absent from historical rows are shown as not recorded. Historical duplicate receipt numbers cannot be reliably separated without source records.
- Dashboard transaction counts count receipts; items sold sums quantities. Top sellers are actual sales over the last 30 days, not a prediction.
- Inventory uses 1–5 units as low stock, matching the existing inventory threshold. Staff inventory is read-only.

## Verification

`php artisan test` runs on isolated in-memory SQLite, protected by `tests/TestCase.php`. `StaffWorkflowTest` covers checkout, rollback, duplicate requests, validation, saved drafts, partial payments, cancellation, fulfillment, customers, legacy history, role access, admin payment synchronization, and stale prices.

Browser verification scripts and screenshots are under `storage/app/verification`. The browser fixture uses a separate SQLite file. `staff-database-smoke.php` renders all six screens against the configured database; `staff-mysql-transaction-check.php` checks writes inside an outer transaction that is always rolled back.
