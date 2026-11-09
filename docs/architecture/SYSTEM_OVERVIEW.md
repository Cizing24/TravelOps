# TravelOps 系統總覽

## 模組

| 模組 | 職責 |
|---|---|
| Catalog | 供應商、場館、票種、權益模板、上架及可售庫存 |
| Checkout | 授權、訂單快照、數量與總價、15 分鐘庫存預留、取消及逾期 |
| Payment | 驗證 HMAC、核對付款參考與金額、紀錄與去重、成立開券事件 |
| Fulfillment | MySQL Outbox、Redis 補送、Mock Supplier、嘗試歷史與發券 |
| Redemption | 場館授權、憑證雜湊查找、即時效期判斷、權益核銷與稽核 |
| Refund | 申請、營運審核、模擬全額退款、撤銷及庫存回補 |
| Operations | Filament 讀取真實 DB，所有寫入經 Application action |

HTTP 層處理 Laravel session、CSRF、驗證、速率限制與回應格式。Policies 處理訂單及票券所有權。營運的查閱與變更分別使用 `operate` 與 `administer`，場館核銷以 `redeem` 及場館關聯限制。付費、開券、核銷和退款的事實存於 MySQL。

## 權限矩陣

| 能力 | 旅客 | 場館人員 | 營運 | 管理員 |
|---|---|---|---|---|
| 下單、查訂單與票券、申請退款 | 僅本人 | | | |
| 核銷、查核銷紀錄 | | 僅授權場館 | | |
| 查訂單、交易與稽核、重新排程、退款審核 | | | ✓ | ✓ |
| 管理商品、權益、供應商、場館、可售庫存與角色 | | | | ✓ |
| 營運票券與庫存預留清單 | | | | ✓ |

## 狀態轉移

- 訂單由 `pending_payment` 到 `paid`，開券有失敗時進入 `fulfilling`，成功後 `fulfilled`。未付款可以 `cancelled` 或 `expired`，已收款且無任何核銷才可 `refunded`。
- 模擬付款失敗只更新該筆付款為 `failed`，訂單在期限內仍可收到成功回呼。已捕獲付款不會因後到的失敗回呼倒退。
- 庫存建立預留時從可售量扣除。預留 `held` 可變為 `sold` 或 `released`，售出且成功退款後改為 `returned`。
- 開券由 `pending` 到 `succeeded`、`retryable_failed` 或 `permanently_failed`，退款前若仍未開券則事件取消。嘗試次數及最近一次錯誤均可追蹤。
- 票券發行後為 `active`。第一次成功核銷設定 UTC 到期時間。查詢及每次核銷都即時計算期限。退款後變為 `revoked`。每個場館權益個別轉為 `redeemed`，到期後只顯示剩餘權益已失效。
- 退款由 `requested` 到 `processed` 或 `rejected`。核准與本地模擬退款同交易完成，因此沒有獨立待撥款狀態。已使用權益需人工處理，畫面會標示拒絕原因。

更多設計見 [交易一致性](../decisions/002-transaction-consistency.md) 與 [開券可靠性](../decisions/003-outbox.md)。
