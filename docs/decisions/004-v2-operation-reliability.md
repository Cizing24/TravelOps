# ADR 004. V2 transaction and delivery boundaries

Keep the modular monolith and the authoritative MySQL stock balance.
Checkout reserves under the same product and balance locks for both channels.
POS adds store-scoped replay protection and commits sale and local mock
capture in the same transaction. Stock movements are recorded alongside the
transitions they describe.

Reuse the issuance Outbox for provider work and the Inbox for callbacks.
Persist a lease and stable request key before contacting an adapter. Treat
the sandbox ledger as independent evidence, including when a worker dies
between provider acceptance and local confirmation. Allow status queries
before replaying a request.

Keep one full-refund row per order. Approval does not settle the payment.
Approved and unresolved refunds suspend redemption under the order lock.
Only confirmed refunds revoke local entitlements and release sold stock.
The same transaction schedules cancellation of an existing supplier issuance.
The worker retries cancellation by the original request key without holding
order locks. Unconfirmed cancellations stay visible for operator review.
Historical `processed` refunds remain terminal. The scope excludes partial
refunds and manual rewriting of captured amounts.

Derive reconciliation findings from observed internal and sandbox states.
Keep comparisons and manual dispositions separate from the original
transactions. Deduplicate by source, business day, request and finding kind.
Reruns converge open findings when their evidence disappears.

Write notification inbox rows and eligible delivery records with the
transaction they describe. Use unique recipient, template and event keys.
Recheck opt-in before delivery. The durable row is also the mock delivery
record. Mock channels create no external messages.
