# JudaCargo Accounting — Handoff

## TL;DR（若只想快速接手，先讀這段即可）
1. 應收票據頁面已可重新載入上傳資料、編輯列版面調整完成，總計改為「逐筆累積」。
2. 主檔資料頁面移除「新增」與「待匯入」面板，僅保留表格右上角的「📤 上傳舊檔」，上傳後立即刷新列表。
3. 仍未將票據編輯／刪除結果寫回後端，也尚未串接代墊款銷帳資料。
4. 明日優先：規劃票據 CRUD API、決定銷帳資料模型、確認主檔匯入是否需回寫或同步其他欄位。
5. 若需完整背景（設計、環境與過往紀錄），請往下讀取「最新交接」、「目前任務」、「參考資料」等章節。

---

## 最新交接（必讀 ≈3 min）

### 今日更新
- **應收票據頁面**
  - 匯入資料會在重新整理後自動從 `latest.json` 讀回，並依入帳日排序。
  - 操作欄新增「編輯／刪除」，暫為前端狀態；編輯列套用與零用金相同的輸入框樣式，並在 `assets/css/admin.css` 加入 `notes-edit-field` 欄位寬度設定。
  - `total` 欄改為逐筆累加（第一筆=金額，第二筆=前筆總計+當前金額…）；上傳或編輯時不需人工輸入總計。

- **資料維護（主檔）**
  - 移除卡片右上方的「＋ 新增」與「待匯入舊檔」面板，避免雙軌作業。
  - 表格上方新增 `table-toolbar`，僅保留「📤 上傳舊檔」按鈕；`assets/js/master.js` 也刪除了 pending/匯入/刪除相關程式。
  - 上傳成功後直接重載目前的主檔列表（`loadActiveTab({ force: true })`），不再顯示待匯入清單。

### 明日優先事項
1. **票據 CRUD API**：目前編輯／刪除僅存在前端，需設計 `notes_create/update/delete` 與資料表（可沿用 `sales_notes` 草案或另建 `notes` 表）。
2. **代墊款銷帳資料模型**：定義代碼如何對應至營收或票據，是否需要 `receipts` / `advance_links` 之類的關聯表。
3. **主檔匯入檢查**：確認是否要支援「匯入即覆蓋」或「匯入前預檢」，若需要請補上差異比較或備份機制。

---

## 目前任務與狀態（選讀）

### 應收票據
- 上傳：`public_html/accounting/api/sales/notes_upload.php`，解析 CSV/XLSX、儲存原檔並產生 `latest.json`。
- 下載：`api/sales/notes_download.php`，可依 `year/month` 拿最新舊檔。
- 前端：`sales/notes.php` + `assets/js/sales-notes.js`；現在可載入最新資料、編輯行內內容，但尚未寫回後端。

### 主檔資料（資料維護）
- 目前僅保留「上傳舊檔」按鈕；上傳 API 仍使用 `api/master-data/upload.php`。
- 「待匯入」相關功能及 `pending.php/import_upload.php/delete_upload.php` 仍存在，但前端已不再呼叫，可視需求日後清理。

---

## 參考資料（選讀）
- **環境資訊**：macOS + zsh；MySQL `127.0.0.1:8889`（root/root）；資料庫 `judacargo_local`。  
  開發伺服器範例：`/Applications/MAMP/bin/php/php7.3.33/bin/php -S 127.0.0.1:8001 -t "$HOME/Projects/judacargo/accounting-php/public_html/accounting"`。
- **敏感檔案**：`public_html/accounting/api/config.php`、各種 CSV/XLSX/SQL dump，禁止推到公開 repo。
- **例行流程**：  
  1. `./scripts/backup_db.sh` → 私有備份。  
  2. `git status && git push`。  
  3. 視需求 `./scripts/deploy.sh`（需 `CPANEL_*`）。  
- **詳細歷史與備註**：請參考 `handoff/` 目錄，例如 `handoff/2025-11-01.md`（仍保留過去交接內容）。

---

## 過往紀錄（選讀）
- 2025-10-22：完成主檔匯入第一版與側邊導覽（見 `handoff/2025-10-22.md`）。
- 2025-10-30：零用金日曆回退至穩定版（build tag 20251112）。
- 2025-10-31：恢復 ROC 客製日曆並修復代墊欄位。  
- 更舊的資訊請參閱 `handoff/` 內按日期區分的檔案。***
