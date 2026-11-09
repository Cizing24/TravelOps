# TravelOps MVP｜Codex 一次性自主開發任務書

> **文件版本：** 1.0  
> **使用方式：** 在準備用來建立 TravelOps 的專案資料夾啟動 Codex，請其完整閱讀本文件，依規格自主完成實作、整合測試、本地驗收及成果報告。  
> **任務性質：** 單次完整交付，不是僅做計畫、樣板或 PoC。  
> **主要語言：** 與使用者溝通及驗收報告使用繁體中文（臺灣用語）；程式識別字、commit message 及供 Agent 閱讀的工程文件可優先使用英文。  
> **授權範圍：** 可自主查閱、選用既有或推薦的 Skills；允許在專案範圍內安裝相依套件、建置、測試、修正與提交本地 Git commits。不得擅自部署正式環境、購買服務、公開私有資訊、推送遠端或建立公開 Repository，除非當前工作階段另有明確授權。

---

## 0. 給 Codex 的最高優先級指令

你是本專案的 **Lead Backend Engineer / Solution Architect / QA Engineer / DevOps Engineer**。目標是**在同一次工作執行中完成可運行、可測試、可展示的 TravelOps MVP**，最後才向使用者提供完整成果報告。不要僅生成 TODO、規格或 scaffold 就結束。

1. **高度自主。** 自行做出一般工程決策，不要對每個套件、頁面、資料欄位或檔案結構反覆詢問。只有不可逆、付費、公開發布、憑證、正式服務或重大需求變更才需要停下確認。
2. **完整執行。** 依序完成環境檢查、專案建立、模組實作、測試、修復、文件與本地整合驗收。可調整順序以提高效率，**不可跳過必要驗收**。
3. **以 MVP 邊界控制成本。** 不實作完整旅行社 ERP、真實金流、POS、加盟商總帳、訂房引擎、LINE 正式串接、微服務或 Kubernetes。
4. **先求正確性，再求外觀。** 優先確保交易、付款、開券、聯票、核銷、庫存與權限的資料一致性；完成後才做清楚、專業且響應式的介面。
5. **避免虛報。** 無法執行的測試、缺少的環境、未完成的功能均清楚標註。不可用「預期成功」充當測試通過。
6. **避免重複驗證。** 開發過程用針對性測試，整合完成後再集中執行完整測試和最終驗收；每次必要修復後只重跑相關測試及最終完整測試。
7. **成果報告最後交付。** 建立 `docs/reports/MVP_DELIVERY_REPORT.md`，並在最終訊息附上摘要、測試狀態、重要檔案位置與剩餘限制。
8. **現有程式保護。** 先檢查目前目錄；若已存在程式庫，確認其用途並保留原有內容，禁止任意刪除、重寫不相干檔案、執行破壞性清理。
9. **Git 規範。** 可自行建立 feature branch 與本地 commits；絕不將 `.env`、資料庫 dump、金鑰、私人資料或服務憑證納入版控。沒有遠端發布授權時，停在本地完成狀態。

**Definition of Done：** 本地可依 README 啟動；可用 Demo 帳號完成購買 → 模擬付款 → 開券 → 多場館核銷 → 異常處理；測試能證明重複回呼、競爭購買、並行核銷不會破壞資料；營運後台與角色權限可用；文件及報告完整。

---

## 1. 專案背景與定位

**專案名稱：** TravelOps — Travel Ticket Fulfillment & Operations Platform  
**類型：** 獨立原創的旅行社電子票券履約與營運管理系統（求職挑戰作品）  
**參考業務：** 旅遊商品、電子票券、聯票、合作場館、核銷、訂單及營運後台。  
**界線：** 以公開可觀察的旅行社業務為情境，不宣稱為「太金國際旅行社有限公司」的官方系統、內部原始碼或已確認的公司痛點；不可未經授權使用其 Logo、正式品牌識別或真實會員資料。

**目標使用者：**
- Customer：檢視票券商品、建立訂單、進行模擬付款、查看票券與使用進度。
- Operator：檢視訂單、處理付款／開券異常與退款申請、查看稽核紀錄。
- Merchant Staff：僅能核銷自己被授權場館的票券權益。
- Admin：維護產品、場館、供應商、角色、營運資料及異常事件。

**MVP 主要價值：** 可被實際演示的完整交易生命週期與可靠性工程；不是只有商品 CRUD。

---

## 2. 技術棧與工程原則

| 層級 | 首選 | 原則 |
|---|---|---|
| Server | PHP 8.4 + Laravel 13 | 執行前查閱官方相容要求；若當時穩定版或套件有衝突，改用受支援的穩定組合並在 ADR 記錄理由 |
| UI | Blade + Livewire + Tailwind CSS | 旅客端採 Server-driven UI，避免為 MVP 額外維護 SPA |
| Admin | Filament 5 | 先核對相容性；若不適用改用受支援版本並清楚記錄 |
| DB | MySQL 8.4 LTS | 交易事實唯一權威資料來源；必須在 MySQL 上驗證並行行為 |
| Cache / Queue | Redis | 不當作付款／核銷唯一資料來源 |
| API Auth | Laravel session + Sanctum（如需 API） | Web 用 cookie/session；API 才使用 token |
| Jobs | Laravel Queue / Scheduler | 重試次數受控；重要事件有可追蹤紀錄 |
| Tests | Pest 或 PHPUnit | Unit、Feature、Integration、真實 MySQL 併發測試 |
| DevOps | Docker Compose + GitHub Actions | 本地一鍵啟動，CI 驗證格式、靜態分析及測試 |

**禁止不必要的架構膨脹：** 不上微服務、事件串流平台、Kubernetes 或多資料庫；優先採 **Modular Monolith**。  
**程式品質：** thin controllers、application services/actions、明確的業務狀態機、Policies、DTO/Request Validation、可替換的外部 provider adapters、資料庫約束。  
**安全原則：** 最小權限、CSRF / XSS / SQL injection 防護、password hashing、rate limiting、敏感欄位遮罩與秘密不入庫。

## 3. 可使用 Skills 的授權與選用策略

使用者**明確允許使用已安裝或推薦的 Skills**，你可以：

1. 先檢視工作環境中可用的 Skills、`AGENTS.md` 和相關工具，按實際需求選擇，不要假設某個 Skill 必定存在。
2. 可優先考慮 **impeccable、taste-skill、ui-ux-pro-max-skill、review-ui-design-skill** 來強化後台與旅客 UI；可考慮 **agent-skills** 中的 Laravel / PHP / Security / Testing 類工作流程，但只有在確實適用時使用。
3. 也可使用其他已安裝或可信來源推薦的架構、PHP、Laravel、DB、測試、Docker、CI、Accessibility 等 Skills。
4. 安裝新 Skill 前檢查來源、授權、內容與腳本行為；不得執行不可信任的一鍵安裝命令或要求擴大系統權限。若不安全、不必要或不可取得，跳過並記錄原因。
5. Skills **只提升實作品質，不凌駕本任務規格**；不得因 Skill 建議而偏離 MVP 範圍或增加不必要框架。
6. 在最終成果報告中列出：已使用的 Skills、用途、是否新安裝；若無法使用，也應簡短說明。

## 4. MVP 範圍與業務需求

### M1. 商品、場館及供應商
- 建立商品、供應商、場館與聯票權益模板。
- 支援**單館票**與**多場館聯票**兩種商品。
- 設定票券使用規則：首次核銷起算 N 天、固定到期日或其他合理擴充點（MVP 至少落實前兩種中的一種並完整測試；優先首次核銷 N 天）。
- 商品停售、價格版本或訂單快照：下單後的價格與權益內容不得因管理員修改商品而偷偷改變。
- 商品圖示與描述可使用原創示意素材；禁止使用未經授權的公司識別。

### M2. 訂單、預留庫存與模擬付款
- 顧客可瀏覽商品、選擇數量、建立訂單並看見總價。
- 幣別 TWD，金額以整數最小貨幣單位或適當 decimal 保存，禁止以 float 運算金錢。
- 下單時採可追蹤的庫存預留；付款期限屆滿則釋放；已付款不得被過期工作錯誤取消。
- 模擬支付服務可切換成功、失敗、延遲、重複 Webhook、回呼亂序等情境。
- Webhook 驗證以 Mock Provider 的簽章／共享秘密或可驗證 token 示意；不允許無驗證地改變付款狀態。
- 建立 Idempotency Key、唯一索引及 webhook event log；同一事件重送不會再扣庫存或重複發券。
- 支援使用者取消尚未付款訂單；已付款退款採受控管理流程（見 M5）。

### M3. 票券發行、聯票與 QR Code
- 付款成功後非同步開券；每個 ticket 有唯一不可猜測識別碼、狀態及對應的 ticket_entitlements。
- 聯票每個場館權益獨立核銷，同一權益最多成功一次。
- QR Code 不可直接暴露 PII、連續資料庫 ID 或管理憑證；採高熵 token／簽章憑證，服務端仍須做權限與狀態檢查。
- 旅客端可查看票券狀態、權益使用進度、有效期限及核銷紀錄。
- 首次成功核銷時啟動有效期；使用 `Asia/Taipei` 顯示，資料庫時間盡量存 UTC 並測試時區邊界。
- 過期判定須在每次核銷時即時檢查；Scheduler 可負責投影更新／提醒，但不能只依賴定時作業保證過期判斷。

### M4. 核銷引擎
- 商家人員登入後，僅可核銷已授權場館的對應權益。
- 核銷入口可輸入或掃描核銷碼；掃碼功能可用適當開源套件，若硬體攝影機環境不可用，必須提供文字代碼 Demo 流程。
- 驗證票券已發行、已付款、未取消／退款、期限有效、權益適用該場館、尚未核銷。
- **並行核銷**：必須以 InnoDB transaction、鎖定及唯一約束使兩次並發請求最多只有一次有效成功；不可單靠前端 disable 按鈕。
- 提供明確的失敗原因（已核銷、過期、無權限、無效代碼、撤銷等），不洩漏其他顧客個資。
- 核銷成功寫入不可任意覆寫的 redemption/audit record。

### M5. 異常處理與退款
- 針對「付款成功、供應商開券失敗」建立異常事件與人工可查看的處理狀態。
- 以 provider adapters 模擬成功、逾時、暫時失敗、永久失敗；對可安全重試的操作設最大重試次數、退避策略與冪等防護。
- 關鍵事件應使用 **Transactional Outbox 或等效的可靠補償設計**，避免 DB commit 成功、queue dispatch 失敗造成永久漏單。
- 後台可查看失敗原因、請求追蹤 ID、已重試次數、最後錯誤及可執行的受控補償動作。
- MVP 支援**整筆未使用票券退款**的模擬流程；已部分使用聯票採明確不可自動退款規則，需人工處理／拒絕，不做複雜的部分退款計算。
- 退款與撤銷票券、庫存狀態更新要有一致性及 audit log；重複退款請求不可重複退款。

### M6. Filament 營運後台
- Admin：商品、供應商、場館、權益、訂單、票券、庫存預留及角色管理。
- Operator：訂單搜尋、付款／發券異常追蹤、退款審核與處理、核銷紀錄查詢。
- Dashboard：基本營運指標（訂單數、已付款訂單、開券成功、待處理異常、核銷數），使用 seed 與真實 DB 查詢結果。
- 查詢功能應有索引、分頁、必要 filter，不建立只是漂亮而無功能的假資料卡片。
- 未獲授權的使用者不得存取後台或其他供應商／場館資料。

### M7. 旅客端與展示體驗
- 原創 TravelOps 品牌風格、首頁商品列表、商品詳情、購物／付款模擬、我的訂單、我的票券。
- 行動裝置可閱讀，狀態有清楚標示、空狀態與錯誤回饋；必要表單有標籤與驗證訊息。
- 提供一組安全的 Demo 資料：數個單館票、至少一個四場館聯票、成功／失敗／退款／異常情境。
- Demo 模式需避免使用真實付款、真實供應商或真實個資。

### 明確不在 MVP 內
- 真實支付金流、真實票券供應商 API、LINE Messaging API 正式串接。
- 商業級 POS、跨門市庫存調撥、加盟商分帳、完整會計、電子發票、複雜報表 BI。
- 真正第三方飯店訂房／機票訂位、旅遊套裝行程搜尋與供應商即時定價。
- Kubernetes、微服務、跨區高可用、實際商業級 SOC／法遵認證。
- 公開正式部署、真實付款或實際合作商家營運資料。

---

## 5. 領域模型、表結構與約束

請根據以下邏輯完成 ERD、migrations、indices、foreign keys、模型關聯與 factories。可合理增減技術表，但不得省略核心約束。

**主要實體：**

- `users`、角色／權限（可用經驗成熟的權限套件）、`merchant_users` 或同等場館授權模型。
- `suppliers`, `venues`, `products`, `product_entitlements`（場館使用權益模板與效期規則）。
- `orders`, `order_items`（單價與規則快照）, `payments`（provider event IDs / idempotency）, `webhook_events`。
- `tickets`, `ticket_entitlements`, `redemptions`。
- `inventory_balances`, `inventory_reservations`（若採對等替代設計需在 ADR 說明不超賣機制）。
- `refunds`, `integration_attempts`, `outbox_events`, `audit_logs`。

**必備約束：**
1. `webhook_events`: `(provider, external_event_id)` 唯一。
2. `payments`/退款：對業務 idempotency key 做唯一約束或等效防重設計。
3. `redemptions`: 單次使用的 `ticket_entitlement_id` 僅能建立一筆成功核銷；可用唯一索引及設計適當的嘗試紀錄表。
4. 核銷、庫存預留、付款狀態轉移均需 DB transactions；鎖定順序一致以降低 deadlock。
5. 不以 Redis 鎖取代 DB 唯一約束，也不以「HTTP 回應成功」代替持久化交易事實。
6. 使用實際 MySQL 驗證 unique key、locking、rollback、並行交易，而不只 SQLite。
7. 重要異動需有 actor、timestamp、trace / correlation ID；稽核資料不得由一般使用者任意更改或刪除。

### 建議狀態機（可在 ADR 說明細化）

| Aggregate | 基本狀態 | 重要規則 |
|---|---|---|
| Order | `pending_payment` → `paid` → `fulfilling` → `fulfilled` | 取消限未付款；已付款使用退款流程 |
| Payment | `initiated`, `succeeded`, `failed`, `refunded` | 已確認成功不因晚到失敗回呼倒退 |
| Ticket | `pending`, `active`, `expired`, `revoked` | 不可由任意 API 直接跳狀態 |
| Entitlement | `unused`, `redeemed`, `expired`, `revoked` | `redeemed` 單向不可重複成功 |
| Issuance | `pending`, `processing`, `succeeded`, `retryable_failed`, `permanently_failed` | 重試有上限且冪等 |
| Refund | `requested`, `approved`, `rejected`, `processed`, `failed` | 需要權限、審計、去重 |

不強制每一狀態都存在為單獨欄位；可用 domain-specific entities 管理，但必須寫明允許轉換、拒絕非法轉換並用測試覆蓋。

---

## 6. 必需的實際演示流程（E2E Acceptance Journeys）

### Journey A：正常付款與開券
1. Customer 登入，購買單館票或聯票。
2. 預留庫存，建立 pending order。
3. Mock payment gateway 回傳成功 webhook。
4. 系統驗證／去重並持久化付款成功，可靠交付開券事件。
5. Worker 發券成功，顧客看到 QR 憑證與權益清單。
6. Operator 在後台查看完整訂單、付款與開券紀錄。

### Journey B：聯票分段核銷
1. 顧客取得四場館聯票。
2. Merchant A 成功核銷場館 A，首次成功核銷時間寫入。
3. 依商品規則開始計算 14 天期限；其他三場館仍可用。
4. Merchant B 可核銷 B；Merchant A 不可操作 B 的權益。
5. A 再次核銷 → 明確失敗且沒有第二筆成功核銷。
6. 時間推進到有效期後 → 尚未使用權益不能核銷。

### Journey C：付款重送、失敗及重試
1. 同一付款成功 webhook 重送兩次；只產生一筆有效付款結果及預期數量的票券。
2. Mock supplier 首次逾時或暫時失敗；異常中心看得到原因。
3. 背景 Job 安全重試後成功；開券沒有重複。
4. 模擬永久失敗或耗盡重試次數；後台保留待處理異常，不能默默遺失。

### Journey D：並發與庫存
1. 最後一張票由兩個顧客同時下單，最多一個完成預留。
2. 兩台商家裝置同時核銷同一權益，最多一次成功。
3. 付款期限到期與付款成功事件競爭，不能同時出現「已付款又已釋放售出庫存」的不一致結果。
4. 重試失敗後確認資料庫交易回滾、資料一致且可追蹤。

### Journey E：退款與存取控制
1. 尚未使用的已付款票券可由授權營運人員按流程模擬退款及撤銷。
2. 部分核銷的聯票不能被自動完整退款；後台明確拒絕或標記人工處理。
3. Customer 不可存取其他人的訂單；Merchant A 不可核銷 Merchant B 的票券權益。
4. Operator 不能進行僅 Admin 能做的權限變更。

---

## 7. API 與 UI 合約

路由實際命名可調整，至少要有相同能力；重要操作應具備正確 HTTP status 與 validation errors。

**Customer：**
- `GET /products`, `GET /products/{slug}`
- `POST /orders`、`GET /my/orders`、`GET /my/orders/{id}`
- 模擬付款入口（僅本地／Demo 環境開放），以及內部安全 webhook handler
- `GET /my/tickets`, `GET /my/tickets/{id}`

**Merchant：**
- `POST /merchant/redemptions`（以核銷碼與 venue context 驗證／核銷）
- `GET /merchant/redemptions`（只顯示獲授權資料）

**Admin / Operator：**
- Filament resources / actions：商品、供應商、場館、訂單、開券、退款、異常、稽核紀錄與指標。

**實作要求：**
- 所有 mutation 經授權和 server-side validation。
- `POST` 操作的重試策略要清楚；必要的業務操作以 idempotency key 處理。
- API 文件要包含重要 request / response 範例、常見錯誤與權限規則。
- 不允許 demo-only 的 admin bypass 進入正式環境。

---

## 8. 測試與品質門檻

### 必要測試
- Unit：金額計算、狀態轉換、效期規則、權限判定、退款規則。
- Feature：完整下單付款、登入／授權、開券、權益核銷、異常後台操作。
- Integration：MySQL transaction、Webhook 去重、Redis queue、Outbox dispatch / reconciliation、退款一致性。
- Concurrency（**必做**）：兩個以上獨立 DB connections / processes 同時預留最後庫存、同時核銷同一權益；驗證最終資料庫狀態及 unique constraints。
- Security：一般顧客不能讀取他人資料、商家跨場館拒絕、管理員權限差異、未驗簽 webhook 拒絕。
- Smoke / Browser：首頁、登入、商品購買、訂單列表、票券詳情、Filament、商家核銷；如瀏覽器自動化工具不可用，以可執行的 HTTP/UI 測試代替並記錄限制。

### 驗收硬性規定
1. 重要旅程都要有自動化測試，特別是付款重送、並行核銷與並行庫存。
2. 主要測試必須在與生產機制相近的 MySQL + Redis 環境執行，不可只在 SQLite 或 mock DB 自稱完成。
3. 使用 PHP formatter（例如 Pint）、靜態分析（例如 PHPStan / Larastan）、必要的前端 lint/build；嚴重等級錯誤不可忽略。
4. `php artisan migrate:fresh --seed` 僅可針對**專用本地 Demo DB**，不得在未知、正式或含真實資料的資料庫執行。
5. CI 不能將測試 skipped / flaky 宣告為 passed；若有阻礙，記錄實際輸出與範圍。
6. 若 Docker 或其他環境不可用，仍需盡可能完成可執行實作與替代測試，並在報告中**明確宣告整合驗收尚未通過**。
7. 若出現阻擋 MVP 核心路徑的缺陷，優先修復，不要用截圖、空白 mock 或 README 宣稱已完成。

---

## 9. Docker 與本地執行

建議 Compose 服務：`nginx`（或單一合適 Web server）、`app`、`queue`、`scheduler`、`mysql`、`redis`，可選 `mailpit`。  
需有：
- `compose.yaml`、Dockerfiles、entrypoint/health checks、`.env.example`、專案初始化與 demo seed 指令。
- Service dependencies 與 readiness/healthcheck 設計，避免 race condition。
- 資料持久化 volumes；MySQL/Redis 不對公開網路暴露；區分 dev 與 demo 憑證。
- Composer lockfile、可重現的 npm install/build、清楚的 migrate / seed / test 指令。
- README 的 Windows PowerShell 與一般 shell 使用方式（如指令相同可共用）；不要求使用者預先安裝 PHP、Composer、MySQL。
- 不建立付費雲端服務、不擅自部署 production，不提交 `.env` 或 demo 以外的憑證。

## 10. UI/UX 定義

以「現代營運工具」而非「套版旅行社購物網站」為風格：清楚的資訊層級、精簡且可閱讀的資料表、連貫的交易狀態提示、顯著的異常處理優先級。  
UI 必須實際連到後端；不做只有視覺效果的假互動。  
基本品質：手機／桌面響應式、文字對比、鍵盤操作、表單標籤、操作成功與失敗回饋、loading/empty/error states。  
如使用設計相關 Skills，保留一致的設計系統（顏色 token、spacing、components），避免混合多套不相容 UI 規範。

---

## 11. Codex 一次性工作順序

> 這是工作流程，不是需要使用者分批下達的任務。**一個指令完成全部項目。**

### Step 0 — Environment & Skill Discovery
- 檢查目前 repo、AGENTS.md、Node/PHP/Docker/Git 等可用環境，避免毀損已有檔案。
- 探索可用 Skills，選出適用集合；查驗核心版本相容性並在必要時作 ADR。
- 確定 Laravel 專案的最佳初始化方式、依賴版本與本地運行方式。

### Step 1 — Domain & Skeleton
- 建立 Composer/Laravel 專案、Docker Compose、CI、權限骨架、樣式系統。
- 建立 migrations、models、domain actions、state transitions、factories/seeders。
- 增加初始對應測試；不做未使用的抽象層。

### Step 2 — Core Transaction Flows
- 完成商品與訂單、庫存預留、Mock payment webhook、去重及開券。
- 完成 ticket entitlements、聯票規則、核銷與 QR 憑證。
- 完成 Outbox、重試、補償、退款與稽核記錄。

### Step 3 — UI & Back Office
- 完成顧客商品、訂單、票券頁面；商家核銷入口；Filament 營運後台。
- 提供可演示且真實操作後端的情境數據。

### Step 4 — Automated Verification
- 測試核心正常流程、失敗流程、權限、併發、資料庫約束。
- 完成格式檢查、靜態分析、前端 build、服務啟動／health check、smoke test。
- 修正直到**核心驗收全部通過**，或因外部環境不可解決而被阻斷；不可隱瞞。

### Step 5 — Documentation & Final Report
- 完成架構文件、ERD、API 文件、ADR、README、Demo 指引、測試報告。
- 核對 Git status、敏感資訊及最終變更；可在本地建立合理 commits。
- **只在全部工作完成或確有無法解除阻擋時**提供最終成果報告，不在每階段停下要求使用者選擇下一步。

---

## 12. 必交檔案

以下名稱可依專案實際情形調整，但所有內容不可缺少。

```text
travelops/
├─ app/
│  ├─ Domain/                 # Catalog, Orders, Payments, Tickets, Redemptions, Inventory...
│  ├─ Application/            # Actions, DTOs, Contracts
│  ├─ Infrastructure/         # Payment / Supplier / Notification adapters, persistence
│  ├─ Filament/
│  ├─ Http/
│  ├─ Jobs/
│  └─ Policies/
├─ database/
│  ├─ migrations/
│  ├─ factories/
│  └─ seeders/
├─ resources/
├─ routes/
├─ tests/
│  ├─ Unit/
│  ├─ Feature/
│  └─ Integration/
├─ docs/
│  ├─ architecture/
│  │  ├─ SYSTEM_OVERVIEW.md
│  │  └─ DATA_MODEL.md
│  ├─ decisions/             # ADRs，至少版本、交易一致性與開券可靠性
│  ├─ api/API_REFERENCE.md
│  ├─ demo/DEMO_GUIDE.md
│  └─ reports/MVP_DELIVERY_REPORT.md
├─ .github/workflows/ci.yml
├─ docker/
├─ compose.yaml
├─ .env.example
└─ README.md
```

**README 必含：** 系統定位及「非太金官方產品」聲明、技術棧、最低環境需求、一鍵啟動、DB 初始化、Demo 帳號建立方式、如何觸發失敗場景、測試指令、架構圖、限制及安全注意事項。文案要精簡、精確、可操作，不寫空泛宣傳語。

**Demo 指引必含：**
1. 乾淨環境啟動命令。
2. 如何取得／重設 demo accounts，不將通用管理員明文密碼硬編碼於應用程式。
3. 走完正常購票、開券、聯票核銷、重複核銷、開券失敗與修復、退款的具體步驟。
4. 可使用的 mock failure toggles／seed scenarios，以及測試後資料重置方法。

---

## 13. 最終成果報告格式（必須實際產生）

請建立 `docs/reports/MVP_DELIVERY_REPORT.md`，包含：

1. **Executive Summary：** MVP 是否完成（Pass / Partial / Blocked）及原因。
2. **實作功能矩陣：** M1–M7 每一項的實際完成狀態與對應檔案／路由，不能只列計畫。
3. **技術架構：** 實際版本、關鍵套件、模組、ERD、交易設計和主要 ADR。
4. **可靠性驗證：** Webhook 冪等性、庫存併發、核銷併發、開券補償、退款權限與資料一致性的測試結果。
5. **測試執行紀錄：** 執行日期、原始 commands、通過/失敗/略過數、已知錯誤、環境限制；不可編造測試輸出。
6. **部署與 Demo：** 本地啟動方式、Demo 資料、Demo 操作步驟、公開部署是否完成（預設為否）。
7. **Skills 使用報告：** 探索、選用、安裝與實際用途。
8. **Git 交付資訊：** branch、commit hashes、working tree 狀態；若沒有 repo 或 remote 就如實說明。
9. **安全與限制：** 沒有真金流／真供應商、Demo-only 機制、未完成或風險事項。
10. **後續 V2 建議：** 將 POS、LINE、真實 API、進銷存和營運報表列為未來規劃，不偷做成未驗收的半成品。
11. **學習導覽：** 使用者應先閱讀的 5–10 個核心檔案及每個檔案解決的工程問題，便於親自理解 Codex 的設計。

**報告中的完成狀態語意：**
- **Pass：** 可實際運行且必要的自動化驗收已通過。
- **Partial：** 部分可運行，但有明確尚未完成或未驗證的核心要求。
- **Blocked：** 缺少必要環境、憑證或工具而無法驗證主要功能；寫出已完成內容與解除阻擋的最短步驟。

---

## 14. 終止條件與禁止事項

- 禁止只交規劃書、只建立 Laravel scaffolding 或只做靜態 Dashboard。
- 禁止以 Docker 尚未安裝為由無條件放棄所有實作；若不可驗證，要實作可完成部分並留下準確報告。
- 禁止使用實際太金平台的登入、API、個資或自動化抓取其受限資料；所有 provider 必須 mock。
- 禁止在未知資料庫執行破壞性 migrate、reset、truncate 或刪除操作。
- 禁止直接把管理員權限賦予所有 Demo 使用者。
- 禁止在未驗證時宣稱「100% pass」「production ready」「高併發安全」。
- 禁止自行註冊／付費、建立公開部署或推送公開 Repository；若沒有外部授權，只需本地完整交付。
- 若兩種要求衝突，以交易正確性、安全性、資料保護、可執行驗收及 MVP 邊界優先。

---

## 15. 最後給 Codex 的執行命令

**請立即在目前指定的專案工作目錄開始執行。** 使用上列規格作為單一任務的完整交付契約。你有權自行調整合理的實作順序、選用可信 Skills、挑選相容套件及修復測試失敗。先完成整個 TravelOps MVP，再集中執行驗收；最後寫出 `docs/reports/MVP_DELIVERY_REPORT.md`，於最終回覆明確告知完成狀態、測試證據、主要成果及限制。**不要將工作切割成多輪請示，也不要僅回覆一份執行計畫。**
