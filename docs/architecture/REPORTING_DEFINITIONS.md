# TravelOps reporting definitions

The `/ops/reports` report and CSV use the same queries. Date filters are
inclusive Taiwan calendar dates and converted to a half-open UTC interval.
Date range is limited to 92 days. Amounts are stored and exported as integer
TWD minor units. Fees and taxes are outside the calculation.

| Metric | Definition |
| --- | --- |
| Successful sales | Count and sum of captured payments with `paid_at` in the interval. A subsequently refunded payment still contributes to original gross sales. |
| Successful refunds | Refunds in `processed` with `processed_at` in the interval. MVP history uses `updated_at`. Pending, declined and manual-review rows contribute zero to refunded amount. |
| Net sales | Captured gross in the interval minus successfully settled refunds in the interval. |
| Failed refunds | Failed and manual-review refunds whose latest update falls in the interval. |
| Redemption rate | Current redeemed entitlements divided by all entitlements on tickets created in the interval. Zero eligible rights gives zero. |
| Provider issuance | Outbox creation date defines the cohort. Success ratio is succeeded divided by cohort size, and confirmation latency is measured to completion. Attempts after the first include status checks and callbacks. |
| Inventory | Held and sold are current reservation states of the selected date cohort. Available stock is the current shared balance, filtered by product supplier when selected. Movement counts and deltas use the movement timestamp. |
| Reconciliation | Count by active, reviewed and converged status for the selected business dates. A scoped store or channel can only attribute discrepancies with a matching order. |
| POS closing | Captured sales grouped by method across the selected Taiwan business dates for an assigned store. The refunded count concerns those original sales. |

The export uses UTF-8 with a BOM for spreadsheet compatibility. String cells
with formula-like prefixes are apostrophe-prefixed. It contains aggregates
and store or supplier names. QR credentials and customer identifiers are
excluded. POS supervisors access their store's daily closing view.
