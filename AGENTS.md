# AGENT HANDOFF

## TL;DR — 若只想快速接手先讀此段
- 今日：資料維護搜尋列移到 tabs 下方置中，切換分頁時搜尋字串會清空；搜尋支援四個表的欄位比對。
- 卡住：部分畫面仍顯示舊版（搜尋列位置/按鈕未更新），可能是 DocRoot 未載入新版或瀏覽器快取，需再確認。
- 明日優先：1) 確認搜尋 UI 已套用最新 HTML/CSS/JS 2) 驗證搜尋能在四個表正常過濾 3) 規劃並串接各項費用/收支/車輛成本 API。
- 快速入口：`public_html/accounting/master/index.php`、`assets/js/master.js`、`assets/css/admin.css`、`public_html/accounting/{expenses,cashflow,vehicle-costs}/`。
- 環境提醒：MAMP Document Root=`/Users/pei-yinglin/Projects/judacargo/accounting-php/public_html/accounting`；檔案上傳成功會自動匯入，只有失敗時需手動觸發。

---

## 📌 最新交接 — 必讀 (3 min)
1. **資料維護搜尋**  
   - 搜尋列移至分頁下方置中 (`card__search--center`)，右上僅保留「上傳舊檔」。  
   - `master.js` 新增 `searchKeys`（客戶 code/name、車輛 code/license、員工 code/name、科目 name/mapping），切分頁時會清空搜尋字串，避免殘留條件。  
   - 若畫面仍是舊版，請確認 DocRoot 讀取的是最新版 HTML/CSS/JS 並強制重載。

2. **新模組骨架（待串 API）**  
   - 各項費用：`public_html/accounting/expenses/` + `assets/js/expenses.js`，欄位「登記日／會計科目／支出／備註／操作」。  
   - 收支表：`public_html/accounting/cashflow/` + `assets/js/cashflow.js`，欄位「支出項目／支出金額／收入項目／收入金額」含合計。  
   - 車輛成本：`public_html/accounting/vehicle-costs/` + `assets/js/vehicle-costs.js`，車號由側欄連結帶入；欄位「交易日／會計科目／收入／支出／合計／備註／操作」。  
   - 三者目前僅顯示「尚未串接資料來源」，待 API 補齊。

3. **搜尋/資料顯示疑慮**  
   - 若搜尋無效或科目表消失，可能是搜尋仍套用殘值或未載入最新 JS；切分頁會自動清空搜尋，請再強制重載並確認 API 有回傳資料。

---

## 🔄 目前任務 — 必讀 (2 min)
1. 確認 master 頁已套用最新搜尋列與樣式，並在四個表驗證搜尋（依 `searchKeys` 比對）。  
2. 檢查科目表資料是否因搜尋或 API 失敗導致空表，必要時重新載入並看 Network/Console。  
3. 設計並串接各項費用／收支／車輛成本 API，補上資料來源與欄位 mapping。  
4. 若仍要調整搜尋列位置或按鈕，請直接修改 DocRoot 內的 HTML/CSS，避免舊版快取干擾。

---

## 📚 參考資料 — 選讀 (需背景)
- `handoff/2025-11-13.md`：先前營收匯入、票據對帳、匯款帳號的完整紀錄。  
- `public_html/accounting/api/sales/*.php`、`assets/js/sales-*.js`：需處理營收匯入／收款相關工作時參考。

---

## 🗂 過往紀錄 — 選讀
- 更早紀錄於 `handoff/2025-11-12.md`、`2025-11-11.md` 等，可依需要回查。
