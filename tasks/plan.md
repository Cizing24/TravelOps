# TravelOps implementation

The master plan is the specification. Implement the catalog and checkout as one vertical slice, then durable issuance, then venue redemption and refunds. Connect the customer and operations surfaces to those same actions. Run the A–E journeys and independent MySQL process races before writing the acceptance report.

Transactions serialize on the order for state transitions. Inventory creation and checkout serialize on the product. A scheduler reconciles the transactional outbox and Redis workers process issuance. Work is confined to local demo and dedicated test databases.
