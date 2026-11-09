# TravelOps V2 data model

The V2 migrations extend the existing MySQL database. Existing orders default
to the online channel and use the original mock payment method. Existing
suppliers default to synchronous delivery. The inventory ledger starts at the
V2 migration boundary. Opening balances and historical reservations remain in
`inventory_balances` and `inventory_reservations`.

```mermaid
erDiagram
    USER ||--o{ STORE_USER : assigned
    STORE ||--o{ STORE_USER : authorizes
    STORE o|--o{ ORDER : records
    USER ||--o{ ORDER : acts
    ORDER ||--|| ORDER_ITEM : snapshots
    ORDER ||--|| PAYMENT : captures
    ORDER ||--|| INVENTORY_RESERVATION : holds
    ORDER ||--o| OUTBOX_EVENT : issues
    OUTBOX_EVENT ||--o{ INTEGRATION_ATTEMPT : tracks
    OUTBOX_EVENT ||--o| SUPPLIER_STATEMENT : "matches by trace ID"
    ORDER ||--o{ WEBHOOK_EVENT : receives
    ORDER ||--o{ INVENTORY_MOVEMENT : accounts
    PRODUCT ||--o{ INVENTORY_MOVEMENT : changes
    SUPPLIER ||--o{ SUPPLIER_STATEMENT : supplies
```

Issuance claims the outbox event with a two-minute lease, calls the provider
outside the transaction, then applies the result if the lease token still
matches. The UUID trace is also the provider request key. A successful callback
can settle before the originating worker writes back. Both paths lock the order
and outbox row and share the same ticket creation code.

Online and POS checkout lock the same product and inventory balance. A POS
order records the authenticated cashier as its actor. A store-scoped unique key
prevents replay from another cashier at that store. POS checkout and local mock
capture commit together. There is one sale per reservation and sale has a
zero available-stock delta because the reservation already reduced availability.

Stock movement uniqueness is scoped to order and movement kind. Adjustments
have an operator and reason and update the same authoritative balance. Order
reads and ticket links in the POS flow require active store membership.
