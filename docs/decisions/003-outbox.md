# ADR 003 · Transactional Outbox 與開券可靠性

狀態：已由 [ADR 004](004-v2-operation-reliability.md) 更新。以下保留 MVP 的本地 Mock 設計；現行 `Issuance::process` 已將供應商查詢與呼叫移出訂單鎖，並在新交易確認結果。

付款更新與 Outbox 新增同一個 MySQL commit。每 10 秒的排程尋找可執行事件，推進 Redis queue 並寫入兩分鐘投遞租期。推送失敗會回滾租期。Redis 若在推送後遺失任務，租期屆滿後會再送。重複 job 由訂單鎖、事件狀態及 ticket unique key 去重。

Worker 取得訂單及事件鎖後才執行本地供應商 mock，開券、狀態、嘗試紀錄與稽核在同一交易中寫入。每個開券週期最多三次供應商嘗試，暫時失敗依序退避 15、30 秒。永久失敗或耗盡可由營運人員重新排程，舊嘗試仍保留。資料庫或 Worker 層非預期錯誤另由 Laravel job 重試三次，未處理的 Outbox 可在租期過後再補送。

實際遠端開券須把供應商網路呼叫移出持鎖交易，並以 Outbox trace ID 當供應商端冪等參考。模擬 adapter 目前只執行純本地運算。
