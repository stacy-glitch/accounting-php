> 📌 給下一位 AI（交接提示）
> - 先閱讀專案根目錄 `AGENTS.md`，用 5 行內摘要：目的、工具、需要自動化。
> - 依「Workplan」一步步帶我做，提供可直接貼到 macOS zsh 的指令。
> - 本機固定值：MySQL 埠 = 8889（MAMP）、本地伺服器 = 127.0.0.1:8001。
> - 嚴禁把 `public_html/accounting/api/config.php`、Excel/SQL dump 推到 Public repo。
> - 出錯時請根據訊息提供修正指令並解釋原因。

### 本機固定值（重要）
- DB：host=127.0.0.1、port=8889、user=root、pass=root、db=judacargo_local
- 伺服器（MAMP PHP 範例）：
  /Applications/MAMP/bin/php/php8.4.1/bin/php -S 127.0.0.1:8001 -t "$HOME/Projects/judacargo/accounting-php/public_html/accounting"

### 備份腳本（已固定 8889 + 自動偵測 mysqldump）
- 位置：scripts/backup_db.sh
- 作用：mysqldump → backups/db（保留 7 份）→ 複製到「私有」repo judacargo-accounting/backups/db → commit/push
- 若找不到 mysqldump：先執行
  export PATH="$(brew --prefix)/opt/mysql-client/bin:/Applications/MAMP/Library/bin:$PATH"
---
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

---

## 11) 資料倉與備份流程（私有 repo）

### 目的
- 將 **舊有 Excel/CSV 原始資料** 與 **每日資料庫備份** 存在 **私有資料倉**，與公開程式碼分離，避免機密外流。
- 公開 repo：`accounting-php`（只放程式碼、migrations、seed）
- 私有資料倉：`judacargo-accounting`（放 Excel/CSV 舊資料與每日 DB dump）

### 私有資料倉結構（judacargo-accounting）


### LFS（建議，處理大型 Excel/CSV）
- 在 `judacargo-accounting` 執行一次：


### 備份腳本（在 accounting-php 執行）
- 腳本位置：`scripts/backup_db.sh`
- 作用：匯出本地 DB → 保留最近 7 份 → 複製到 `~/Projects/judacargo/judacargo-accounting/backups/db` → 在私有資料倉 commit & push
- 執行：

- 依需求調整變數：`DB_HOST`、`DB_PORT`（MAMP 可能 8889）、`DB_NAME`、`DB_USER`、`DB_PASS`

### 公開 repo 的 .gitignore 原則（再次強調）
- 不提交 `public_html/accounting/api/config.php`
- 不提交任何 `.sql` dump 與原始 Excel（交由私有資料倉保存）
- 保留：`sql/master_tables.sql`、`sql/migrations/*`、`sql/seed/*`

### 可選自動化（macOS）
- 用 `launchd` 每天固定時間執行 `./scripts/backup_db.sh`
- 或用行事曆/提醒事項設定每日提醒後手動執行

