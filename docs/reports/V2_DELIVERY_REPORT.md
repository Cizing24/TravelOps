# TravelOps V2 交付報告

## 1. 驗收結論

**Pass**。驗收日期為 2026-11-09（Asia/Taipei）。六個模組使用合成資料及本地 Mock Adapter，驗收涵蓋 MVP 回歸與 V2 旅程。

| 項目 | 證據 |
| --- | --- |
| 基底 | `codex/travelops-closeout`，`06ecdb57ea6ca6a7b6be26f3a3cbdbeb4f8b15e3` |
| 已驗收的功能提交 | `2d66d18c46b638a8334b5f521993b202647c2496` |
| 開發分支 | `codex/travelops-v2` |
| Pull Request | [TravelOps V2](https://github.com/Cizing24/TravelOps/pull/1) |
| Linux Compose | [GitHub Actions](https://github.com/Cizing24/TravelOps/actions/runs/42619112353) |

## 2. 六個模組

| 模組 | 交付入口 | 驗收證據 |
| --- | --- | --- |
| 多供應商履約 | `/ops/suppliers`、`/ops/outbox-events` | 同步與回呼開券，簽章與來源範圍、重送、亂序、回應遺失、退款後撤銷；Chrome F |
| 交易對帳 | `/ops/reconciliation` | 按營業日區間及供應商比對帳冊，追蹤金額或數量差異、遺漏、長時間待確認、重複、狀態差異及人工稽核；Chrome I |
| POS 與庫存 | `/pos`、`/ops/stores`、`/ops/inventory-movements` | 現金與模擬卡片、共用庫存、重送、門市隔離、日結、調整原因；MySQL 獨立連線併發與 Chrome G |
| 非同步退款 | `/ops/refunds` | 核准後排程、逾時查詢、拒絕、簽章回呼、已付款金額限制、核銷互斥與單次回補；Chrome H |
| 通知中心 | `/my/notifications`、`/ops/notification-deliveries` | 站內收件匣、Email／LINE 偏好、到期提醒、去重、失敗重試及本人可見；Chrome J |
| 營運報表 | `/ops/reports`、`/ops/reports.csv` | 臺灣時區、日期／通路／門市／供應商、成功退款與淨額、庫存及異常、CSV 公式防護；Chrome J |

## 3. 架構與資料相容性

- [系統總覽](../architecture/V2_SYSTEM_OVERVIEW.md) 說明角色、通路與 Worker。
- [資料模型](../architecture/V2_DATA_MODEL.md) 說明外鍵、唯一鍵、交易界線與歷史資料。
- [ADR 004](../decisions/004-v2-operation-reliability.md) 記錄庫存、退款、對帳及通知決策。
- [供應商回呼](../api/V2_SUPPLIER_WEBHOOK.md)、[退款回呼](../api/V2_REFUND_WEBHOOK.md) 與 [報表定義](../architecture/REPORTING_DEFINITIONS.md) 說明輸入及計算口徑。
- 以專用 MySQL 測試庫建立 MVP 訂單、加密票券、權益與歷史退款，再執行前向遷移。測試確認舊退款仍是終態，原始票券與權益可讀。

## 4. 驗證結果

| 檢查 | 環境與結果 |
| --- | --- |
| MVP 基線 | Windows 原生 PHP 8.4.27、MySQL／Redis，18 項測試、238 個斷言 |
| Windows V2 PHPUnit | 34 項測試、404 個斷言，0 失敗、0 略過 |
| V2 PHPUnit | Linux Compose，34 項測試、411 個斷言，0 失敗、0 略過 |
| 格式與靜態分析 | Pint 與現有等級 PHPStan 通過 |
| 依賴與前端 | Composer manifest／lockfile 驗證、Docker 建置的 `npm ci` 與 Tailwind build 通過 |
| 服務與 HTTP | App、MySQL、Redis 就緒，Queue／Scheduler 運行，`/up` 與票券目錄通過 |
| Chrome | MVP 購票、四館權益、分段核銷、後台授權與退款回歸，V2 旅程 F–J |

同時驗證線上與 POS 爭用最後一張票，以及退款審核和場館核銷在獨立 MySQL 連線上競爭。Webhook 重送、合成帳冊收斂、CSV 安全與通知隔離都有獨立自動化案例。

首輪六模組驗收在通知頁的 Chrome 等待步驟逾時。通知投遞頁加入狀態更新，旅程改為查詢已投遞的付款通知列。後續兩次 Linux Chrome 重演通過。 [首輪失敗記錄](https://github.com/Cizing24/TravelOps/actions/runs/42617658332) · [修正後重跑](https://github.com/Cizing24/TravelOps/actions/runs/42618757624)

## 5. 展示

依 [V2 展示指南](../demo/V2_DEMO_GUIDE.md) 操作。

```sh
docker compose up --build -d --wait
docker compose exec -T app php artisan travelops:demo --reconciliation-fixture --no-ansi
```

開啟 `/products`、`/pos` 或 `/ops`。初始化指令會在終端機顯示新建的合成帳號和密碼。

## 6. 技能與後續範圍

使用 planning-and-task-breakdown 釐清交易相依順序，test-driven-development 驗證鎖外呼叫和工作恢復，git-workflow-and-versioning 管理提交，Ponytail 控制新增依賴與抽象。這次驗收使用 Chrome、MySQL 和 Redis。報表與對帳用合成規模資料，未執行壓力或實機掃描驗收。V2 的帳冊自啟用後記錄，歷史 MVP 資料保留原來的交易與核銷事實。

## 7. 建議閱讀順序

1. `Checkout` 與 `PointOfSale` 說明線上及 POS 如何共用庫存預留。
2. `Issuance` 與 `SupplierCallback` 說明交易鎖外的供應商呼叫、回呼確認與取消補償。
3. `RefundOrder` 與 `Redeem` 說明核銷和退款如何共享訂單鎖。
4. `Reconcile`、`NotificationCenter` 與 `OperationsReport` 說明營運功能如何使用已持久化的交易事實。
