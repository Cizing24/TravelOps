# TravelOps HTTP 合約

Web 使用 Laravel session。成功登入會更新 session ID。表單 POST 使用 CSRF，JSON 呼叫需同時提供 session cookie、`X-CSRF-TOKEN` 及 `Accept: application/json`。Mock 回呼使用自己的 HMAC 驗證。

| 路由 | 角色與行為 |
|---|---|
| `GET /products`、`GET /products/{slug}` | 公開。只顯示上架商品 |
| `POST /login`、`POST /logout` | Email／密碼登入及清除 session |
| `POST /orders` | 旅客。傳 `product_id`、1–10 的 `quantity`、UUID `idempotency_key` |
| `GET /my/orders`、`GET /my/orders/{reference}` | 旅客。只限本人 |
| `POST /my/orders/{reference}/simulate` | 本地 Demo 訂單本人。選 `success`、`failure`、`delayed`、`duplicate` 或 `out_of_order` |
| `POST /my/orders/{reference}/cancel` | 本人取消未付款訂單。已取消或過期時重送沒有第二次影響 |
| `POST /my/orders/{reference}/refund` | 本人申請整筆退款。同一訂單回到既有退款 |
| `GET /my/tickets`、`GET /my/tickets/{id}` | 只限票券所有者，QR 詳情回應要求不快取 |
| `GET /merchant/redemptions` | 場館人員。清單僅顯示授權場館 |
| `POST /merchant/redemptions` | 授權場館人員。傳 `venue_id` 與 48 字元小寫十六進位 `code` |
| `POST /webhooks/mock` | 本地 mock。簽章驗證及事件去重 |
| `GET /ops`、`/ops/{resource}` | 營運或管理員依資源授權。編輯透過 Filament Livewire actions |
| `GET /up` | 本地就緒檢查，包含 MySQL 與 Redis 連線 |

## 下單與核銷

```json
{"product_id": 4, "quantity": 2, "idempotency_key": "230ea63e-54ea-4123-b1c6-68d0a2f263b4"}
```

```json
{"reference": "9ee32e28-2f58-4d50-bd6d-3537ffb93f18", "status": "pending_payment"}
```

新單為 201，同 key 同內容重送為 200，key 改用不同商品或數量為 409。HTML 表單成功後重新導向詳情。核銷 JSON 成功為 201 並回傳 `trace_id` 與 `redeemed_at`。同一權益重試為 409，可從場館清單追溯前次成功。

## Webhook

```json
{"event_id":"8d898a13-b9c9-4a29-871b-a3b7619da1e9","order":"9ee32e28-2f58-4d50-bd6d-3537ffb93f18","payment":"01328f14-81a0-42b7-9e7d-5098cc7d90ba","status":"succeeded","amount":176000,"currency":"TWD"}
```

以 `X-TravelOps-Signature` 傳入原始 body 的 SHA-256 HMAC 十六進位字串，使用本地 `TRAVELOPS_WEBHOOK_SECRET`。回應例如 `{"outcome":"paid"}`。失敗事件及已關閉訂單的驗證通過回呼亦會保留 outcome。簽章不符為 401，同一事件 ID 內容衝突為 409，金額或付款參考不符為 422。停止 Demo 時回傳 404。

常見狀態為 401（JSON 未登入／無效回呼簽章）、403（角色、場館或所有權限制）、404（找不到資源）、409（庫存不足、已核銷、到期或撤銷）、419（CSRF）、422（驗證錯誤）及 429（速率限制）。業務拒絕 JSON 使用 `message` 與 `code`，Laravel 欄位驗證另有 `errors`。
