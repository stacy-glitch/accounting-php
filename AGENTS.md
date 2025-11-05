# JudaCargo Accounting — Handoff

## TL;DR（必讀 ≤ 5 行）
- 今日完成營收報表「新增營收資料」卡片、客戶清單、自動建檔 API，並調整匯入邏輯共用正規化工具。
- 代墊款匯入方向已討論，但尚未串接到營收銷賬；仍需設計收款／銷賬對應表。
- 若只想快速接手，先讀本段與「今日更新」、「明日優先事項」即可，其餘章節標註為選讀。

---

## 今日更新（必讀 3 min）
- **營收報表新增卡片**（`public_html/accounting/sales/index.php`, `assets/css/admin.css`）  
  新增置中表頭 + 上／下月按鈕，客戶欄改文字輸入＋ datalist，撤除客戶名稱顯示。
- **營收前端整合**（`assets/js/sales-index.js`）  
  載入客戶名單、送出新增表單、重構月份標題、限制合計列只顯示運費/合計/實收，所有提示改記錄於 console。
- **後端共用正規化**（`api/sales/_revenue.php`, `upload.php`, `revenue_update.php`）  
  把金額與日期解析抽成共用函式，匯入與更新都走同一套處理。
- **單筆營收新增 API**（`api/sales/revenue_create.php`）  
  允許前端建立單筆營收資料並回傳完整紀錄。

---

## 明日優先事項（必讀）
1. 規劃並實作「代墊款 ↔ 營收」銷賬資料模型（建議 receipts + receipt_items / advance_settlement_links）。
2. 設計自動同步流程：代墊款勾「營收報表」後，營收匯入時自動整併，同客戶排序需維持連續。
3. 檢查營收報表下載/匯出是否需要同步代墊明細，若要支援請先定義輸出格式。

---

## 進行中任務與設計參考（選讀）

### 營收報表上傳流程
- CSV/XLSX 檔上傳至 `public_html/accounting/api/sales/upload.php`，將同月份舊資料刪除後重新寫入 `sales_revenue`。  
- `sales_revenue` 欄位（建議防呆）：`year`,`month`,`customer`,`customer_name`,`freight`,`invoice_amount`,`tax`,`warehouse_fee`,`total`,`actual_received`,`received_date`,`received_method`,`note`,`created_at`,`updated_at`。  
- 客戶代號來源：`customers` 表；若找不到代號會回傳錯誤。  
- 原始檔存放：`public_html/accounting/uploads/sales/YYYYMM/`。下載 API 還未完成。  
- 前端檔案：`public_html/accounting/sales/index.php` + `assets/js/sales-index.js`。

### 代墊款與銷賬（構想）
- 目前代墊款支出尚未與營收銷賬串接；建議新增銷賬對應表，或在代墊明細存 `link_target`, `link_period`, `link_status`。  
- 收款同時涵蓋運費與代墊時，可透過 `receipt_items` 拆帳，再對應到 `sales_revenue` / 代墊明細。

---

## 環境＆例行事項（選讀）
- macOS + zsh，使用 MAMP；MySQL `127.0.0.1:8889`（帳密 `root/root`），資料庫 `judacargo_local`。  
- 啟動 PHP 伺服器範例：  
  `/Applications/MAMP/bin/php/php7.3.33/bin/php -S 127.0.0.1:8001 -t "$HOME/Projects/judacargo/accounting-php/public_html/accounting"`  
- 敏感檔案：`public_html/accounting/api/config.php`、任何 Excel/SQL dump 不得進公開 repo。  
- 每日例行：  
  1. `./scripts/backup_db.sh` → 私有倉 `judacargo-accounting`。  
  2. `git status && git push` ↔ `accounting-php`。  
  3. 視需求 `./scripts/deploy.sh`（需 `CPANEL_*`）。

---

## 過往紀錄（選讀）
- 2025-10-22：完成主檔上傳、多層選單、Master Data API。  
- 2025-10-30：零用金日曆回退至穩定版（build tag 20251112）。  
- 2025-10-31：恢復 ROC 客製日曆並修復代墊欄位。  
- 更多細節：`handoff/` 目錄（例如 `handoff/2025-11-01.md`）。***
