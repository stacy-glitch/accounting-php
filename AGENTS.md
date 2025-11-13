# AGENT HANDOFF

## TL;DR — 若只想快速接手先讀此段
- 今日：調整營收匯入流程（欄位映射、NULL 欄位、表頭偵測）、實收欄前端邏輯、匯款帳號上傳 API、票據↔基隆二信對帳、營收表單標題同步顯示月份。
- 卡住：目前 Excel 表頭仍被判定缺少必要欄位；尚未取得實際範本，匯入仍無法完成；舊資料的實收/備註仍須 SQL 清空。
- 明日優先：1) 取得/解析實際匯入檔並修正 `detect_header_info()` 2) 針對既有月份執行 SQL 清空實收欄 3) 驗證基隆二信對帳欄是否完全正確。
- 快速入口：`public_html/accounting/api/sales/upload.php`、`assets/js/sales-{index,klsb,remittance}.js`、`api/sales/notes_archive.php`、`api/sales/remittance_*`
- 環境提醒：MAMP Document Root=`/Users/pei-yinglin/Projects/judacargo/accounting-php/public_html/accounting`；檔案上傳成功會自動匯入，只有失敗時才需手動觸發。

---

## 📌 最新交接 — 必讀 (3 min)
1. **營收匯入（進行中）**  
   - `api/sales/upload.php` 會讀表頭並對應欄位，實收/收款日期/收款方式/備註匯入時強制寫成 NULL/空字串；合計欄若為 0 會 fallback 使用發票金額。  
   - 目前仍抓不到實際報表表頭，前端持續顯示「找不到表頭」，需要實際檔案讓 `detect_header_info()` 能識別僅有「客戶/運費/稅金/代墊支出/合計」的格式。  
   - 若要清空舊資料，需在 MySQL 執行 `UPDATE sales_revenue SET actual_received = NULL, received_date = '', received_method = '', note = '' WHERE year = ? AND month = ?;`。

2. **匯款帳號上傳（前端完成）**  
   - `api/sales/remittance_upload.php` + `_remittance_parser.php` 解析 `.xlsx/.csv`；`remittance_latest.php` 提供最新快照。  
   - `sales-remittance.js` 使用綠色「上傳 .xlsx」按鈕匯入並顯示客戶/帳戶/銀行/備註；尚未寫入資料庫，僅供前端匯入與檢查。

3. **應收票據 ↔ 基隆二信對帳**  
   - `api/sales/notes_archive.php` 彙整歷史票據：票號後五碼 → 帳款月份。  
   - `sales-klsb.js` 載入 archive 後，自動將「管收他票」紀錄套上對應月份並顯示在「對帳」欄；需人工抽查以確保無遺漏。

4. **新增營收表單標題**  
   - `sales-index.js` 會同步更新 `data-sales-month-title="create"`，顯示 `114年10月新增營收紀錄`。若仍只見「114年新增…」，請強制重整或確認 JS 是否載入最新版本。

詳細流程與指令請見 `handoff/2025-11-13.md`。

---

## 🔄 目前任務 — 必讀 (2 min)
1. **修復營收匯入**：取得實際 Excel/CSV，調整 `detect_header_info()` & `build_header_mapping()` 讓報表表頭能被辨識，重新測試匯入。  
2. **清空舊資料並重新匯入**：針對已匯入的月份執行 SQL 將 `actual_received/received_date/received_method/note` 改為空值，避免舊資料顯示 0。  
3. **驗證基隆二信對帳欄**：確認所有「管收他票」列都顯示正確月份，記錄任何例外格式以便調整。  
4. **匯款帳號後續**：決定是否將上傳結果寫入資料庫或提供下載，避免只有前端快照。

---

## 📚 參考資料 — 選讀 (需背景)
- `handoff/2025-11-13.md`：今日所有變更與測試記錄。  
- `public_html/accounting/api/sales/upload.php`：匯入流程、欄位映射、表頭偵測、NULL 處理。  
- `public_html/accounting/api/sales/notes_archive.php` + `assets/js/sales-klsb.js`：票據對帳邏輯。  
- `public_html/accounting/api/sales/remittance_*.php` + `assets/js/sales-remittance.js`：匯款帳號上傳與呈現。  
- `public_html/accounting/assets/js/sales-index.js`：營收頁面主要邏輯（匯入、編輯、標題、實收欄位）。

---

## 🗂 過往紀錄 — 選讀
- 2025-11-12 前的歷史請見 `handoff/2025-11-12.md`、`2025-11-11.md`、`2025-11-09.md`、`2025-11-08.md` 等檔案，需要更早脈絡時再查閱。

