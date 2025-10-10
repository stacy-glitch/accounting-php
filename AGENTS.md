# AGENTS.md — JudaCargo Accounting（給下一位 AI）

## 1) 目的（Priority）
- **首要任務：在本地建立與 cPanel 相同的環境，先在本機跑通系統，再上傳到 cPanel 的 `/public_html/accounting/`。**
- 流程：本地環境 → 匯入資料表 → 設定 API 連線 → 本地驗證 → 上傳主機 → 主機驗證。
- 安全：`config.php` 與真實資料不進公開 repo；以 `config.sample.php → config.php` 並用 `.gitignore` 排除。

## 2) 會用到的工具（Tools & Interfaces）
- macOS 終端機（zsh）
- Git / GitHub（Public: `accounting-php`；Private: `judacargo-accounting` 用於備份）
- 編輯器：VS Code 或 nano
- MAMP（Apache + PHP + MySQL）與 PHP 擴充：mysqli、pdo_mysql、mbstring、curl、json
- phpMyAdmin（本地與主機）
- MySQL Client：mysql、mysqldump
- cPanel：File Manager、MySQL® Databases、phpMyAdmin
- 檔案傳輸：cPanel File Manager / FTP(S)/SFTP / Git（若主機支援）
- 可選：GitHub CLI（gh）、macOS launchd、GitHub Actions

## 3) 需要自動化的步驟（Automation）
A. 每日資料備份（私有）
- 用 `mysqldump` 匯出本地 DB，保留最近 7 份，推到 `judacargo-accounting`（Private）。
- 腳本：`scripts/backup_db.sh`；頻率：每日一次。

B. 每日開發提交
- 程式碼 + `sql/migrations/*` + `sql/seed/*` 提交到 `accounting-php`（Public）。
- 可加提醒或用 launchd 觸發。

C. 快速建立 migration
- 腳本：`scripts/new_migration.sh` 產生 `sql/migrations/YYYYMMDD_description.sql`。

D. 安全防呆（可選）
- pre-commit 檢查是否誤把 `public_html/accounting/api/config.php` 加入 staged。

## 4) 作業步驟（Workplan）
1. 安裝 MAMP；確認 MySQL 埠（3306 或 8889）。
2. 取得原始碼：`git clone https://github.com/stacy-glitch/accounting-php.git && cd accounting-php`
   並加入 `.gitignore` 排除：`public_html/accounting/api/config.php`、`backups/**`、`*.sql`
   但保留：`sql/master_tables.sql`、`sql/seed/**`、`sql/migrations/**`
3. 建本地 DB：新建 `judacargo_local`（utf8mb4），匯入 `sql/master_tables.sql`
4. 設定 API：`cp public_html/accounting/api/config.sample.php public_html/accounting/api/config.php` 並填入本地 DB 參數
5. 本地啟動與驗證：用 MAMP 指向 `public_html/accounting/`；測首頁與任一 `api/*`
6. 每日提交：程式碼 + migrations/seed → `git add/commit/push`
7. 備份（如需）：執行 `scripts/backup_db.sh` 推到私有 repo
8. 部署到 cPanel：建主機 DB → 匯入 schema/migrations/seed → 上傳檔案 → 設定主機 `api/config.php` → 驗證
