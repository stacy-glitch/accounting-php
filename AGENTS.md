# AGENT HANDOFF

## TL;DR — 若只想快速接手先讀此段
- 今日：完成各項費用／收支管理／車輛成本三個模組的頁面骨架與 JS，以及資料維護搜尋功能；所有畫面已在 MAMP docroot 驗證。 
- 卡住：這三個模組尚未串接 API，表格仍顯示空資料；資料維護搜尋在部分環境仍可能看到舊版（需再確認快取或伺服器輸出）。
- 明日優先：1) 為新模組定義並串接後端 API 2) 驗證搜尋欄在正式環境顯示正常 3) 與會計確認車輛成本/收支表使用的實際欄位來源。 
- 快速入口：`public_html/accounting/{expenses,cashflow,vehicle-costs}/`、`assets/js/{expenses,cashflow,vehicle-costs}.js`、`master/index.php`、`assets/js/master.js`。
- 環境提醒：MAMP Document Root=`/Users/pei-yinglin/Projects/judacargo/accounting-php/public_html/accounting`；檔案上傳成功會自動匯入，只有失敗時才需手動處理。

---

## 📌 最新交接 — 必讀 (3 min)
1. **各項費用頁**  
   - 位置：`public_html/accounting/expenses/` + `assets/js/expenses.js`。  
   - 表頭與零用金相同，欄位為「登記日／會計科目／支出／備註／操作」。`loadExpenses()` 目前只顯示「尚未串接資料來源」並渲染空表，待 API 完成後將回傳資料整理成 `state.records`。  
   - 若要串接後端，只需將 API 回傳陣列（含 `entry_date`, `subject`, `amount`, `note`, `id`…）交給 `renderRows()`。

2. **收支管理（收支表）**  
   - 位置：`public_html/accounting/cashflow/` + `assets/js/cashflow.js`。  
   - 欄位依序為「支出項目／支出金額／收入項目／收入金額」，`tfoot` 顯示雙方合計。`loadCashflow()` 目前僅顯示提示文字，尚未呼叫 API。  
   - 串接時只需產生包含 `expense_item`, `expense_amount`, `income_item`, `income_amount` 的資料並交由 `renderRows()`。

3. **車輛成本頁**  
   - 位置：`public_html/accounting/vehicle-costs/` + `assets/js/vehicle-costs.js`。  
   - 車號改由側邊欄連結控制（例：`/vehicle-costs/?plate=830-W6`），`data-initial-plate` 會帶進 JS。  
   - 表格欄位「交易日／會計科目／收入／支出／合計／備註／操作」，`loadVehicleCosts()` 同樣僅顯示提醒，待 API 產出 `transaction_date`, `subject`, `income`, `expense`, `note`, `id` 等欄位即可渲染。

4. **資料維護搜尋**  
   - `master/index.php` 的卡片標題旁新增 `data-master-search` 搜尋框，CSS (`admin.css`) 提供 `.master-search__input` 樣式。  
   - `assets/js/master.js` 會即時以 `searchKeys` 過濾表格：客戶/員工比對 `code/name`、車輛比對 `code/license`、科目比對 `name/mapping`。  
   - 若仍看不到搜尋欄，可直接 `curl http://localhost:8888/accounting/master/ | grep data-master-search` 確認伺服器輸出是否為新版，再清除瀏覽器快取。

---

## 🔄 目前任務 — 必讀 (2 min)
1. **串接 API**：設計各項費用、收支表、車輛成本的 API 端點與欄位 mapping，並完成 JS fetch/渲染。  
2. **驗證搜尋欄**：再次檢查實際伺服器輸出的 HTML 與 CSS，確保所有瀏覽器都能顯示搜尋框。  
3. **定義車輛成本資料來源**：與會計確認每個車號的資料來源（成本/收入來源、跨月規則），並更新 JS / API。  
4. **回顧營收匯入**：若需接手匯入工作，請參考 `handoff/2025-11-13.md` 內的詳細紀錄。

---

## 📚 參考資料 — 選讀 (需背景)
- `handoff/2025-11-13.md`：先前營收匯入、票據對帳、匯款帳號的完整紀錄。  
- `public_html/accounting/api/sales/*.php`：若需處理營收匯入相關議題，可由此了解 API 邏輯。  
- `assets/js/sales-*.js`：與營收頁面、票據、匯款帳號前端邏輯有關的腳本。

---

## 🗂 過往紀錄 — 選讀
- 更早的紀錄仍保存在 `handoff/2025-11-12.md`、`2025-11-11.md`、`2025-11-09.md` 等檔案，可依需要查閱。
