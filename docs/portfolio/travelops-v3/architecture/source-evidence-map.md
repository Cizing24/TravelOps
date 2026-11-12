# 架構來源與語意核對（私有工程證據）

查核日期：2026-11-12。TravelOps 來源：`11c0e7ae5fa828a69438f807be220c4f37be41c9`。此檔供私有 Repository 審查，不搬入公開網站；公開圖只保留一般技術名稱。

| 節點／箭頭／行為 | 實際來源 | 核對重點 |
| --- | --- | --- |
| 旅客、POS、場館、營運 → Laravel | `routes/web.php`、`app/Http/Controllers/`、`app/Providers/OperationsPanelProvider.php` | Blade／Livewire／Filament 同屬 Laravel；不是四個微服務。 |
| 授權邊界 → Application | `app/Policies/`、`app/Providers/AppServiceProvider.php`、`app/Application/Checkout.php::place`、`Redeem.php::ticket` | 訂單持有人、角色、門市 membership、指派場館。 |
| Checkout → 共用庫存 | `app/Application/Checkout.php::place`、`PointOfSale.php::sell`、`tests/Integration/MySqlConcurrencyTest.php` | actor、商品及餘額列鎖、交易內預留及商品權益快照；POS 共用同一個 place。 |
| 付款 → Inbox + Outbox | `app/Application/PaymentWebhook.php::receive` | HMAC、大小及 payload 驗證；訂單／付款鎖、event 唯一性、body hash；付款狀態和 issuance 同交易提交。 |
| MySQL → Scheduler → Redis | `routes/console.php`、`app/Application/Issuance.php::relay`、`RefundOrder.php::relay`、`NotificationCenter.php::relay` | 圖上 DB → Scheduler 代表排程讀取到期持久列，不是 DB 主動推送。主要 relay 每 10 秒。 |
| Redis → Worker | `app/Jobs/IssueTickets.php`、`ProcessRefund.php`、`DeliverNotification.php`、`compose.yaml` | Redis 工作可從 MySQL 工作列重建；queue worker 與 scheduler 為不同程序。 |
| Worker ↔ MySQL | `app/Application/Issuance.php::process` | 短交易先保存 lease 與 trace，釋放鎖後 queryIssuance／issue，再以新交易鎖定並驗證 lease。 |
| Worker → Local Mock | `app/Infrastructure/MockSupplier.php`、`MockRefundGateway.php`、`MockGateway.php`、`MockNotificationChannel.php` | 都是本地合成 adapter，沒有真實第三方網路金流；Mock ledger 和業務紀錄分開保存但同在 MySQL。 |
| Mock → Receivers／查詢 → Application | `app/Application/SupplierCallback.php`、`RefundCallback.php`、`Issuance.php::confirmCallback`、`RefundOrder.php::confirm` | 簽章、事件內容、狀態及冪等驗證；供應商／退款具有時間窗。付款 receive 沒有相同時間窗，避免過度概括。 |
| 回呼可見範圍 | `routes/web.php`、`app/Jobs/DeliverMockSupplierCallback.php`、`DeliverMockRefundCallback.php` | 供應商／退款 HTTP 測試路由限 testing，本地排程工作直接呼叫相同 receiver；圖不暗示公開第三方入口。 |
| Outbox → 票券權益 | `app/Application/Issuance.php::recordResult`、`tests/Integration/SupplierIssuanceTest.php`、`RedisOutboxTest.php` | 重送、逾時查詢、同步或延遲回呼，只在有效狀態建立票券。 |
| 退款與核銷共用 order lock | `app/Application/RefundOrder.php`、`Redeem.php::ticket`、`tests/Feature/RefundProcessingTest.php`、`tests/Integration/MySqlConcurrencyTest.php` | 核准且未確定退款阻擋核銷；確認後撤銷權益與回補庫存，再排程供應商取消。 |
| 內外帳冊 → 對帳／報表 | `app/Application/Reconcile.php`、`OperationsReport.php`、`app/Filament/Pages/Reconciliation.php`、`Reports.php` | 獨立保存 evidence／處置，沒有改寫原交易金額。UI 元／CSV 分與臺灣營業日。 |
| 本地部署邊界 | `compose.yaml`、`docker/Dockerfile`、`.github/workflows/ci.yml` | PHP 8.4、MySQL 8.4、Redis 7.4，App／Queue／Scheduler；GitHub Actions 是驗證工具，不畫成正式線上依賴。 |

## 兩種總覽來源的一致性

`travelops-system-overview.html` 是展示版的主來源，由官方 template 和 `build.py` 自包含輸出；兩張 PNG 均由官方 `render_png.py` 實際渲染。12 個節點、四角色入口、Application → MySQL、DB 掃描 → Scheduler → Redis → Worker、Worker → Mock → Receiver → Application、Worker 確認 MySQL，與 `.mmd` 的語意相同。HTML 的簡短標籤由頁尾及無障礙文字補充 Inbox、refund、audit 和帳冊。

`travelops-system-overview.svg` 和 `travelops-transaction-flow.svg` 分別由對應 Mermaid 來源匯出；不是 HTML 的 SVG 匯出。交易流程是兩條通路共用庫存、付款及開券工作、鎖外 adapter、結果確認與退款／核銷競爭的展開圖，不表示所有箭頭都是一次同步 HTTP 請求。

## Skill 與 Logo 審查

- 採用 `caru-ini/stack-diagram` 的 `5af3b095fff7109c905c5284dc510dc06588bade`，來源：[官方 Skill](https://github.com/caru-ini/stack-diagram/blob/5af3b095fff7109c905c5284dc510dc06588bade/skills/stack-diagram/SKILL.md)。本機固定在主工作樹 `.runtime/tools/stack-diagram/skills/stack-diagram/`。
- 已讀取 `fetch_icons.py`、`build.py`、`render_png.py` 與 `share.py`。下載只傳公開技術名稱；build 在本機內嵌圖示和字型，render 只開本機 HTML。`share.py` 有公開 Gallery 上傳與本機 delete key 寫入能力，本輪未執行、未分享。
- 官方來源品牌圖示經 svgl 下載：Laravel、MySQL、Redis、Docker。抽象角色、Worker、Scheduler、receiver、Mock 與授權使用 Lucide。完整 URL／檔案大小保存在 `logo-sources.json`。沒有將 Laravel 或資料庫圖示改色；Lucide 的 currentColor 在白底 tile 固定為可讀深色。
- upstream 程式與文件採 MIT；嵌入 Geist／JetBrains Mono 採 SIL OFL 1.1。授權文字保存在 `licenses/`。品牌標誌仍屬其原持有人；採用 Skill 不等於取得商標背書或額外公開授權。
- Mermaid CLI 固定 `11.12.0`，安裝在 ignored 工具路徑且使用 `--ignore-scripts`。既有 Chrome 執行匯出；沒有新增產品執行期依賴。其工具依賴提示 Puppeteer 23 已過支援期，只在本機處理可信圖表，不納入產品執行期。

## 圖像替代文字

**總覽繁中：** 旅客、門市、場館及營運經 Laravel 授權後操作 MySQL；排程讀取持久工作，送入 Redis，由 Worker 呼叫本地 Mock，再經驗證回呼或查詢確認結果。資料全為合成。

**Overview English:** Customers, POS, venues and operations enter authorized Laravel interfaces backed by MySQL. A scheduler dispatches durable work through Redis; workers call local Mock adapters and confirm results through verified receivers or queries. All data is synthetic.

**流程繁中：** 線上與 POS 共用鎖定庫存；付款確認與 Outbox 同交易提交；Worker 先取得租約，在訂單鎖外查詢及呼叫 Mock，再以新交易確認結果。退款核准與核銷共用訂單鎖，確認退款才回補庫存與撤銷權益。

**Flow English:** Online checkout and POS lock shared stock. Payment confirmation commits its Outbox atomically. Workers claim a lease, query and call Mock adapters outside order locks, then confirm in a new transaction. Refund approval and redemption share the order lock; confirmed settlement revokes entitlements and releases stock.
