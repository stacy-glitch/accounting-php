# JudaCargo Accounting — MySQL 主檔資料設定指南

## 1. 建立資料表
1. 登入 cPanel → **phpMyAdmin**。
2. 選擇資料庫 `judacarg_web`（假設沿用既有設定）。
3. 切到「SQL」頁籤，貼上 `sql/master_tables.sql` 內容執行：

```sql
-- 也可直接載入 /public_html/accounting/sql/master_tables.sql
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  tax_id VARCHAR(50) DEFAULT '',
  contact VARCHAR(100) DEFAULT '',
  phone VARCHAR(50) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  plate VARCHAR(50) DEFAULT '',
  model VARCHAR(100) DEFAULT '',
  brand VARCHAR(100) DEFAULT '',
  driver VARCHAR(100) DEFAULT '',
  license VARCHAR(100) DEFAULT '',
  permit VARCHAR(100) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(50) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_mappings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mapping VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_mapping_name (mapping, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 2. 設定資料庫帳密
1. 在 cPanel → MySQL® Databases 查詢 `judacarg` 使用者對 `judacarg_web` 的權限；如無，新增對應權限。
2. 開啟 `/public_html/accounting/api/db.php`：
   - 將 `DB_PASS` 換成實際資料庫密碼。
   - 若資料庫名稱或使用者不同，請同步調整 `DB_NAME`、`DB_USER`。

## 3. 測試 API
1. 造訪 `https://judacargo.com/accounting/api/master_customers.php`（預期得到 JSON 陣列）。
2. 透過 curl 或 Postman 使用 `POST` 上傳 JSON/CSV：
   - JSON：`[{"code":"C001","name":"客戶 A","tax_id":"123","contact":"王小明","phone":"0912-345678"}]`
   - CSV：在前端頁面（完成串接後）選擇檔案送出。

只要這三步完成，主檔資料就能正常寫入 MySQL，前端也可讀取最新內容。
