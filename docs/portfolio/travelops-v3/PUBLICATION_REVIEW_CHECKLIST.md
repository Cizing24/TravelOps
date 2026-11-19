# 公開前複核

這是供未來發布時使用的審核表。完成私人 TravelOps PR 不等於已同意公開發布，也沒有修改 Cloudflare Pages 或個人作品網站。

- [ ] 逐張開啟八張最終 PNG，確認商品、價格、流程均來自真實 V3；日期及資料為合成情境。
- [ ] 全程播放最終 MP4，查看開頭、旅客、POS、營運與結尾；繁中字幕可讀、英語字幕語意一致。
- [ ] QR、48 字元核銷碼、完整交易 UUID、帳號、token、密碼、私人網址及 Windows 路徑均不在公開素材可視內容中；遮蔽已固化為像素。
- [ ] 不公開 `.runtime/` 的原始錄影、登入 storage state、帳密、log 或 contact sheets，不將全部工程文件複製到 public。
- [ ] 確認本機 mock 交易無法被誤解為真實付款、供應商串接、客戶營運或實際業績。
- [ ] 公開文案僅使用兩份已審核雙語案例；內部路徑、完整測試帳號、私人 PR 及 repository URL 不公開。
- [ ] 圖上箭頭、短交易與鎖外呼叫符合 evidence map；總覽 HTML 及 Mermaid SVG 不被說成同一匯出格式。
- [ ] 保留合成資料、Mock 整合、未正式部署及未驗證項目；歷史 CI 與本輪測試分開標日期和來源 SHA。
- [ ] 審查品牌 Logo／商標與附帶 MIT、OFL 字型授權；沒有宣稱廠商背書。未向 stack-diagram Gallery 上傳。
- [ ] Astro 使用 `visibility: private`，省略私人 repository 和不存在的 demo；雙語 translationKey、sourceCommit、日期與封面相符。
- [ ] 圖片有雙語 alt／caption；影片有 controls、海報與文字替代；英文 track 若使用需轉成有效 WebVTT。
- [ ] 深淺模式和手機寬度閱讀可用，圖中文字有替代摘要；影片大小、快取與平台單檔限制已確認。
- [ ] 另開作品集整合 PR，通過當前內容契約與驗證，由擁有者決定合併及部署。

擁有者審閱：________________　日期：________________
