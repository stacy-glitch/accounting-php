# AGENT HANDOFF

## TL;DR（若只想快速接手，先讀這段即可）
- 今天：靠行表改為三列表頭＋內嵌下拉、限制司機為李正源/蕭添丁/八達/張逢升/阮明昭並自動帶車號，新增上傳.xlsx/新增明細按鈕；薪資自動帶值 API 調整；車輛匯入允許缺行照/駕照且自動補牌照。
- 目前卡住：靠行表上傳 XLSX 僅提示未解析、司機總匯尚未實際資料驗證、部分舊車輛資料仍缺 license 需驗證。
- 明日優先：完成靠行 XLSX 解析＋存檔、驗證司機/車輛資料並更新 handoff、整理薪資/匯入成果。
- 備註：MAMP Document Root = `/Users/pei-yinglin/Projects/judacargo/accounting-php/public_html/accounting`；檔案上傳成功會自動觸發匯入，僅失敗時需手動重送。

---

## 📌 最新交接 — 必讀 (3 min)
### 靠行表（`public_html/accounting/payroll/affiliates.php`）
- 表頭僅三行：第 1 行司機選單、第 2 行年份/雙月選單、第 3 行車號；列印時只輸出文字。
- 限制司機名單為【李正源、蕭添丁、八達、張逢升、阮明昭】，自動從 `master_vehicles` 取車號。JS：`assets/js/payroll-affiliates.js`。
- 右下角新增「📁 上傳.xlsx」（目前僅提示，尚未解析）、「新增明細」、「下載 PDF」。

### 薪資／自動帶值
- `payroll/index.php` + `assets/js/payroll-index.js`：已接 `api/payroll/payroll_autofill.php`，切換年月/員工時自動拉運費、中油、勞健保、借支、固定補助。表格擴充為 16 列。
- `api/payroll/payroll_autofill.php`：整合司機總匯、中油、勞健保、健保、零用金、二信等來源並回傳欄位定義。

### 車輛匯入
- `api/master-data/import_upload.php`：車輛匯入僅要求「代號／車牌號碼／車型／廠牌／司機」，行照/駕照改為選填；若未提供行照則用車牌填入 `license`，並同步補齊舊資料，確保資料維護頁顯示車牌號碼。

---

## 🔄 目前任務 — 必讀 (2 min)
1. **靠行 XLSX 解析**：設計上傳的檔案格式、解析後自動填入表格並能儲存為明細或寫入 DB。
2. **司機/車輛資料驗證**：重新匯入最新車輛表確認 `license`/`plate` 正確；靠行表五位司機需人工驗證車號。
3. **司機總匯 / 薪資驗證**：以實際資料跑一次司機金額總匯及薪資自動帶值，確認 API 對應與金額正確，再更新 handoff/紀錄。

---

## 🔜 明日優先事項
1. 串接靠行上傳 `.xlsx` → 解析為表格/明細資料並支援儲存。
2. 檢查 `master_vehicles` 匯入後的車牌欄位，若仍有空值再調整匯入規則或資料來源。
3. 司機金額總匯與薪資表重新驗證 114/09 或最新月份資料，將結果寫入 `handoff/2025-11-10.md`。

---

## 📚 參考資料 — 選讀
- `handoff/2025-11-10.md`：今日靠行表/薪資/健保調整詳述。
- `handoff/2025-11-09.md`：司機金額總匯與健保欄位改版背景。
- 更早背景：`handoff/2025-11-08.md`、`handoff/2025-11-07.md`。
- 程式索引：
  - 靠行表：`payroll/affiliates.php`, `assets/js/payroll-affiliates.js`。
  - 薪資：`payroll/index.php`, `assets/js/payroll-index.js`, `api/payroll/payroll_autofill.php`。
  - 車輛匯入：`api/master-data/import_upload.php`, `public_html/accounting/master/?tab=vehicles`。

---

## 🗂 過往紀錄 — 選讀
- `handoff/2025-11-01.md` 以前：僅在需要追溯零用金/營收等歷史背景時使用。
