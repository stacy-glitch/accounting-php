# AGENT HANDOFF

## TL;DR（若只想快速接手，先讀這段即可）
- 今天：完成司機金額總匯頁＆API、健保/勞保/薪資 UI 與匯入修正，並補上 SQL/JS/匯入流程。
- 目前卡住：`driver_summary_records` 雖已建表，但員工上傳檔還停在 `uploads/master-data/employees/pending/`，尚未走匯入 API；司機表也需實際資料驗證。
- 明日優先：確認司機總匯匯入自動化、跑完員工匯入並驗證資料、整理健保/勞保成果。
- 快速入口：依序閱讀「最新交接 → 目前任務」即可，其他段落為選讀背景。

---

## 📌 最新交接 — 必讀 (3 min)

### 司機金額總匯（新頁面）
- 路徑：`public_html/accounting/payroll/drivers-summary.php`，JS：`assets/js/payroll-drivers-summary.js`，API：`api/payroll/drivers_summary_records.php`、`drivers_summary_upload.php`，SQL：`sql/20251111_create_driver_summary_records.sql`（必須進 DB 執行）。
- 功能：沿用勞保樣式的月份切換＋上/下月按鈕；卡片右上角上傳 `.xlsx`；表格顯示「代號／司機／運費／備註／操作」，操作含綠色編輯與粉紅刪除按鈕。
- 匯入：支援完整模板或簡易「司機＋金額」雙欄；缺姓名或代號會參照 `employees` 表自動補齊。匯入流程＝上傳 → `drivers_summary_upload.php` 寫入 `driver_summary_records` → `drivers_summary_records.php` 供前端讀寫。

### 健保名冊
- PDF parser 重新切 token，保證投保金額與自付/單位負擔/合計欄位對齊；若投保金額省略會依相同金額組合或上一筆補值，但眷屬列保持空白。
- 前端新增自付/單位負擔/自付合計的總計列，備註欄顯示「本月實際應繳保險費」；保險費欄允許為空。
- 相關檔：`payroll/health.php`, `assets/js/payroll-health.js`, `api/payroll/health_upload.php`, `api/payroll/health_records.php`。

### 勞保／薪資表
- 勞保：上傳改為 PDF，表尾顯示個人負擔＋單位負擔加總（備註欄僅數字）。
- 薪資：左右各 11 列輸入格（含模板），便於輸入所有支出/收入項目。

### 資料維護匯入
- 上傳後檔案會放在 `public_html/accounting/uploads/master-data/<tab>/pending/`。若 UI 沒顯示「匯入完成」，需手動呼叫 `api/master-data/import_upload.php`（POST JSON：`{"tab":"employees","id":"<檔案ID>"}`）才能把檔案從 pending 移到 processed，並寫入資料庫。

---

## 🔄 目前任務 — 必讀 (2 min)
1. **司機總匯驗證**：確認資料庫已執行 `sql/20251111_create_driver_summary_records.sql`，並用實際 `.xlsx` 測試上傳/編輯/刪除；若 500 錯誤，多半是表尚未建立。
2. **員工匯入落地**：目前 pending 有多個檔（例如 `20251111002214_0ed40f7b.xlsx`）；請透過 `import_upload.php` 或 UI 匯入流程處理，完成後應移到 processed，並在資料維護頁看到新員工。
3. **健保／勞保／薪資資料驗證**：以 114/09 實際檔案再次比對金額與列數；若確認無誤，更新 `handoff/2025-11-10.md`。

---

## 🔜 明日優先事項
1. 自動化司機總匯匯入（考慮後端批次或後台按鈕）並補上簡易總計/匯出功能。
2. 跑完所有員工匯入並清空 pending，確認 UI 可看到最新資料。
3. 彙整健保/勞保/薪資修正成果與測試結果，補進 `handoff/2025-11-10.md`。

---

## 📚 參考資料 — 選讀
- `handoff/2025-11-10.md`：今日詳細變更（司機表、健保 parser、勞保/薪資調整）。
- `handoff/2025-11-09.md`：前一日中油統計卡與健保欄位改版。
- 更早背景：`handoff/2025-11-08.md`、`handoff/2025-11-07.md`。
- 程式索引：
  - 司機：`payroll/drivers-summary.php`, `assets/js/payroll-drivers-summary.js`, `api/payroll/drivers_summary_upload.php`, `api/payroll/drivers_summary_records.php`。
  - 健保：`payroll/health.php`, `assets/js/payroll-health.js`, `api/payroll/health_upload.php`, `api/payroll/health_records.php`。
  - 勞保：`payroll/labor.php`, `assets/js/payroll-labor.js`, `api/payroll/labor_upload.php`。
  - 薪資：`payroll/index.php`, `assets/js/payroll-index.js`。

---

## 🗂 過往紀錄 — 選讀
- `handoff/2025-11-01.md` 以前：僅在需要追溯零用金/營收等歷史背景時使用。
