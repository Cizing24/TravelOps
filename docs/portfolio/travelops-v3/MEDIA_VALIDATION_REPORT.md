# TravelOps V3 媒體驗收報告

執行日期：**2026-11-12（Asia/Taipei）**。本報告為私人工程交付紀錄；公開網站只取經審閱的媒體及雙語案例，不公開整份證據文件。

## 來源與隔離

- 鎖定已合併 V3：`11c0e7ae5fa828a69438f807be220c4f37be41c9`。GitHub 唯讀確認 PR #3 已合併；歷史 PR CI run `42815175066` 為 success，其 V3 head 是 `b1f2b1ef36479dbaa97c82cd48a5513c7d5d7c31`。
- 本機主工作樹仍在原 `codex/travelops-v2-polish`，既有 `compose.yaml`、`docker/mysql/20-test-db.sh` 修改及未追蹤任務文件保留。
- 新工作樹：`C:\Users\yasg3\Desktop\TravelOps\.runtime\portfolio-media`；分支 `codex/travelops-portfolio-media`。此分支只加入媒體、文件與製作／驗證腳本，不改 V3 核心交易程式。
- Docker Desktop 啟動後 Engine `29.9.3` 可連線。使用 `travelops-media` 專用 project、新 MySQL／Redis／runtime Volumes、獨立 image tag 與 `localhost:18084`。未刪除、重設原資料或 Volumes。
- 原片、帳密、剪接中間檔及工具都留在 ignored 工作路徑。沒有呼叫 Gallery 分享工具、沒有修改 Developer Portfolio、沒有合併或部署。

## 交付結果

| 交付 | 實際成果 |
| --- | --- |
| 真實截圖 | **8 張 PNG**，6 張桌面 viewport 1440×900、2 張手機 viewport 390×844；使用 full-page capture，PNG 高度可超過 viewport。逐張來源、角色、尺寸及 bytes 見 [manifest](screenshots-manifest.md)。 |
| 影片 | [travelops-v3-demo.mp4](video/travelops-v3-demo.mp4)，**74.36 秒、1280×720、25 fps、H.264 High、yuv420p、無音訊、1,045,600 bytes**，fast-start。 |
| 字幕與海報 | 繁中字幕燒入畫面；另有英語／繁中 SRT，各 10 段且時間一致；海報由最終片 8 秒實際影格擷取。[分鏡](video/demo-storyboard.md) 包含時間碼、路由、剪接及原片說明。 |
| 系統總覽 | `stack-diagram` 自包含 HTML、**2800×1700 深淺 PNG**；另外保留工程語意用 `.mmd` 與 `.svg`。 |
| 關鍵交易流程 | Mermaid `.mmd`／`.svg`，另有 **3132×11888 PNG**；長圖適合閱讀工程細節，手機搭配文字替代。 |
| 雙語案例 | [繁中](TRAVELOPS_PORTFOLIO_ZH_TW.md)、[English](TRAVELOPS_PORTFOLIO_EN.md)，涵蓋角色、Problem → Solution → Outcome、五項決策、測試與限制、媒體順序和 alt/caption。 |
| 整合與公開審查 | [整合指南](DEVELOPER_PORTFOLIO_INTEGRATION.md)、[公開複核表](PUBLICATION_REVIEW_CHECKLIST.md)、[來源映射](architecture/source-evidence-map.md)、`asset-manifest.json`。 |

20 個媒體／圖表來源與輸出檔案共 **3,926,504 bytes（約 3.74 MiB）**。最終 MP4 適合普通 Git，不使用 LFS；原始錄影不包含在提交。SHA-256：

```text
ad746872b2a3b136b4db4b6f01dd5e42e262b490c32588f70c0fbf9efa31dbcf  video/travelops-v3-demo.mp4
```

其他檔案的 bytes、尺寸與 SHA-256 均記錄於 [asset-manifest.json](asset-manifest.json)。素材目錄在本工作樹 `docs/portfolio/travelops-v3/`；PR 中追蹤同一份檔案，不把本機原片說成 PR 資產。

## 本輪實際驗證

| 驗證 | 實際結果 |
| --- | --- |
| 來源版本 | 合併 V3 SHA、GitHub PR #3 merged、歷史 CI success 已查核。Laravel 13.40.0、Filament 5.11.2、Livewire 4.4.8 由該來源 composer.lock 解析。 |
| 隔離 Docker | 新建 image、MySQL、Redis、init、app、queue、scheduler；init exit 0，App／DB／Redis 健康。映像 runtime PHP 8.4.27。 |
| PHPUnit | 隔離 App 容器實際執行：**34 tests、416 assertions、0 failures**，6:29.241。只使用該 project 的 `travelops_test` 與專用 Redis 前綴。歷史 415 assertions 不當成本輪數字。 |
| 真實瀏覽器擷取 | Chrome `157.0.8103.51`；登入、旅客建立訂單／重複模擬付款／開券、POS 變更數量與提交、營運對帳及報表。腳本驗證 NT$ 640、8 張截圖、頁面水平溢出 0、沒有 pageerror、可見文字無完整識別碼。 |
| MP4 metadata | ffprobe 確認 H.264、1280×720、25 fps、74.36 秒、無音訊。FFmpeg 完成全片解碼；blackdetect 閾值 0.2 秒未產生 black_start。 |
| 實際播放 | Chrome 成功解碼並播放，currentTime 有前進；開頭、中段、結尾三處 seek 成功。這是桌面 Chrome 驗證，不代表所有瀏覽器／實機皆通過。 |
| 影片目視 | 抽查 12 個開頭／旅客／POS／營運／結尾影格，並查看全尺寸關鍵影格。字幕、遮蔽與狀態可讀；剪去 12 秒相同等待並在切點標示。沒有以靜態截圖假造互動。 |
| 架構圖 | 官方 `build.py` 內嵌字型／Logo；官方 `render_png.py` 產生深淺版本。實際目視兩版並修復 Lucide 在白色 tile 的深色模式可見性與入口箭頭穿字問題。兩張 Mermaid SVG 均以瀏覽器渲染並目視；圖像與來源對照。 |
| 離線 | 開啟自包含總覽 HTML，沒有任何 HTTP(S) 資源請求。下載 Logo 是公開技術名稱，未上傳架構或私人資訊。 |
| 檔案與字幕 | 8 PNG 尺寸、架構 PNG 最小寬度、SVG XML、影片全片解碼、10 段雙語字幕的時間界線及相同 timeline、公開文案敏感文字與隱形 Unicode 檢查通過。 |
| Portfolio schema | 唯讀載入本機 Portfolio 真實 `projectSchema`，以 Zod 驗證兩份 frontmatter 通過。完整整站內容檢查未執行；文案尚須接入共用元件，細節見整合指南。 |
| 腳本與 Git | Node syntax check、Python compile check、媒體腳本內 assert/self-check、`git diff --check` 通過。提交前檢查 staged 檔案，原片／帳密／工具不納入。 |

本輪不是原有 `smoke.mjs` 的完整重跑；新增的素材旅程只驗證所錄製流程。未重跑 Pint／PHPStan：沒有修改 PHP 或產品 CSS／JS。新 PR 的 GitHub Actions 最終狀態以該 PR Checks 為準，與上述歷史 CI 分開。

## 隱私與資料層次

截圖逐張目視，保留真實狀態與金額。完整交易 UUID、測試帳號文字被替換成明確遮蔽標記；QR／文字核銷憑證在錄製前隱藏，並顯示公開素材遮蔽提示。這些是媒體用途的瀏覽器呈現處理，沒有修改後端資料、票券規則或付款邏輯；最終 PNG／MP4 遮蔽為不可還原的像素。影片登入前先建立授權 session，再建立錄影 context，所以錄影不包含密碼輸入。素材皆無瀏覽器網址列。

公開候選只有審核後 PNG、MP4、SRT、通用架構和兩份案例。原片與 capture JSON 在本工作樹 `.runtime/media/`；帳密在 `.runtime/demo-credentials.txt`。此處不複製任何帳密值。HTML 內嵌品牌 Logo 與字型，授權文件隨附於 `architecture/licenses/`。

## 工具、Skill 與重現

實際採用 `using-agent-skills`、Ponytail、Git 工作流程、browser-testing 原則（無 Chrome DevTools MCP，使用既有 Playwright/Chrome）、documentation-and-adrs、code-review-and-quality；Graphify 經評估沒有既有圖，本輪使用針對性原始碼查核，沒有建立額外知識圖。

新增的專案工具只在 ignored 路徑：`caru-ini/stack-diagram` 固定 **5af3b095fff7109c905c5284dc510dc06588bade**、Python Playwright 1.65.1 及匹配 Chromium 158、Mermaid CLI 11.12.0。技術標誌使用 svgl 的 Laravel／MySQL／Redis／Docker，抽象節點使用 Lucide；來源 URL 在 `architecture/logo-sources.json`。`share.py` 已檢視但從未執行。未安裝全域 AGENTS 規則、未使用 Gallery 或第三方上傳服務。

製作與重現命令見 `scripts/portfolio/README.md`，主要入口為 `capture.mjs`、`build-video.py`、`build-architecture.py`、`check-browser.mjs`、`validate.py`。沒有新增產品執行期依賴。Mermaid CLI 間接依賴的舊 Puppeteer 僅供本機可信來源圖表匯出，不用於不受信任網頁。

## 剩餘限制與下一步

必要素材已齊。擁有者仍需對照公開複核表審閱，之後另開 Developer Portfolio 整合 PR；兩份案例有 schema frontmatter，但不能直接繞過既有元件與內容契約就視為網站整合完成。

未驗證手機實機、真實相機掃碼、讀屏、全面跨瀏覽器或正式負載。沒有真實金流、供應商、外部通知、真實客戶及商業績效；未公開部署。保留所有原有及本輪隔離 Volumes，沒有做破壞性清理。
