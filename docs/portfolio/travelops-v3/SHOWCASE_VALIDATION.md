# GitHub 展示改版驗證

日期：2026-11-21（Asia/Taipei）。基準為遠端預設分支 `codex/travelops-closeout` 的 `9bf8b97ed0621f58fa02a868f5594b2b6801f627`。查核時 PR #1–#5 均已合併，沒有未合併 PR。本輪使用獨立 worktree 與 `docs/github-showcase-refresh`，保留原工作樹未提交的 Compose 及任務文件。

## 素材與文件

| 項目 | 交付與來源 |
| --- | --- |
| 封面 | 重用 `screenshots/travelops-catalog-desktop.png`，真實 1440×1120 目錄畫面；保留全頁資訊，沒有重複存檔。 |
| 功能截圖 | README 直接呈現訂單進度、場館權益、POS、對帳與報表 5 張；另連結 2 張 390 px 手機截圖。原 8 張檔案、遮蔽與 manifest 保持不變。 |
| GIF | 新增 `video/travelops-pos-demo.gif`，從既有 MP4 的 00:33.48–00:49.16 連續 POS 片段轉出。800×450、8 fps、125 frames、15.63 秒、611,943 bytes、無限循環。沒有用靜態截圖合成操作；只降低解析度與影格率。 |
| 影片 | 重用 `video/travelops-v3-demo.mp4`，74.36 秒、1280×720、H.264、1,045,600 bytes。檔案已存在 Repository；README 提供可用下載入口，沒有待上傳的空連結。原繁中字幕、English SRT、海報與分鏡保留。 |
| 架構 | 重用經原始碼複核的總覽 PNG、SVG、Mermaid 及交易流程；下節列出本輪核對來源。 |
| 雙語文件 | 重整根目錄 README，新增 `README.en.md` 對應展示、限制、架構、測試與啟動資訊；保留原雙語案例、歷史驗收及第三方授權。 |
| 歷史決策 | ADR 002、003 加入狀態說明並導向現行 ADR 004，保留舊決策原文，避免把 MVP 的同步退款或持鎖 Mock 呼叫當成現況。 |

現有 MP4、PNG 和授權直接重用；沒有新增產品功能、改動交易程式、發布正式服務或合併 PR。

## graphify 與原始碼核對

使用 graphifyy 0.9.113 的 `graphify.extract.collect_files` / `extract`，只抽取 `app`、`routes`、`database`、`tests`、`bootstrap`、`config` 的程式碼。結果為 **142 files、894 nodes、2,254 edges**，`failed_sources` 與 `skipped_sources` 均為空。三個回傳設定陣列的檔案沒有可建模符號：`bootstrap/providers.php`、`config/app.php`、`config/travelops.php`，因此另直接閱讀設定。

抽取結果和快取留在 ignored `.runtime/showcase/`，沒有把大型工具圖或套件加入作品。AST 的呼叫解析不等於執行期 DI 證明；以下以 owning source 交叉核對，沒有只根據 README 推測箭頭。

| 關係／語意 | 本輪查核程式碼 | 結論 |
| --- | --- | --- |
| 四角色入口 → 同一個 Laravel | `routes/web.php`、`app/Providers/OperationsPanelProvider.php`、`app/Http/Controllers/` | Blade／Livewire／Filament 屬同一個模組化單體，沒有拆成微服務。 |
| Session、角色與範圍授權 | `routes/web.php`、`app/Providers/AppServiceProvider.php`、`app/Policies/`、`Checkout.php`、`Redeem.php` | Route middleware 加上 Application / Policy 檢查 owner、角色、門市 membership 及場館指派。 |
| POS → 共用 Checkout → MySQL | `app/Application/PointOfSale.php::sell`、`Checkout.php::place` | 同一套商品、庫存鎖與預留；POS 在外層交易內呼叫本地 Mock 付款。 |
| 付款 → Inbox／Outbox | `app/Application/PaymentWebhook.php::receive` | HMAC、payload、參考、金額、幣別、event key 及 body hash；付款確認與 issuance row 同交易。付款沒有供應商／退款的相同時間窗。 |
| MySQL → Scheduler → Redis → Worker | `routes/console.php`、`Issuance.php::relay`、`RefundOrder.php::relay`、`app/Jobs/`、`compose.yaml` | Scheduler 掃描持久工作列；主要 relay 每 10 秒。MySQL 不主動推送事件，Redis 是投遞層。 |
| Worker → adapter → 確認交易 | `app/Application/Issuance.php::process`、`AppServiceProvider.php`、`app/Infrastructure/MockSupplier.php` | 先保存租約，再於訂單鎖外 query／issue，最後另開交易驗證租約並寫結果；DI 綁定的是本地 Mock。 |
| 退款 ↔ 核銷 | `app/Application/RefundOrder.php`、`Redeem.php::ticket` | 共用訂單鎖；已核准未確認退款阻擋核銷，確認後才撤銷權益與回補庫存，整筆退款限定保留。 |
| 對帳與報表 | `app/Application/Reconcile.php`、`OperationsReport.php`、Filament 自訂 Pages | 合成帳冊與人工處置分開；既有時間及金額單位語意保留。 |
| Runtime 與 CI | `compose.yaml`、`docker/Dockerfile`、`.github/workflows/ci.yml`、lockfiles | 本機 App、Queue、Scheduler、MySQL、Redis 分程序；GitHub Actions 是驗證工具，沒有被描述為產品 runtime。 |

上述關係與既有架構圖一致，因此直接沿用圖像；完整歷史圖像來源與授權仍見 [source-evidence-map.md](architecture/source-evidence-map.md)。

## 測試證據與驗證邊界

1. 查核 [基準 CI run 44552435274](https://github.com/Cizing24/TravelOps/actions/runs/44552435274)：2026-11-19，head `9bf8b97`，conclusion `success`。Log 明確顯示 **36 tests、425 assertions**；Pint 144 files、PHPStan、Composer validate、Compose 啟動與 Chrome journeys 完成。這是既有基準的遠端執行，沒有宣稱是本輪本機重跑。
2. [2026-11-12 媒體驗收](MEDIA_VALIDATION_REPORT.md) 的 34 tests／416 assertions 仍保留並清楚標為歷史資料。兩個數字來自不同版本，不混用。
3. 本輪 `docker compose config --quiet` 可驗證設定。起始時本機 Docker Engine 未啟動；文件改版沒有重設資料、啟動原 Demo、重跑業務套件或改動測試環境。新提交的業務回歸結果以 PR Checks 為準。
4. PNG 以 Pillow 解碼，SVG 以 XML parser 檢查；GIF 逐影格解碼，125 張影格皆不同、總長 15,630 ms、loop=0，MP4 以 FFmpeg 完整解碼並以 ffprobe 核對尺寸、codec 及片長。原 20 項 manifest 的 SHA-256 重新核對，確認既有素材未改動。
5. 89 個本地 Markdown／媒體連結、相互語言入口、圖片檔案與 fenced code block 配對檢查通過；以 GitHub Markdown renderer 和本機 Chrome 檢查呈現。`git diff --check` 通過。既有 browser checker 在 Chrome 157.0.8103.80 確認 MP4 實際播放、3 處 seek、兩張 SVG 渲染及自包含 HTML 無外部請求；GIF 也在 Chrome 確認動態影格。README 的 1280 px／390 px 截圖留在 ignored `.runtime/showcase/`，不是產品手機實機驗證。
6. 目視抽查封面、訂單、POS、權益、對帳、報表及 GIF／影片影格。素材原有遮蔽保留；內容為合成資料，沒有登入密碼、完整交易 UUID、48 字元核銷 token、QR 或真實客戶個資。原片與帳密沒有提交。

未驗證：實機手機、相機掃碼、讀屏軟體、Safari／Firefox、正式流量及負載。沒有真實金流、供應商或外部通知，也沒有商業績效數字。

## 文案審查

依 documentation-and-adrs 保留可操作的啟動、測試、Volume 排查與技術決策連結；沒有為文件數量新增 ADR。最後依 speak-human-tw 保留專有名詞、數字、指令、限制與真實聲明，將開場改成具體的購票、POS、核銷與對帳流程；移除根目錄重複的簡略架構，直接引用已核對的完整架構與來源。使用者已授權整批直接完成，因此文案未逐項中斷確認。
