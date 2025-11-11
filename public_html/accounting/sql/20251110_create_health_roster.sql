CREATE TABLE IF NOT EXISTS health_roster_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  roc_year INT NOT NULL,
  month TINYINT NOT NULL,
  insurance_fee INT DEFAULT 0,
  dependent_name VARCHAR(100) NOT NULL,
  id_number VARCHAR(50) DEFAULT '',
  birth VARCHAR(20) DEFAULT '',
  identity_type VARCHAR(50) DEFAULT '',
  change_type VARCHAR(50) DEFAULT '',
  billing_note VARCHAR(255) DEFAULT '',
  self_payment INT DEFAULT 0,
  company_payment INT DEFAULT 0,
  self_total INT DEFAULT 0,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_health_roster_period (roc_year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
