# TravelOps Demo 維運與驗收

只有一般商品目錄公開；四種展示身分密碼分別保存於營運 Secret Manager。全部為合成場館／交易；沒有公開 admin，受控 operator 只能執行原有授權能力。使用前檢查目前部署 SHA 與 `.env.demo` 的 staging、HTTPS、debug=false、專用 DB、Secure Session。

## 完整交易驗收

1. 受控 customer 下單→Mock 付款→讀回付款成功；確認 MySQL Outbox、Redis Queue 和 Worker **實際非同步**完成開券。不得由 smoke 直接呼叫 issuance 取代 Worker。
2. 重複回呼／亂序、Queue 暫停再啟動、暫時開券失敗重試、永久失敗人工補償；工作不能遺失或重複出票。
3. POS 身分只可進入指派門市，完成付款、庫存與交班；merchant 只能核銷指派場館。錯誤角色／門市／場館應 403。
4. 退款申請→營運審核→背景處理；退款中票券不可核銷，核銷後不可直接整筆退款。驗證重複核銷、競爭互斥與稽核一致性。
5. 營運對帳與報表讀回同一合成交易。重啟 App、Queue、Scheduler、MySQL／Redis 後資料與待處理工作仍在，`restart: unless-stopped` 恢復背景程序。
6. 外網 HTTPS、Cookie、CSRF、Debug、`.env`、測試 webhook 404、DB／Redis 不公開；桌面、平板、390／320 px、鍵盤及完整旅客路徑。不同電腦／未登入 GitHub 的瀏覽器驗證，不能用本機測試冒充。

## 保存與重置

公開不提供個資輸入或寫入帳號；受控身分禁止輸入真實資訊。每月由操作者檢查容量及情境，需重置時建立新隔離 DB／volumes 和新 Secrets，重新 provision／驗收再切換。舊資料先備份、保留至少 7 天；不對既有 volumes 執行 down -v、migrate:fresh、TRUNCATE 或覆寫重要 DB。

MySQL 使用同版本 mysqldump 的一致性備份（InnoDB `--single-transaction`），帳密從受保護 option file 注入；Redis 備份包含 AOF，APP_KEY／HMAC 保留於獨立 Secrets 備份。限制檔案權限、加密異地保存、記錄 SHA-256。還原到新 DB，先停 Worker／Scheduler，驗證核心表數量、Outbox、庫存、票券與退款狀態，再決定切換。未演練前不承諾 RPO／RTO。

## 監控、更新及停止

監控 `/up`、5xx／429、failed_jobs、最老 pending Outbox、Queue 延遲、Scheduler heartbeat、磁碟和容器重啟次數。使用 `docker stats --no-stream` 量測峰值記憶體；不以初始候選規格聲稱容量足夠。

先 CI、備份、一次性 Migration，再發布固定 image digest；Queue 使用 `queue:restart` 平順重載，Scheduler 保持一份。回滾至先前已驗收映像，保留向下相容 schema，重新驗證交易；資料問題還原至新 DB，不直接覆寫。

停止服務用 `docker compose --env-file .env.demo -f compose.demo.yaml stop`，保留 volumes。刪除 VM／DB／volumes 需使用者另行決定並確認備份。尚無 VM 時，不對原網站或原本機 Compose 執行任何上述命令。

## 原始碼公開檢查

Gitleaks 8.30.1 已檢查完整 clone Git 歷史與工作樹；唯一命中是 `docs/api/API_REFERENCE.md:24` 的 UUID 冪等鍵 JSON 範例，確認不是真實秘密。不是所有任意格式秘密都能被自動辨識，仍需人員核查。沒有改寫 Git 歷史或更改可見性。

`composer.json` 的 proprietary 授權保留。Repo Public 不等於可自由重用；示範場館為合成素材，不替真實品牌背書。雲端上線與完整外網驗收仍未完成。
