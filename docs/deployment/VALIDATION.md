# Demo 部署變更驗證

日期：2026-11-14 至 2026-11-15（Asia/Taipei）。工作分支：`codex/demo-deployment-2026-11-14`。

| 項目 | 實際結果 |
| --- | --- |
| 原本機工作樹、既有資料 | 保留；使用部署工作內 clone 與獨立 Compose project／volumes |
| PHP 格式 | 5 個新增／修改 PHP 檔經專案 Pint 通過 |
| PHPStan | 隔離測試容器內通過，無錯誤 |
| PHPUnit | 36 tests、420 assertions 全數通過，包含新的 staging Mock 防護與外部 webhook 404 |
| 原測試 Compose | 新建 MySQL／Redis／App／Queue／Scheduler，健康檢查通過；未刪原有 volumes |
| 公開 Compose 設定 | `docker compose --env-file .env.demo.example -f compose.demo.yaml config --quiet` 通過 |
| 公開 image 建置 | `docker/Dockerfile.demo` web target 成功，包含 PHP-FPM app stage、非 root runtime、Nginx 靜態檔；Linux amd64 |
| Web image digest | `sha256:345ff00e8dcbbee342464670b3e4649da83f69c7abf896d5dbe50b0f323e5946`（本地建置產物，非公開 release） |
| Git 歷史／工作樹掃描 | Gitleaks 8.30.1；只命中 API 文件中的合成 UUID 冪等鍵，已人工確認誤判；未發現可用真實秘密 |
| 公開 Hosting／HTTPS | BLOCKED：沒有現有可用 VM，未新增付費資源 |
| 公開 FPM runtime／背景程序與持久化 | 未執行；本地舊架構測試不等於新拓撲或公網驗收 |
| ARM／真實外網裝置 | 未驗證 |

原始 local Seed／demo 指令安全限制保留，沒有將它們直接改成 production 可用。新 provisioning 不打印帳密、拒絕既有資料，不建立公開 admin。

此分支供審閱，不合併、不發布商用 Production；尚未有 Live Demo URL，Portfolio 不加空連結。後續 VM／成本決策與可重試程序見 DEMO_DEPLOYMENT.md。
