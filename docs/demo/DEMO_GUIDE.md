# TravelOps Demo 操作

## 建立資料

```sh
docker compose up --build -d --wait
docker compose exec app php artisan travelops:demo
```

初次執行會在終端印出本次產生的隨機密碼。旅客使用 `customer@travelops.test`，營運使用 `operator@travelops.test`，管理員使用 `admin@travelops.test`。場館帳號為 `merchant-a@travelops.test` 到 `merchant-d@travelops.test`，依序對應水族館、設計館、觀景台與工藝館。

需要換發時執行 `docker compose exec app php artisan travelops:demo --reset-passwords`。帳密只用於 Demo，請勿貼到公開紀錄。

## 購買、開券與核銷

1. 旅客登入，開啟票券目錄並選擇「灣岸四館聯票」。數量保留 1，建立訂單。
2. 選「付款成功」或「重複成功回呼」。開券工作完成後，交易進度區出現「查看票券 1」。進度每五秒更新，補送週期為十秒。
3. 開啟票券，核對四個場館及 QR Code，複製下方核銷代碼。
4. 另開一個私密瀏覽視窗以場館 A 登入。貼上代碼送出，確認成功，再送相同代碼一次，畫面會顯示已核銷。
5. 以場館 B 登入，使用同一代碼核銷。旅客重新查看票券時會看到已使用 2／4 場館，並顯示首次核銷加 14 天的到期時間。
6. 營運人員在 `/ops/orders` 以訂單編號搜尋，開啟交易紀錄檢視付款及開券追蹤資訊。在「核銷紀錄」可依場館查核。

## 開券失敗與補償

1. 購買「港城設計館單館票」，模擬付款成功。預設供應商第一次逾時，營運可見「等待重試」與追蹤編號，退避後重新開券。
2. 購買「岬角觀景台單館票」，付款後事件進入待人工處理。管理員在「供應商」將「港城展務」切換成正常開券。
3. 營運人員在「開券與異常」對該事件選「重新排程」，確認歷次結果及發券成功。可將供應商切回永久失敗以重複演示。
4. 供應商設定另提供首次暫時失敗及持續暫時失敗。後者三次後進入人工處理。訂單頁也提供付款失敗、延遲與亂序情境。

## 退款

1. 購買單館票，模擬付款並等待開券。先保持票券未使用。
2. 旅客回訂單詳情送出退款申請。營運人員到「退款審核」選該訂單並核准模擬退款。
3. 旅客票券會變為已撤銷，QR 不再顯示。管理員可查看庫存預留已回補。
4. 對已核銷部分場館的聯票重複申請，審核會標示拒絕及須人工處理。

## 停止或重置

`docker compose down` 停止服務並保留資料。只有確認是本工作目錄的 `travelops-demo` 專用容器及合成資料時，才用 `docker compose -p travelops-demo down -v` 移除其本地 volumes，再重新啟動與執行 Demo 指令。

## 本次 Windows 工作區

本次驗收使用 `http://127.0.0.1:18080`。在原生 MySQL（33384）與 Redis（36379）運行時，可從專案目錄重新取得帳密。

```powershell
& .\.runtime\php\php.exe artisan travelops:demo --reset-passwords
```

若需要重啟 Laravel，分別在三個 PowerShell 視窗執行下列命令。原生服務與設定位於本次工作區的 `.runtime` 與 `.env`。

```powershell
& .\.runtime\php\php.exe artisan serve --host=127.0.0.1 --port=18080 --no-reload
& .\.runtime\php\php.exe artisan queue:work redis --sleep=1 --tries=3 --timeout=30
& .\.runtime\php\php.exe artisan schedule:work
```
