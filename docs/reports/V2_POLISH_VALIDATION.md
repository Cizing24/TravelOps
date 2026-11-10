# TravelOps V2 收尾驗收

## 審查與修正

2026-11-10 從 `3a85a65a8102b42efaa58599f2c3e8efbbd4e981` 開始。GitHub 顯示專案為私有，[PR #1](https://github.com/Cizing24/TravelOps/pull/1) 開啟且可合併，基底為 `codex/travelops-closeout`，無行內審查討論。[交接提交 CI](https://github.com/Cizing24/TravelOps/actions/runs/42619935592) 通過。

| 分級 | 原問題 | 調整 |
| --- | --- | --- |
| P1 | POS 選商品時只見品名和庫存，送出前無法從表單確認單價。 | [POS 表單](../../resources/views/pos/index.blade.php) 沿用共用金額格式顯示單價及可售量。[Chrome 旅程](../../tests/Browser/smoke.mjs) 加入合成票券單價檢查。 |
| P2 | 商品頁未標價量單位，旅客送出前不知道後續付款入口。 | [票券詳情](../../resources/views/products/show.blade.php) 補上每張單價與模擬付款指引。 |
| P2 | CSV 連結使用已套用的查詢參數，畫面未說明新條件要先套用，也未標示匯出金額單位。 | [報表頁](../../resources/views/filament/reports.blade.php) 說明操作順序及新臺幣分。 |

審查從目錄到訂單、通知與票券，從 POS 入口到門市日結，以及營運台的退款、庫存、對帳與報表。原始碼中已有標籤、錯誤摘要、跳至主要內容、焦點樣式與狀態文字。未從這些證據確認其他 P0 或 P1。此次介面改動只涉及 Blade 的顯示內容。檢查付款 Inbox／訂單鎖、共用庫存鎖、退款與核銷訂單鎖、門市授權及報表／CSV 共用查詢與公式防護，這些路徑沒有程式差異。

文件調整見 [README](../../README.md)、[V2 展示指南](../demo/V2_DEMO_GUIDE.md) 及 [作品案例](../portfolio/TRAVELOPS_CASE_STUDY.md)。

## 驗證記錄

| 環境與指令 | 結果 |
| --- | --- |
| Windows，Node v24.22.1，`npm run build` | 通過，Tailwind CSS v4.3.5。 |
| Windows，`node --check tests/Browser/smoke.mjs` | 語法檢查通過。 |
| Windows，`docker compose config --quiet` | 通過設定解析。 |
| Windows，`docker compose ps -a` 與 `GET /up` | Docker Linux engine 連線失敗，HTTP 未取得回應。未在既有 Demo 執行 Pint、PHPStan、PHPUnit 或 Chrome 旅程。 |

既有未提交的 Compose 修改把初始化來源指向 `docker/mysql/20-test-db.sql`，目前該路徑是目錄。設定解析無法驗證初始化檔內容或首次啟動。原有 Compose 變更及資料保留在本機，提交使用版本庫的 Compose 基線。README 的啟動步驟沿用 Linux Compose 驗收流程。

本輪 Linux Compose 的 PHP 及 Chrome 結果以收尾 PR 的 Checks 為準。歷史測試數來自 [V2 交付報告](V2_DELIVERY_REPORT.md)。交接簡報記載 409 個 Linux 斷言，交付報告對其功能提交記載 411 個，兩者均與本輪執行結果分開。

## 尚需手動檢查

在本地服務可用時，用桌面與窄螢幕走訪 `/products`、`/pos` 和 `/ops/reports`。使用 Tab 檢查焦點，確認 POS 原生選單的品名與單價、長字換行、表格可讀性、報表匯出及等待、錯誤與空狀態。實機、讀屏和完整對比量測未驗證。素材擷取場景見 [V2 展示指南](../demo/V2_DEMO_GUIDE.md#素材擷取清單)。
