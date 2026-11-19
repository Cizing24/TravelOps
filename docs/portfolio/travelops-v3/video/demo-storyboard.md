# Demo 分鏡與字幕

最終片長 **74.36 秒**，1280×720、25 fps、H.264/yuv420p、無音訊。2026-11-12 以隔離 Chrome 錄製真正的 V3 操作；登入在錄製前完成。影片不是由截圖製造操作。開頭及結尾為資訊字卡，中間為三段實際 Playwright 錄影。

| 時間 | 真實操作／路由 | 繁中資訊 | English subtitle |
| --- | --- | --- | --- |
| 00:00–00:05 | 標題字卡 | 獨立作品展示；合成資料與 Mock 整合。 | Independent portfolio demo; synthetic data and Mock integrations. |
| 00:05–00:14 | `/products` → `/products/coast-four` | 查看商品、場館、效期與預估。 | Browse tickets, venues, validity and estimated totals. |
| 00:14–00:28.48 | 建立訂單 → `/my/orders/{redacted}`，模擬重複成功回呼 | 追蹤付款與開券，等待片段已剪短。 | Place an order, simulate duplicate callbacks and follow issuance. |
| 00:28.48–00:33.48 | `/my/tickets/{id}` | 查看場館權益；QR／代碼遮蔽。 | Inspect venue entitlements; credentials are redacted. |
| 00:33.48–00:42.48 | `/pos` → `/pos/{store}` | 商品、張數、付款方式與即時摘要。 | Change POS quantity and payment method; check the live summary. |
| 00:42.48–00:49.16 | 提交真實合成銷售 → `/pos/{store}/sales/{redacted}` | 兩張 NT$ 640；後端計價與庫存。 | Confirm two tickets at NT$640; server pricing and shared stock. |
| 00:49.16–00:54.16 | `/ops/outbox-events` | 追蹤開券工作與異常。 | Operations traces issuance work and failures. |
| 00:54.16–00:64.36 | `/ops/reconciliation`，執行對帳、捲動查看差異 | 內外合成帳冊與處理入口。 | Compare internal and Mock ledgers and disposition controls. |
| 00:64.36–00:69.36 | `/ops/reports` | 篩選介面與 KPI；非實際業績。 | Operational reporting controls and synthetic results. |
| 00:69.36–00:74.36 | 收尾字卡 | Laravel／MySQL／Redis／Outbox；未正式部署。 | Shared stock, Outbox and refund coordination; no production deployment. |

字幕以獨立的英語與繁中 SRT 交付，時間碼完全相同；繁中另燒入 MP4。影片右上角全程顯示 Synthetic data / Mock integrations。英文 SRT 用於未來字幕轉換，瀏覽器 track 需轉為 WebVTT。

## 剪接與遮蔽

原始旅客 take 40.48 秒、POS 15.68 秒、營運 20.20 秒。旅客原片 18–30 秒為重複等待畫面，本次剪除 12 秒，切點標示「等待片段已剪短 / Wait shortened」，沒有加速或假造狀態。各 5 秒字卡後，總長 74.36 秒。公開 MP4、海報均由實際影格產出。

完整 UUID、QR、48 字元核銷憑證及帳號在捕捉時遮蔽，輸出的像素不可還原文字或 QR；狀態、價格、場館和庫存保留真實頁面結果。登入密碼不在錄製 context，瀏覽器網址列和開發工具不在錄製範圍。

私有原片留在 ignored `.runtime/media/{traveller,pos,operations}.webm`，不提交。重現指令：`python scripts/portfolio/build-video.py --traveller-cut 18 30`。這些切點只適用本次 take，重錄需重新審查時間。詳見 `scripts/portfolio/README.md`。
