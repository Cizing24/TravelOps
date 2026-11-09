# TravelOps V2 — Delivery Report

## 1. 執行狀態

**Partial**。本次開發以遠端預設分支 `codex/travelops-closeout` 的
`06ecdb57ea6ca6a7b6be26f3a3cbdbeb4f8b15e3` 為基底，開發分支為
`codex/travelops-v2`。本報告記錄 2026-11-09 臺灣時間的驗證。

## 2. 模組進度

| 模組 | 實作範圍 | 入口 |
| --- | --- | --- |
| V2-01 多供應商 | 同步及非同步模擬開券、Inbox、查詢補償、操作稽核 | `/ops/suppliers`、`/ops/outbox-events` |
| V2-02 對帳 | 待實作對帳差異與人工處理 | — |
| V2-03 POS／庫存 | 門市角色、共用庫存、現金和模擬卡片、庫存流水、門市日結 | `/pos`、`/ops/stores`、`/ops/inventory-movements` |
| V2-04 退款 | MVP 退款仍可操作，開券結果未知時阻擋退款 | `/ops/refunds` |
| V2-05 通知 | 待實作 | — |
| V2-06 報表 | 已有本店日結，跨通路報表與 CSV 待實作 | `/pos/{store}/closing` |

## 3. 架構及驗證

- [實作順序與契約](../architecture/V2_IMPLEMENTATION_MAP.md)
- [資料模型與交易界線](../architecture/V2_DATA_MODEL.md)
- [供應商回呼契約](../api/V2_SUPPLIER_WEBHOOK.md)

| 檢查 | 結果 |
| --- | --- |
| Windows 上的 MVP 基線 PHPUnit | PHP 8.4.27、MySQL／Redis，18 項、238 個斷言通過 |
| 供應商整合測試 | 4 項、30 個斷言通過，涵蓋交易鎖外呼叫、worker 中斷、簽章、重送、亂序、跨來源及回應遺失 |
| Composer manifest 與 lockfile | `composer validate --no-check-publish` 通過 |
| 前端建置 | `npm run build` 通過 |

## 4. 展示與交付

依 [V2 展示指南](../demo/V2_DEMO_GUIDE.md) 啟動合成環境並建立帳號。
本次採用 Ponytail 控制變更範圍，沿用 test-driven-development 驗證供應商
行為，並以 git-workflow-and-versioning 管理分支。
