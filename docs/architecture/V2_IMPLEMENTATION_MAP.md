# TravelOps V2 implementation map

The starting revision is `06ecdb57ea6ca6a7b6be26f3a3cbdbeb4f8b15e3` on
`codex/travelops-closeout`, the verified upstream default branch. The V2 branch
is `codex/travelops-v2`. The two untracked planning and closeout documents are
kept in the workspace and excluded from V2 commits.

| Order | Slice | Existing entry points and contracts | Verification checkpoint |
| --- | --- | --- | --- |
| 1 | Supplier delivery | `PaymentWebhook` creates the issuance outbox event. `Issuance` and `IssueTickets` own retries and final ticket creation. Keep the order and outbox lock order, ticket uniqueness and attempt history. | Provider calls outside locks, stable request identity, duplicate and late callbacks, Redis worker recovery. |
| 2 | POS and inventory | `Checkout` owns reservations and the shared balance. Capture uses `PaymentWebhook`. Reuse both paths with store authorization and stock movements in the same transactions. | Last-unit race on MySQL, replay, cash and mock card, store-scoped lists and daily totals. |
| 3 | Refunds | `RefundOrder` owns review, `Redeem` locks the order before redeeming, and `Checkout` releases sold stock. Preserve historical `processed` rows as successful refunds. | Pending blocks redemption, authenticated callbacks, one reversal, retry and failure behavior. |
| 4 | Reconciliation | Compare persisted sandbox statements with `Payment`, `OutboxEvent` and `Refund`. Keep findings and operator review separate from the original transactions. | Missing, mismatch, duplicate and aged pending findings, convergence, audit and permissions. |
| 5 | Notifications | Produce durable notifications alongside the payment, issuance and refund transitions. Reuse existing owner checks for links and UTC ticket expiry for reminders. | Recipient isolation, preferences, deduplication, expiry and mock channel retries. |
| 6 | Reports | Query original transactions, stock movements and reconciliation findings. Keep the existing Filament dashboard. | Taiwan date boundaries, integer money, authorized filters and CSV safety. |

New schema is additive. Use the protected `travelops_test` database for
PHPUnit and isolated Compose volumes for Linux acceptance. Run the existing
MVP browser journey, V2 journeys F–J and a migration over existing MVP data
before declaring V2 complete.
