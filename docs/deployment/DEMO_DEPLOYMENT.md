# TravelOps 公開 Demo 部署

狀態：尚無可用部署 VM，沒有公開 URL，不計為已上線。2026-11-14，分支 `codex/demo-deployment-2026-11-14`。MySQL／Redis／Queue／Scheduler 不能省略。

`compose.demo.yaml` 為獨立的 Linux 單機拓撲：Caddy HTTPS → Nginx → 非 root PHP-FPM；獨立 MySQL 8.4、Redis 7.4 AOF、Queue Worker、Scheduler。僅 Caddy 對外開 80／443；MySQL、Redis、FPM、Nginx 不映射主機 port。Named volumes 使用獨立 project 名稱，不沿用本機 Compose。容器有記憶體限制及應用日誌輪替。

可使用已有 VM，或取得有配額的 OCI A1；沒有可用免費 VM 時需另決定 VPS，不能自動升級付費。ARM 架構必須另測，沒有因 Dockerfile 存在便聲稱支援。最低 CPU／RAM 仍需實測；本地限制不是雲端容量保證。

## Secrets 與初始程序

`.env.demo.example` 只含佔位符。真實 `.env.demo` 由部署主機安全檔案／Secret Manager 注入，限制營運帳戶讀取。`docker compose --env-file .env.demo -f compose.demo.yaml` 從該檔取得變數；Web 明確列出環境變數，**不會收到 MySQL root／migrator 密碼或 provisioning 密碼**。

1. 確認受控 DNS 指到 VM，雲端防火牆只允許 HTTPS／必要 SSH；`DEMO_HOST` 為真實可控名稱。Caddy 自動取得 ACME 憑證。不能用變動 Tunnel 或 IP HTTP 當長期網址。
2. 產生獨立 `APP_KEY`、Webhook HMAC、MySQL root／migrator／runtime、Redis 及四個受控角色密碼；不得沿用 `.env.example` 的 local 值。記錄通過 CI 的 `RELEASE_TAG` 完整 SHA。
3. 建置 `app`、`web` target，先 `up -d mysql redis`。MySQL 只建立 `travelops_public_demo`，初始 `travelops_migrator` 有該 DB 的 DDL 權限。
4. 透過受保護 shell 環境，把 migrator 密碼作為一次性 `DB_PASSWORD` 注入，不放命令文字；執行 `docker compose --env-file .env.demo -f compose.demo.yaml run --rm -e DB_USERNAME=travelops_migrator -e DB_PASSWORD app php artisan migrate --force`。Migration 不在正常 Web 啟動時執行。
5. 同一受保護一次性工作注入 `DEMO_PROVISION_CONFIRM`、`DEMO_CUSTOMER_PASSWORD`、`DEMO_OPERATOR_PASSWORD`、`DEMO_MERCHANT_PASSWORD`、`DEMO_POS_PASSWORD`，執行 `php artisan travelops:provision-public-demo`。指令核對 staging、HTTPS、debug=false、專用 DB、四個不同強密碼，拒絕既有資料，寫稽核但不打印密碼；不建立 admin。
6. MySQL 以離線管理程序建立 `travelops_runtime`，只授予該 DB 的 SELECT／INSERT／UPDATE／DELETE，撤銷 DDL、GRANT、FILE；`audit_logs` 只需 SELECT／INSERT，`migrations` 不授權。帳密經主機 Secret Manager／安全 SQL 工作階段設定，勿貼到日誌。MySQL root 與 migrator 均不供 App 使用。
7. 用 runtime 設定啟動 `app queue scheduler web https`。外網檢查 `/up`（包含 MySQL 和 Redis），再做完整交易／Worker／持久化驗收。保存 app／web image digest、Caddy 憑證及服務狀態。

初始化 SQL 角色配置是實際營運步驟，未偽裝為自動化；平台尚未取得，不能聲稱角色或雲端備份已建立。

## 公開邊界

公開訪客只看商品目錄；私下提供 customer、operator、merchant、pos_clerk 的受控展示身分。沒有共用公開營運／核銷帳密。`TRAVELOPS_PUBLIC_DEMO` 只在 staging、指定 DB、HTTPS、debug 關閉時允許內部 Mock；外部 `/webhooks/mock*` 在 staging 一律 404，付款模擬仍需登入、訂單授權、CSRF 與簽章。

Queue、Scheduler 持續執行；Session 改用有認證的 Redis，Secure／HttpOnly／SameSite=Strict。付款、退款、供應商和通知保留 Mock 性質，不接真實金流。頁面提示合成票券不能入場。
