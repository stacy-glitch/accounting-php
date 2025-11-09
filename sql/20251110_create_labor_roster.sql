CREATE TABLE IF NOT EXISTS labor_roster_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  roc_year INT NOT NULL,
  month TINYINT NOT NULL,
  employee_code VARCHAR(50) DEFAULT '',
  employee_name VARCHAR(100) NOT NULL,
  birth VARCHAR(20) DEFAULT '',
  labor_salary INT DEFAULT 0,
  health_salary INT DEFAULT 0,
  change_type VARCHAR(50) DEFAULT '',
  change_date VARCHAR(20) DEFAULT '',
  personal_share INT DEFAULT 0,
  company_share INT DEFAULT 0,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_labor_roster_period (roc_year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
