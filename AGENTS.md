# AGENT HANDOFF

## TL;DR（若只想快速接手，先讀這段即可）
- 今天：完成中油統計卡＋列印明細（直式 A4、可自動換頁），上傳改為 `.xlsx` 並覆蓋舊月份；健保名冊改為固定欄位並重寫 Excel/PDF 解析。 
- 目前卡住：健保 PDF 仍會把「自付／單位負擔／合計」讀錯，畫面筆數與 PDF 未完全一致。 
- 明日優先：完成健保 PDF 解析＋重新上傳 9 月資料驗證，必要時加上總計/匯出以利核帳。 
- 關鍵檔案：`payroll/cpc.php`, `assets/js/payroll-cpc.js`, `api/payroll/cpc_upload.php`, `api/payroll/cpc_summary.php`; 健保對應 `payroll/health.php`, `assets/js/payroll-health.js`, `api/payroll/health_upload.php`, `api/payroll/health_records.php`。 
- 詳細紀錄：`handoff/2025-11-09.md`（今日變更）、`handoff/2025-11-08.md`（昨日參考）。

---

## 📌 最新交接 — 必讀 (3 min)
### 中油表（已完成）
- 統計卡移到頁面頂端，表頭與明細卡一致（含「上月／下月」切換）。
- 「列印明細」會依車號/司機分組輸出、直式 A4、內含總計列，列印視窗載入後自動觸發列印。
- 上傳僅允許 `.xlsx`；同月檔案會先刪除 `cpc_records`/`cpc_summary` 再寫入，避免重複。API：`payroll/cpc_summary.php`（新）、`cpc_upload.php`、`assets/js/payroll-cpc.js`、`assets/css/admin.css`、`payroll/cpc.php`。

### 健保名冊（進行中）
- UI 只顯示「保險費／姓名／身分證號／出生日期／計費註記／自付／單位負擔／自付保費合計／備註」。
- 後端已改寫欄位 mapping（包含多種別名）並在 Excel/PDF 解析時自動計算 `保險費 = 自付 + 單位負擔`；但 PDF 仍會把金額拆錯（例：自付=2、單位負擔=540），需再針對純文字格式調整。
- 相關檔案：`payroll/health.php`、`assets/js/payroll-health.js`、`api/payroll/health_upload.php`、`api/payroll/health_records.php`。

### 其他（選讀）
- 薪資模板／列印多選 API 化等舊任務暫擱置，歷史紀錄見 `handoff/2025-11-09.md` 以前的檔案。

---

## 🔄 目前任務 — 必讀 (2 min)
1. **健保 PDF 解析**：完成「計費註記 + 自付 + 單位負擔 + 合計」欄位的拆解邏輯，確保不同欄位數的行都能正確解析。
2. **健保資料驗證**：用 114/09 原始檔重新上傳，逐筆比對金額與筆數；必要時新增合計列及匯出功能。
3. **中油維運**：若需匯出或 API 擴充，可沿用 `cpc_summary.php`；目前功能已可使用。

---

## 🔜 明日優先事項
1. 針對健保 PDF 純文字格式撰寫更嚴謹的 parser，修正自付／單位負擔／合計欄位讀值錯誤。
2. 重新匯入 9 月健保資料並與 PDF 對照，確認筆數、金額與計費註記一致；更新結果於 `handoff/2025-11-10.md`。
3. 若時間允許，新增健保頁的合計顯示或匯出按鈕，方便人工核對。

---

## 📚 參考資料 — 選讀
- `handoff/2025-11-09.md` — 今日詳細紀錄（中油統計、健保欄位調整）。
- `handoff/2025-11-08.md` — 薪資模板／列印歷史背景。
- `handoff/2025-11-07.md` — 零用金、營收報表舊待辦。
- 開發索引：
  - 中油：`public_html/accounting/payroll/cpc.php`、`assets/js/payroll-cpc.js`、`api/payroll/cpc_upload.php`、`api/payroll/cpc_summary.php`。
  - 健保：`public_html/accounting/payroll/health.php`、`assets/js/payroll-health.js`、`api/payroll/health_upload.php`、`api/payroll/health_records.php`。

---

## 🗂 過往紀錄 — 選讀
- `handoff/2025-11-01.md`、`handoff/2025-10-31.md`：更早期任務紀錄，僅在需要追溯背景時參考。
