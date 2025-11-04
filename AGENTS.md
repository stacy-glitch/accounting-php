# JudaCargo Accounting — Handoff

## Quick TL;DR（必讀 ≤ 5 行）
- 今日完成「代墊款表」功能：新增搜尋卡片、月份列表，以及後端 `/api/petty-cash/advances.php` 計算 FIFO 未銷金額。
- 所有代墊款 UI（`public_html/accounting/petty-cash/advances.php` + `assets/js/petty-advances.js`）已對齊零用金頁的操作體驗，代號顯示會自動去除 `.0`。
- 現在的工作重點：驗證代墊款 API/頁面資料與零用金表一致，評估是否要串接銷帳寫回流程。
- 若只需接手代墊款模組，可先讀「🧭 Current Focus」與「⚙️ Environment」，其他章節標示為選讀。

---

## 🧭 Current Focus（必讀）
- **今天完成**
  - 新增 `public_html/accounting/api/petty-cash/advances.php`，可依代號或年月回傳 FIFO 未銷明細與總額。
  - 建立 `public_html/accounting/petty-cash/advances.php` 與 `assets/js/petty-advances.js`，支援代號搜尋、民國年月表頭、上下月切換與 ROC 格式顯示。
  - 調整 `assets/css/admin.css` 讓搜尋欄、按鈕與月份導航風格統一為綠色按鈕。
- **明日建議**
  1. 比對代號搜尋／月度列表與零用金表的資料是否一致（特別是 FIFO 邏輯），必要時加上簡單測試樣本。
  2. 決定是否要在代墊款表加入銷帳操作（將勾選的項目回寫零用金或償還表）。
  3. 檢查 RWD 與 cPanel 部署（若要上線，記得更新 build 版本參數如 `petty-advances.js?v=20251215`）。
- **追蹤檔案**
  - `public_html/accounting/petty-cash/advances.php`
  - `public_html/accounting/assets/js/petty-advances.js`
  - `public_html/accounting/api/petty-cash/advances.php`
  - `public_html/accounting/assets/css/admin.css`

---

## ⚙️ Environment Snapshot（必讀）
- macOS + zsh，使用 MAMP。MySQL：`127.0.0.1:8889`，帳密 `root/root`，資料庫 `judacargo_local`。
- PHP 測試伺服器範例  
  `/Applications/MAMP/bin/php/php7.3.33/bin/php -S 127.0.0.1:8001 -t "$HOME/Projects/judacargo/accounting-php/public_html/accounting"`
- 敏感檔案不得進公開 repo：`public_html/accounting/api/config.php`、任何 Excel/SQL dump。
- 備份腳本：`scripts/backup_db.sh`（自動偵測 mysqldump，失敗時記得 `export PATH="$(brew --prefix)/opt/mysql-client/bin:/Applications/MAMP/Library/bin:$PATH"`）。

---

## 🔁 Daily Must-do（必讀）
1. `./scripts/backup_db.sh` 產生 DB dump 並同步至私有倉 `judacargo-accounting`。
2. `git status && git push` 確保公開倉 `accounting-php` 無遺漏更動。
3. 視需要執行 `./scripts/deploy.sh`（需事先設定 `CPANEL_*` 參數）上傳至 cPanel。

---

## 📚 Reference（選讀）

### Workplan（既有流程）
1. 安裝 MAMP，確認 MySQL 埠（3306 / 8889）。
2. 取得原始碼：`git clone https://github.com/stacy-glitch/accounting-php.git && cd accounting-php`  
   `.gitignore` 需排除 `public_html/accounting/api/config.php`、`backups/**`、`*.sql`，保留 `sql/master_tables.sql`、`sql/seed/**`、`sql/migrations/**`。
3. 建立 `judacargo_local`（utf8mb4），匯入 `sql/master_tables.sql`。
4. 複製 `config.sample.php` → `config.php` 並填入本地 DB 參數。
5. 以 MAMP 指向 `public_html/accounting/`，測試首頁與相關 API。
6. 每日提交：程式碼 + migrations/seed → `git add/commit/push`。
7. 需要時執行 `scripts/backup_db.sh`，同步至私有倉。
8. 部署 cPanel：建立主機 DB、匯入 schema/migrations/seed、上傳檔案、調整 `api/config.php`、驗證。

### Automation / Backups（選讀）
- 私有資料倉 `judacargo-accounting` 保存 Excel/CSV 舊資料與每日 DB dump，可搭配 Git LFS。
- `scripts/backup_db.sh`：匯出本地 DB → 保留最近 7 份 → 複製到 `~/Projects/judacargo/judacargo-accounting/backups/db` → commit & push。
- 可使用 macOS `launchd` 或提醒工具排程執行上述腳本。

### 變更紀錄（僅需時再讀）
- 2025-10-22：完成主檔上傳、多層選單、Master Data API 等。
- 2025-10-30：零用金日曆回退至穩定版（build tag 20251112），提醒後續升級需從乾淨版本分支開始。
- 2025-10-31：重新啟用 ROC 客製日曆並修復代墊欄位。

> 更多細節請參考 `handoff/2025-11-01.md` 與 `handoff/` 內其他交接筆記。*** End Patch
