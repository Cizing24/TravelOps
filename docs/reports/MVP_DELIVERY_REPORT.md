# TravelOps MVP 成果報告

驗收日期為 2026-11-09（Asia/Taipei）。

## 1. Executive Summary

**整體狀態為 Partial。** M1–M7 已實作，Windows 原生環境使用 MySQL 8.4/InnoDB、Redis 7.4 與 Chrome 完成核心旅程驗收。PHPUnit 通過 **18 項測試、238 個斷言**，失敗與略過皆為 0。

Compose 組態與啟動腳本語法已通過。Windows 找不到 Docker daemon 的 `//./pipe/docker_engine`，實際映像建置與 Linux Compose 服務驗收尚未通過。GitHub Actions 工作流程已加入本地版本庫，尚未在 GitHub 執行。

## 2. 功能矩陣

| 模組 | 原生驗收 | 交付內容及入口 |
|---|---|---|
| M1 商品、供應商、場館 | Pass | 單館票、聯票、上架、商品場館權益、初始與調整庫存。由 `/ops/products`、`/ops/suppliers`、`/ops/venues` 管理。下單保存單價、效期、供應商與場館名稱／ID |
| M2 訂單、庫存與付款 | Pass | `POST /orders`、訂單詳情、取消、15 分鐘逾期釋放、TWD 分、HMAC webhook、下單與回呼去重。模擬付款包含成功、失敗、延遲、兩次重送與亂序 |
| M3 發券、聯票與 QR | Pass | `/my/tickets`、`/my/tickets/{id}`。每件商品發一張票，權益逐館核銷，首次核銷啟動有效期，UTC 儲存及臺灣時間顯示。憑證為 192 位元隨機代碼 |
| M4 核銷引擎 | Pass | `/merchant/redemptions`。文字輸入或貼上 QR 掃描結果，查詢範圍限授權場館。交易鎖及唯一鍵防止重複成功 |
| M5 開券異常與退款 | Pass | `/ops/outbox-events`、`/ops/refunds`。供應商逾時、暫時及永久失敗，Outbox 補送、退避、人工重排、全額退款、撤銷及庫存回補。部分使用退款留下拒絕與人工處理紀錄 |
| M6 Filament | Pass | `/ops` 的真實 DB 指標、搜尋、分頁、篩選、付款回呼、開券、稽核與核銷紀錄。管理員可維護商品、場館與角色 |
| M7 旅客及 Demo | Pass | `/products`、`/my/orders`、`/my/tickets`。合成旅客、營運、管理員與四個個別場館帳號，四項商品與預置情境，Chrome 驗證桌面及 390 px 版面 |

## 3. 技術架構

實測 PHP 8.4.27、Laravel 13.40.0、Filament 5.11.2、Livewire 4.4.8、Tailwind CSS 4.3.5、MySQL 8.4.13/InnoDB、Redis 7.4.7 Windows 移植版、Predis 3.7.1、PHPUnit 12.5.43、Playwright Core 1.65.0 和 Chrome 157.0.8103.38。QR 使用 BaconQrCode 3.1.1。PHP 與 Node 套件版本固定於 lockfiles。

單體應用由 Blade／Livewire、Filament、交易 actions、Eloquent 與 Mock adapters 組成。付款交易把開券意圖寫入 MySQL Outbox，Scheduler 投遞到 Redis，Worker 完成開券及稽核。訂單鎖序列化付款、過期、開券、核銷及退款，資料庫唯一鍵保護最終結果。

- [系統總覽](../architecture/SYSTEM_OVERVIEW.md)
- [ERD 與約束](../architecture/DATA_MODEL.md)
- [ADR 001 技術棧](../decisions/001-stack.md)
- [ADR 002 交易一致性](../decisions/002-transaction-consistency.md)
- [ADR 003 開券可靠性](../decisions/003-outbox.md)

## 4. 可靠性驗證

| 旅程 | 驗證結果 |
|---|---|
| A 正常付款與開券 | 訂單快照不受商品下架、改價與場館權益修改影響。付款後先持久化，再建立兩張票與八筆權益。旅客可顯示 QR，營運可開啟交易紀錄 |
| B 四館聯票 | 首次核銷啟動 14 天效期。A 與 B 分段核銷，跨場館操作及同權益重複送出遭拒。到期瞬間拒絕未使用權益 |
| C 重送、亂序與補償 | 驗簽失敗拒絕，成功事件重送兩次不重複付款／發券，晚到失敗不回退。Redis 投遞失敗注入後，Outbox 保留待處理事件。實際 Redis worker 處理逾時、失落任務補送及延遲付款。永久失敗與用盡次數可查詢並重新排程 |
| D MySQL 競爭與回滾 | 獨立 PHP 程序經 `SHOW FULL PROCESSLIST` 確認同時等待目標列鎖。最後一張只預留一次，訂單取得更新後一致的價格／權益，並行核銷只成功一次。到期與付款競爭仍維持預留一致性。資料庫 CHECK 注入錯誤後，付款、庫存、Webhook 與 Outbox 全部回滾，同一簽章事件可重試 |
| E 退款及存取控制 | 營運核准未使用訂單，付款、庫存與票券狀態原子更新，重審不重複退款。部分核銷拒絕整筆退款。旅客無法查他人訂單或票券，場館無法跨場館核銷，營運無法修改帳號角色 |

## 5. 測試執行紀錄

Windows PowerShell 以 `$php = (Resolve-Path .runtime/php/php.exe).Path` 指向本次的 PHP。下表均為 2026-11-09 的實際執行。

| 命令 | 結果 |
|---|---|
| `& $php artisan migrate --force` | 專用 `travelops_demo` 的 3 個遷移成功 |
| `& $php artisan travelops:demo --reset-passwords` | 合成資料與角色帳號建立成功 |
| `& $php vendor/bin/phpunit` | 18 通過、0 失敗、0 略過，共 238 assertions，耗時 71.097 秒 |
| `& $php vendor/bin/pint --test` | 通過 |
| `& $php vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | Larastan level 5，0 錯誤 |
| `& $php .runtime/composer.phar validate --no-check-publish --strict` | 通過 |
| `& $php .runtime/composer.phar audit --locked`、`npm.cmd audit --audit-level=high --cache .runtime/npm-cache` | 未回報套件漏洞公告 |
| `npm.cmd ci --cache .runtime/npm-cache --no-audit --no-fund`、`npm.cmd run build`、`node --check tests/Browser/smoke.mjs` | 通過，Tailwind CSS 已編譯 |
| `$env:TRAVELOPS_URL='http://127.0.0.1:18080'` 後執行 `npm.cmd run smoke:browser` | 通過。購票、QR、四館權益、兩館核銷、重複拒絕、營運交易明細、管理員／營運權限、退款與 QR 撤銷。瀏覽器頁面錯誤為 0 |
| `curl.exe -fsS -o NUL -w 'health=%{http_code}\n' http://127.0.0.1:18080/up` | HTTP 200，包含 MySQL 及 Redis 檢查 |
| `bash -n docker/entrypoint.sh`、`bash -n docker/mysql/20-test-db.sh`、`& .runtime/docker-compose.exe config --quiet` | 通過 |
| `& .runtime/docker-compose.exe up --build -d --wait` | 失敗。找不到 `//./pipe/docker_engine`，未進入建置或容器啟動 |

早期驗收發現 Laravel 測試缺少 Mockery、Filament 明細動作使用旅客訂單 Policy，以及測試的 MySQL trigger 需要超出測試帳號的權限。已補齊測試相依，為營運資源設置對應讀取權限，並用專用測試庫的 CHECK constraint 注入回滾錯誤。原始輸出保存在本機忽略目錄的 `.runtime/phpunit-result.txt`、`.runtime/phpstan-result.txt`、`.runtime/pint-result.txt`、`.runtime/browser-result.txt`。

## 6. 部署與 Demo

本次 Demo 位於 [本機 TravelOps](http://127.0.0.1:18080/products)，健康檢查已回應 200。公開部署未執行。Windows 原生 Demo 帳密可在專案目錄執行下列命令重新產生。

```powershell
& .\.runtime\php\php.exe artisan travelops:demo --reset-passwords
```

具備 Docker Engine 的環境可依 [README](../../README.md) 重跑 Compose 啟動與測試。購買、聯票核銷、開券逾時與重排、退款及資料重置步驟見 [Demo 指引](../demo/DEMO_GUIDE.md)。

## 7. Skills 使用

- 規格驅動、任務拆解、增量實作與 Ponytail 用於把主計畫切成可驗證旅程並控制範圍。
- 來源驅動、API 設計、安全與測試驅動用於版本查核、冪等合約、角色、簽章與資料庫競爭。
- Frontend UI Engineering、CI/CD、自查與 ADR 用於響應式介面、品質門檻、實作檢查及工程文件。
- 檢查 Graphify 既有圖譜時，工作區僅有主計畫，直接閱讀即可。瀏覽器驗收以 Chrome 和 Playwright 執行。此次未安裝新 Skill。

## 8. Git 交付

本地分支為 `main`。功能與測試提交為 `2d026c82038599b0bc9b616b9e47616456122331`，該提交後工作樹檢查為乾淨。報告另以本地提交保存，可用 `git log -2 --oneline` 取得其識別碼。未設定遠端。`.env`、Demo 帳密、測試資料目錄、依賴與建置產物均排除於 Git。

## 9. 安全與限制

- Mock 支付與供應商全程使用合成資料。模擬付款及 webhook 只在本地／測試環境、有 Demo 設定及足夠長的密鑰時啟用。
- 核銷使用 QR 及文字代碼。內建攝影機掃描、實體裝置與跨瀏覽器未驗證。
- 票券採首次核銷起算，未核銷前沒有固定到期日。退款只模擬整筆未使用訂單，已使用訂單會留下人工處理原因。
- Windows 使用 Redis 移植版做 Queue 驗收。Docker/Linux 映像與 GitHub CI 執行狀態仍待驗證。啟用可用的 Docker Engine 後，執行 `docker compose up --build -d --wait` 再跑 README 的測試。

## 10. V2 建議

若延伸到實際營運，可先以供應商的冪等契約、對帳及退款回調串接真實 API，並移出持鎖交易內的遠端 I/O。POS、LINE 通知、進銷存及營運報表各自以實際業務與角色需求確認後再規劃。

## 11. 學習導覽

1. [Checkout.php](../../app/Application/Checkout.php) 管理下單鍵、商品快照、庫存預留與釋放。
2. [PaymentWebhook.php](../../app/Application/PaymentWebhook.php) 驗簽、認領事件、核對付款並寫入 Outbox。
3. [Issuance.php](../../app/Application/Issuance.php) 處理補送租期、供應商退避、歷史紀錄與發券。
4. [Redeem.php](../../app/Application/Redeem.php) 以場館授權與交易鎖保護單次核銷，並即時判定到期。
5. [RefundOrder.php](../../app/Application/RefundOrder.php) 原子處理退款、撤銷、庫存回補及稽核。
6. [交易資料表 migration](../../database/migrations/2026_01_01_000001_create_travelops_tables.php) 展示資料庫唯一鍵與外鍵。
7. [OperationsResource.php](../../app/Filament/OperationsResource.php) 區分旅客所有權與營運資源授權。
8. [MySqlConcurrencyTest.php](../../tests/Integration/MySqlConcurrencyTest.php) 用真正的列鎖與獨立程序驗證競爭結果。
9. [RedisOutboxTest.php](../../tests/Integration/RedisOutboxTest.php) 用實際 Redis 及 Worker 驗證投遞、退避與失落任務補送。
