# TravelOps 資料模型

```mermaid
erDiagram
    users ||--o{ merchant_users : grants
    venues ||--o{ merchant_users : scopes
    suppliers ||--o{ venues : owns
    suppliers ||--o{ products : issues
    products ||--o{ product_entitlements : defines
    venues ||--o{ product_entitlements : grants
    products ||--|| inventory_balances : stocks
    users ||--o{ orders : places
    orders ||--|| order_items : snapshots
    products ||--o{ order_items : purchased_as
    suppliers ||--o{ order_items : snapshotted_as
    orders ||--|| inventory_reservations : reserves
    products ||--o{ inventory_reservations : holds
    orders ||--|| payments : pays
    orders ||--o{ webhook_events : receives
    orders ||--o| outbox_events : schedules
    outbox_events ||--o{ integration_attempts : tracks
    orders ||--o{ tickets : fulfills
    tickets ||--|{ ticket_entitlements : includes
    venues ||--o{ ticket_entitlements : accepts
    ticket_entitlements ||--o| redemptions : consumed_by
    users ||--o{ redemptions : performs
    venues ||--o{ redemptions : records
    orders ||--o| refunds : requests
    users ||--o{ refunds : requests_or_reviews
    orders |o--o{ audit_logs : correlates
    users |o--o{ audit_logs : acts
```

| 唯一鍵 | 用途 |
|---|---|
| `orders(user_id, idempotency_key)` | 相同旅客與下單意圖只建一單 |
| `webhook_events(provider, external_event_id)` | 辨識重送，並核對原始 payload 雜湊 |
| `payments(order_id)`、`payments(idempotency_key)` | 單筆付款聚合及付款參考 |
| `inventory_balances(product_id)`、`inventory_reservations(order_id)` | 庫存原子調整及單一預留 |
| `outbox_events(order_id)`、`integration_attempts(outbox_event_id, number)` | 單一開券意圖與完整嘗試序列 |
| `tickets(order_id, sequence)`、`tickets(code_hash)` | 每件商品一張票，憑證可快速查找 |
| `ticket_entitlements(ticket_id, venue_id)`、`redemptions(ticket_entitlement_id)` | 場館權益只成功消耗一次 |
| `refunds(order_id)`、`refunds(idempotency_key)` | 全額退款只處理一次 |

金額為整數新臺幣分。時間欄位按 UTC 寫入，UI 按 Asia/Taipei 呈現。訂單明細保存商品名稱、單價、數量、供應商、效期及場館 ID／名稱快照。QR 代碼以 AES 加密保存，查找使用 SHA-256 雜湊。稽核提供操作者、關聯訂單、追蹤編號及時間，營運介面只有查閱功能。
