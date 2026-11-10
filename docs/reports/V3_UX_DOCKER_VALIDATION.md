# TravelOps V3 驗收紀錄

基準為私人 repository 預設分支 `codex/travelops-closeout` 的 `c993a03204feb570123d8ca1114915973b0671c6`。V2 的 [PR #1](https://github.com/Cizing24/TravelOps/pull/1) 與 [PR #2](https://github.com/Cizing24/TravelOps/pull/2) 均已合併，[合併後 Linux CI](https://github.com/Cizing24/TravelOps/actions/runs/42810171129) 成功。V3 使用獨立工作樹與開發分支。

## 介面盤點與修正

以 Windows 主機的 Chrome 開啟 Compose 內的 V2 畫面和實際 DOM。桌面 1280 px 盤點 15 個角色頁面，768 px 盤點完成其中 10 頁，後續登入受節流限制。補以既有 Blade、狀態與授權程式追查流程。下表區分盤點發現與對應修正。

| 角色 | 盤點 | V3 修正 |
| --- | --- | --- |
| 旅客 | 目錄價格缺乏視覺主次，商品詳情、訂單與權益使用各自的資訊格式。 | 目錄以票名、場館數與票面價格呈現。商品頁列出效期起算、單館限制、可售量及購票預估。訂單的四個步驟配合原 5 秒輪詢，例外顯示付款失敗、重試、等待回呼或人工處理等文字。票券顯示已用與剩餘權益，退款阻擋核銷期間暫藏 QR 與文字憑證。 |
| 門市店員／主管 | 選單有單價和可售量，但送出前須心算，也缺少已收款、等候開券的指引。 | 切換品項、張數或付款方式會更新摘要。送出時提供明確等待狀態並攔阻同頁重送，保留後端冪等鍵、庫存與計價。近期交易列出時間與金額，主管可直接前往日結。 |
| 場館 | 核銷欄位已有標籤，頁面缺少代碼格式及提交提示。 | 先選場館，再貼上 48 字元代碼。提交中顯示等待，保留原服務的成功及錯誤原因，錯誤頁可返回角色工作台。 |
| 通知 | Chrome 1280 px 測得 checkbox 寬約 303 px，768 px 寬約 192 px，核取方塊與標籤分離。 | 把全寬欄位樣式限於文字與選擇控制項，勾選框約 19 px，整列為 44 px 以上可點擊標籤。訊息清楚標示時間、讀取狀態與相關連結。 |
| 營運／管理員 | 對帳及報表有自訂 Tailwind 類別未隨 Filament 載入，欄位與內容缺乏邊界。主控台的異常數字沒有查詢入口。 | 使用 Filament 輸入元件及限定範圍的 CSS。異常 KPI 連到開券紀錄。對帳並排顯示雙方狀態與金額或張數，保留嚴重度、差異原因、處理狀態和稽核參照。退款表保留審核流程，窄螢幕優先留訂單、金額與狀態。報表畫面顯示新臺幣元，CSV 保留原欄位、查詢與整數分。 |

旅客和工作台共用深綠主色、淺底、焦點外框與狀態語意。成功、待處理、需注意和失敗都附狀態文字。操作頁採固定級距的標題與 44 px 基本按鈕高度，目錄標題依視窗調整。表單的原生控制項和 Filament 表格保留各自適合的密度。按鈕只在允許動態時短暫過渡。介面以現有商品與交易資料呈現，沒有新增素材、趨勢推估或前端依賴。

## Windows Docker

### 既有 Volume

Windows 初次查詢回報找不到 `dockerDesktopLinuxEngine` 命名管道。使用者啟動 Engine 後，查到既有的 `travelops-demo_mysql`、`travelops-demo_redis`、`travelops-demo_runtime`。原工作樹的 Compose 修改把 `.sql` 路徑掛入 MySQL。該路徑未追蹤且是空目錄，容器內 `test -d` 亦確認掛載內容是目錄。原工作樹的目錄及兩個未提交檔案保留原狀。

啟動舊容器後確認 MySQL 已有 `travelops_demo` 及其授權，缺少 `travelops_test`，Demo 使用者連入測試 schema 時收到 1044。只以原容器 root 帳號套用 V3 SQL，隨即以 Demo 使用者成功登入 `travelops_test`。前後 Demo 訂單與票券筆數同為 5 筆、3 張。原 App 與兩個資料服務健康，Queue 與 Scheduler 運作，原 init 已成功結束，10 支 migration 均已執行。既有 App 的 `/up`、`/products` 回 200，訪客進入 `/pos`、`/ops` 各回 302。舊 Volume 以原容器完成檢查，V3 App 在隔離專案驗證。

若另一個本機環境也確認只有測試 schema 或授權缺失，可從 V3 工作樹將 SQL 送入既有 Compose 專案的 MySQL。先確認 Compose project、既有帳號與目標容器，再執行下列 PowerShell 指令。

```powershell
Get-Content -Raw docker/mysql/20-test-db.sql |
  docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot'
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -h127.0.0.1 -u"$MYSQL_USER" travelops_test -Nse "SELECT DATABASE();"'
```

### 乾淨 Volume

改用正式 SQL 檔與唯讀 bind mount，並禁止 Compose 在來源缺失時自建目錄。官方 entrypoint 先建立主 schema 和 Demo 使用者，再執行 init 檔。測試 schema 使用 `IF NOT EXISTS`，授權的底線經逸出以限定單一 schema。既有 Volume 會跳過首次初始化。相關依據見 [MySQL 官方映像 entrypoint](https://github.com/docker-library/mysql/blob/master/docker-entrypoint.sh) 與 [Compose bind mount 規格](https://docs.docker.com/reference/compose-file/services/#volumes)。

在 Windows Docker Desktop Linux Engine 29.9.3、Compose 5.7.0 上，以不同 project name、loopback 連接埠和映像標籤完成乾淨啟動。以 `docker inspect` 核對唯讀 bind，容器內 `test -f` 成功，MySQL 8.4.13 的 Demo 使用者可查詢測試 schema。Redis 7.4.12、MySQL 和 App 健康，init 回傳 0，Queue 與 Scheduler 已啟動，10 支 migration 均執行。Seeder 使用交易和 `firstOrCreate`，僅在商品首次建立時初始化權益與庫存，因此隔離環境重跑 init 可沿用既有商品。

保留既有與隔離的全部 Volume。舊 Demo 未重跑 Seeder 或測試套件。

## 驗證矩陣

| 環境與項目 | 證據 |
| --- | --- |
| Windows Compose | 新舊環境的 MySQL、HTTP、Volume 結果見上節。 |
| Windows Tailwind | Node 24.22.1 執行 `npm run build` 成功，Tailwind 4.3.5。 |
| PHP 品質檢查 | Windows Docker 的 Linux App 容器，PHP 8.4.27。Pint 142 個檔案、PHPStan/Larastan 及 Composer validate 均通過。 |
| PHPUnit | 34 tests、415 assertions、0 failures，含 MySQL 併發、Redis Outbox、角色及場館／門市隔離，以及退款處理期間的憑證呈現。 |
| Chrome／Playwright | Chrome 157.0.8103.40。購票、核銷與重複核銷拒絕、退款、同步及非同步開券、POS、庫存流水、對帳、通知和 CSV 旅程通過。門市兩張票預估及落帳均為 NT$ 640，CSV 為 64,000 分。最後收短 POS 選單後補驗 390 px 選擇、庫存不足提示、無效數量與同頁防重送。 |
| RWD | 桌面 1280 px、平板 768 px、手機 390 px。檢查目錄、商品、票券、通知、核銷、POS、日結、主控台、管理員帳號、退款、對帳及報表的頁面級溢出，並目視抽查主要畫面。 |
| 鍵盤、狀態與對比 | Chrome 驗證 Tab 到跳轉連結、焦點外框、Enter 跳至主內容、通知控制項與 Reduced Motion。取樣文字與底色對比為 6.13:1 至 9.95:1，焦點色與頁底為 5.18:1。 |
| GitHub Actions Ubuntu | V2 基準已通過。V3 以本 PR 最終提交的 Checks 為準。 |

現場手機、實際讀屏軟體與非 Chrome 瀏覽器未驗證。截圖留在本機驗收目錄，限合成資料與排除憑證的畫面。
