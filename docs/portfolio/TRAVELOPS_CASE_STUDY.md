# TravelOps V2 工程案例

TravelOps 是獨立作品，以合成交易展示旅遊票券從購買、履約到營運查核的流程。旅客在線上購買票券，POS 店員在授權門市售票，場館人員核銷自己的場館權益，營運人員追查開券、退款、庫存與帳冊差異。Mock 供應商提供同步確認或非同步回呼。

V3 把三種使用情境分開設計。旅客依場館與效期選票，並沿訂單進度找到票券。店員先核對銷售摘要，確認後依同一筆交易的明細查閱開券。營運人員在 Filament 從待處理的開券異常進入紀錄，並排檢視內外部帳冊。結帳預估僅用於操作回饋，伺服器仍負責計價與鎖定共用庫存。各角色的 UX 證據見 [V3 驗收](../reports/V3_UX_DOCKER_VALIDATION.md)。

Laravel 模組化單體用 Blade、Livewire 與 Filament 提供操作介面。MySQL 儲存訂單、付款、庫存、票券權益和 Outbox。Redis Queue 執行開券、退款與通知工作，Scheduler 掃描待處理工作以恢復派送。[V2 系統總覽](../architecture/V2_SYSTEM_OVERVIEW.md) · [可靠性 ADR](../decisions/004-v2-operation-reliability.md)

## 五個工程決策

| 問題 | 設計決策與取捨 | 程式與測試證據 |
| --- | --- | --- |
| 線上與門市同時搶最後一張票 | 共用商品與庫存餘額的列鎖，預留、狀態及流水在交易內更新。熱門商品的請求會在同一庫存列排隊。 | [Checkout](../../app/Application/Checkout.php)、[PointOfSale](../../app/Application/PointOfSale.php)、[獨立連線競爭測試](../../tests/Integration/MySqlConcurrencyTest.php) |
| 付款回呼重送或亂序 | 驗證簽章、付款識別與金額，使用持久化事件與訂單鎖限制狀態推進。保留回呼處理紀錄供追查。 | [PaymentWebhook](../../app/Application/PaymentWebhook.php)、[付款與開券測試](../../tests/Feature/FulfillmentTest.php) |
| 供應商已接單但本地未收到回應 | Outbox 先記錄固定請求識別與處理租約，外部呼叫離開資料庫鎖，遺失回應時可先查詢。短暫不一致由補查、重試及營運處理收斂。 | [Issuance](../../app/Application/Issuance.php)、[SupplierCallback](../../app/Application/SupplierCallback.php)、[供應商整合測試](../../tests/Integration/SupplierIssuanceTest.php) |
| 已核銷票券與退款審核競爭 | 退款審核與場館核銷共用訂單鎖。已核准且未確認的退款暫停核銷，確認後才撤銷權益及回補庫存。V2 只處理整筆退款。 | [RefundOrder](../../app/Application/RefundOrder.php)、[Redeem](../../app/Application/Redeem.php)、[退款測試](../../tests/Feature/RefundProcessingTest.php)、[競爭測試](../../tests/Integration/MySqlConcurrencyTest.php) |
| 帳冊差異與報表的金額口徑 | 以臺灣營業日比對本地交易及獨立 Mock 帳冊，差異處理另存稽核紀錄。報表與匯出共用查詢，CSV 保留整數分並處理可能被試算表當成公式的文字。歷史可對帳範圍取決於帳冊開始累積的時間。 | [Reconcile](../../app/Application/Reconcile.php)、[OperationsReport](../../app/Application/OperationsReport.php)、[對帳測試](../../tests/Feature/ReconciliationTest.php)、[報表測試](../../tests/Feature/OperationsReportTest.php) |

## 展示與證據

- `/products` 買票、`/pos` 確認門市售票、`/ops/inventory-movements` 對照兩個通路。履約或退款情境見 [V2 展示指南](../demo/V2_DEMO_GUIDE.md)。
- 2026-11-09 的 [V2 交付報告](../reports/V2_DELIVERY_REPORT.md) 記錄 Linux Compose 34 項測試、411 個斷言及 Chrome 旅程。PR #1 的 [後續 CI](https://github.com/Cizing24/TravelOps/actions/runs/42619935592) 對應交接提交。當次 Windows 結果及本輪驗證條件分見 [V2 交付報告](../reports/V2_DELIVERY_REPORT.md) 與 [收尾驗收](../reports/V2_POLISH_VALIDATION.md)。
- 付款、退款、供應商與外部通知使用本地 Mock。可用文字碼核銷或貼入 QR 掃描結果。正式串接需另驗證外部合約、實機掃碼與尖峰容量。
- 此稿保存在私有 TravelOps。對外候選內容為經擁有者確認的摘要與通用架構說明。原始碼、內部文件、私有連結和實際畫面需逐項審核。公開素材須排除帳密、票券憑證及完整交易識別碼。

## 面試摘要

TravelOps 是獨立開發的旅遊電子票券履約與營運平台。旅客與門市共用庫存，付款後由背景工作開券，場館按權益核銷。系統以資料庫交易、冪等鍵和 Outbox 處理庫存競爭、回呼重送及退款互斥，並用合成帳冊展示對帳和報表。整合測試驗證最後一張票與退款審核的併發行為。

## 2–3 分鐘口述提綱

1. **30 秒**｜用旅客與 POS 同時賣同一種票說明角色和共用庫存的問題。
2. **45 秒**｜指出 MySQL 的商品與庫存鎖，接著展示兩個通路留下的庫存流水。
3. **45 秒**｜用一次重複付款與非同步供應商回呼，說明固定識別與 Outbox 如何讓重試可追查。
4. **30 秒**｜用退款與核銷競爭測試收尾，交代 Mock、合成資料與正式整合的驗證邊界。
