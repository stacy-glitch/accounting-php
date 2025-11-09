# AGENT HANDOFF

## TL;DR（若只想快速接手，先讀這段即可）
- 勞保/健保/中油名冊均改為「上傳→解析→存 DB」，前端改讀 API，資料不再暫存瀏覽器。（詳見 `handoff/2025-11-10.md`）
- 上傳 API 需 `pdftotext`（macOS 路徑 `/opt/homebrew/bin/pdftotext`），若部署環境不同請調整。
- 需先在 DB 執行三份 SQL：`create_labor_roster.sql`、`create_health_roster.sql`、`create_cpc_records.sql`。
- 中油 CSV 只讀系統欄位（車牌、司機、日期、油站、金額、備註），缺少車牌的列會自動忽略。
- 明日優先：執行 SQL＋上傳實際檔驗證；評估其他暫存模組是否也要 API 化。

---

## 📌 最新交接 — 必讀 (3 min)
### 名冊（必讀）
- 勞保／健保頁面都以 API 讀寫資料庫，支援 PDF（`pdftotext`）與 CSV/XLSX 上傳、列表編輯/刪除。API：`labor_records.php` / `labor_upload.php`、`health_records.php` / `health_upload.php`。
- 上傳流程：刪除同年月舊資料→解析檔案→寫入資料表→回傳新資料；頁面重新整理即會讀到最新資料。
- 需在 DB 執行 `sql/20251110_create_labor_roster.sql`、`sql/20251110_create_health_roster.sql` 後才有表。

### 中油 CSV（必讀）
- 新增 `cpc_records.php` / `cpc_upload.php` + `sql/20251110_create_cpc_records.sql`。UI 與名冊一致，只顯示車牌～備註欄。
- 上傳 CSV 只讀系統欄位，可忽略其它欄；同車牌會依日期排序顯示。缺少車牌的列、或沒有任何有效列會顯示「檔案中沒有可匯入的資料列」。

### 參考
- 更細節（API 結構、風險）請見 `handoff/2025-11-10.md`（必讀 5 min）。
- 11/08 前薪資列印/模板調整可參考 `handoff/2025-11-09.md`、`.../2025-11-08.md`（選讀）。

---

## 🔄 目前任務 — 必讀 (2 min)
- **資料庫初始化**：將三份 SQL 匯入正式 DB；若環境不同需更新 `api/config.php` 及 `pdftotext` 路徑。
- **上傳驗證**：用實際勞保/健保 PDF、中油 CSV 走一次整條流程，確認欄位 mapping 與排序符合期待。
- **下一波 API 化**：盤點仍在前端暫存的模組（如薪資模板），決定是否沿用同樣的 API/DB 模式。

---

## 🔜 明日優先事項
1. 在正式環境執行 `sql/20251110_create_*.sql`，確認 API 已能連線並寫入。
2. 上傳實際名冊檔案，檢查欄位解析（特別是中油 CSV 的時間／金額欄）是否符合需求。
3. 開始規畫「薪資模板／列印設定」的資料表與 API，延伸現在的名冊架構。

---

## 📚 參考資料 — 選讀
- `handoff/2025-11-10.md` — 今日名冊/中油改動詳述（讀完即可進行排障）  
- `handoff/2025-11-09.md`, `.../11-08.md` — 早期薪資列印 / 模板 note  
- API/樣式索引：  
  - 勞保：`api/payroll/labor_records.php`, `labor_upload.php`, `assets/js/payroll-labor.js`  
  - 健保：`api/payroll/health_records.php`, `health_upload.php`, `assets/js/payroll-health.js`  
  - 中油：`api/payroll/cpc_records.php`, `cpc_upload.php`, `assets/js/payroll-cpc.js`

---

## 🗂 過往紀錄 — 選讀
- `handoff/2025-11-01.md`：較早期交接。
- `handoff/2025-10-31.md`：零用金與 ROC 日曆回退細節。
