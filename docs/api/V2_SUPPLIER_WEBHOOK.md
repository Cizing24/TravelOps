# V2 mock supplier callback

`POST /webhooks/mock-suppliers/{supplier_id}` is available in the test
environment with Demo enabled and a webhook secret of at least 32 bytes.
The local Demo uses a Redis job to deliver the same event to the receiver.

Send a JSON body no larger than 4,096 bytes with these fields.

| Field | Value |
| --- | --- |
| `event_id` | Provider-generated UUID |
| `request` | Issuance outbox trace UUID |
| `status` | `pending` or `succeeded` |
| `sent_at` | Unix time in seconds, within five minutes of reception |

`X-TravelOps-Signature` is the hexadecimal HMAC-SHA256 over
`supplier:<supplier_id>\n<raw request body>`, where `\n` is a newline.
The receiver checks the supplier, order reference, quantity and completion
against the stored Sandbox statement. The inbox records the body hash and
outcome. Reusing an event ID with different bytes returns HTTP 409. Invalid
signatures return 401. Invalid payloads, timestamps and supplier references
return 422. An accepted event returns HTTP 200 with an `outcome` of
`awaiting_callback`, `succeeded` or `ignored_final`.

Queue redelivery and provider queries share the original request key.
The `lost_reply` mock creates a statement and reports an unknown outcome.
The next attempt looks up the statement before submitting any request.
The `webhook` adapter exposes a five-second delay. If the callback job
is lost, the outbox relay schedules a status query.
