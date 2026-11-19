# Developer Portfolio 整合交接（私有工作文件）

2026-11-12 唯讀查核本機 `C:\Users\yasg3\Desktop\portfolio`，HEAD `7b4787821a85f64b0477569af712fec395d0e87f`。遠端 main 是否前進仍需未來整合前重查；本輪沒有修改、建置或部署該網站。

## 放置與內容映射

| 本交付 | 作品集目的地／方式 |
| --- | --- |
| `screenshots/*.png` | `src/assets/travelops/`，由 `astro:assets` 的 Image 管理尺寸及格式。封面用 `travelops-catalog-desktop.png`。 |
| 深淺總覽 PNG、流程 SVG | `src/assets/travelops/architecture/`；受信任的本機 SVG 可作為 image asset，禁止把未審核 SVG 注入 HTML。 |
| MP4、海報、SRT | `public/media/travelops/`，影片不經 Astro 圖片轉換。不要把 ignored 原始 WebM 帶入 public。 |
| `TRAVELOPS_PORTFOLIO_ZH_TW.md` | `src/content/projects/zh-tw/travelops.mdx` 的長文來源。保留 frontmatter、再加入共用元件，移除本文的一級標題，避免與頁面標題重複。 |
| `TRAVELOPS_PORTFOLIO_EN.md` | `src/content/projects/en/travelops.mdx`，與繁中使用同一 `translationKey: travelops`。 |
| HTML 展示版 | 預設不需公開。優先嵌入已審核 PNG；若將來提供離線下載，另審核自包含 HTML、授權、品牌圖示及下載策略。 |
| evidence map、manifest、validation、此指南 | 留在私人 TravelOps 工程資料，不複製整個資料夾至公開網站。 |

兩份文案已提供有效 schema 欄位：`visibility: private`、相同 40 字元 `sourceCommit`、真實 `reviewedAt`、雙語 status、20–200 字元 description、相同 order 與相對 cover。`visibility: private` 表示**原始碼私有**，不是隱藏公開案例的開關。不得填私人 `repository`；沒有驗證公開 Demo，因此省略 `demo`。不可用 `#` 或 localhost 補值。

## 不能直接複製後就宣稱整合完成

現行 `src/content.config.ts` 由 `src/lib/schemas.ts` 的 `projectFields` 加入 `cover: image()`，另外禁止私有 repository URL。`scripts/check-content.mjs` 的目前契約不要求舊文件所述的 13 章，改為要求以下共用元件及順序；兩份來源文案仍須接上實作後才能通過整站內容檢查：

1. `ProjectIntroduction project="travelops" locale="…" topic="purpose"`。
2. `ProjectWorkflow project="travelops" locale="…"`。
3. `ProjectIntroduction` 的 `features`、`architecture`，依此順序。

最小獨立整合工作應延伸 `src/lib/project-introductions.ts` 的 `ProjectId = 'bookflow' | 'assetflow'` 與函式資料。現有函式在不是 BookFlow 時回傳 AssetFlow；必須明確加入 TravelOps，避免把別案內容帶入。

`src/components/ProjectWorkflow.astro` 目前也只以 AssetFlow／BookFlow 二分圖片，須加入 TravelOps 的安全截圖、雙語 caption。`src/lib/project-showcase.ts` 要核對詳情入口及素材對應。長文內的媒體表可改成具 alt/caption 的實際圖文；保留合成資料／Mock／無正式營運限制，不需要新增後端或假的互動系統。

## 首頁世界地圖的最小範圍

只新增 MDX 不會自動新增完整島嶼體驗。`src/lib/world-config.ts` 明確列出兩個作品島、熱點與 `project-(bookflow|assetflow)` 閱覽室路由；`world-map.ts` 有兩案位置與專案分支；`src/lib/simulations.ts`、場景與閱覽室元件也需檢查是否只接受既有兩案。

建議下一個獨立工作先完成雙語案例頁、共用簡介和真實媒體，再決定是否擴充島嶼。若要加 TravelOps 島，另調整 view／island 型別、世界配置、位置／動線、展示熱點與閱覽室 dispatch，延伸對應測試。不要沿用 AssetFlow 的 else 分支冒充 TravelOps，也不要為了新增案例直接重畫七島世界。

## 圖片、影片與無障礙

總覽品牌版是 `stack-diagram` HTML → PNG，Mermaid SVG 是另一份工程語意呈現；兩者用途不能混淆。深淺 PNG 尺寸相同，依既有主題機制切換；CSS `prefers-color-scheme` 只適用於網站跟隨系統主題的情況。保留寬高比例，圖下附雙語文字摘要。手機可點擊開大圖，不能只將 2800 px 圖縮到 350 px 後期待讀者辨認標籤。

影片範例（未修改網站）：

```html
<video controls preload="metadata" playsinline
  poster="/media/travelops/travelops-v3-demo-poster.png">
  <source src="/media/travelops/travelops-v3-demo.mp4" type="video/mp4" />
  <!-- 將 SRT 經驗證轉為 WebVTT 後，才加入 kind="subtitles" 的 track。 -->
  你的瀏覽器無法播放此影片，請閱讀下方分鏡文字。
</video>
```

繁中字幕已燒入畫面，英語 SRT 是交付字幕；HTML track 原生需要 WebVTT，不能把 `.srt` 直接當成 `.vtt`。不自動播放、不加虛構 Demo 連結。影片無音訊，不依賴旁白傳達資訊。海報及影片皆註明合成展示。

## 未來整合的驗證

在作品集獨立分支完成後執行 `npm run test:content`、`npm run check`、`npm run build`、相關 E2E 及既有 `npm run verify`，依專案規範確認雙語切換、heading anchor、路由、theme、390 px、無 JavaScript 備援及影片載入。重查最新 schema／元件與主分支，讓使用者審閱獨立 PR，再決定合併及部署。
