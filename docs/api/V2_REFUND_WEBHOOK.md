# V2 mock refund callback

`POST /webhooks/mock-refunds` is enabled in the test environment.
The local demo sends the same signed event through a Redis job.
Set `Content-Type: application/json` and `X-TravelOps-Signature`.
The signature is hexadecimal HMAC-SHA256 of `refund\n<raw body>`
using the mock webhook secret, with a newline after `refund`.

| Field | Value |
| --- | --- |
| `event_id` | UUID for this provider event |
| `request` | Stable refund request UUID |
| `order` | Order reference UUID |
| `amount` | Positive integer amount in minor units |
| `currency` | `TWD` |
| `status` | `pending`, `succeeded` or `failed` |
| `sent_at` | Unix time within five minutes of receipt |

A confirmed event must match the request, order, amount and authoritative
mock statement. Valid duplicates return the original outcome. Late events
cannot revert a terminal refund. Response statuses include 401 for invalid
signature, 409 for an event key reused with different bytes and 422 for
invalid payload, timestamp or mismatch. A successful response returns
`outcome`, which may be `pending`, `processed`, `failed` or `ignored_final`.
