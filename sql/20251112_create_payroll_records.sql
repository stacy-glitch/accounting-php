CREATE TABLE IF NOT EXISTS `payroll_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `roc_year` SMALLINT NOT NULL,
  `month` TINYINT NOT NULL,
  `employee_code` VARCHAR(50) NOT NULL,
  `employee_name` VARCHAR(100) NOT NULL DEFAULT '',
  `expenses_json` JSON NOT NULL,
  `incomes_json` JSON NOT NULL,
  `note` TEXT,
  `expense_total` INT NOT NULL DEFAULT 0,
  `income_total` INT NOT NULL DEFAULT 0,
  `net_total` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payroll_employee_period` (`roc_year`, `month`, `employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
