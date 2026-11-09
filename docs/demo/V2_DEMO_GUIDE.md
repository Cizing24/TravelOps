# TravelOps V2 展示

使用 README 的本機 Compose 指令啟動合成環境。以
`docker compose exec -T app php artisan travelops:demo --no-ansi`
建立展示帳號及交易。指令只在本地專用展示資料庫執行，請將新生成的密碼保存在本機。

## 多供應商履約

1. 以 `customer@travelops.test` 登入。
2. 分別購買潮線水族館單館票及島岸工藝館單館票。
3. 選擇模擬付款成功或重複成功回呼。兩張訂單各自只產生所購數量的票券。
4. 以營運人員登入並開啟 `/ops/outbox-events`。以供應商或訂單查閱同步與非同步結果。

## 門市銷售與庫存

1. 以 `pos-a@travelops.test` 登入並選取灣岸展示門市。
2. 選擇有庫存的商品、張數及現金或模擬卡片。開券後從銷售明細開啟票券憑證。
3. 以 `pos-supervisor-a@travelops.test` 查看灣岸展示門市的日結。日結按臺灣營業日、付款方式和實際已付款訂單統計。
4. 以營運人員在 `/ops/inventory-movements` 查看同一商品的預留與出售紀錄。管理員可在商品頁輸入理由並調整庫存。
5. `pos-b@travelops.test` 可進入港城展示門市。以該帳號開啟灣岸門市的銷售明細會收到 HTTP 403。
