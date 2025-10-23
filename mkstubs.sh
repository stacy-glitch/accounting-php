#!/usr/bin/env bash
set -euo pipefail

BASE="public_html/accounting/api"

create_stub() {
  local rel="$1"
  local dir="$BASE/$(dirname "$rel")"
  local file="$BASE/$rel"
  mkdir -p "$dir"
  if [ -f "$file" ]; then
    echo "skip  $rel (already exists)"
    return 0
  fi
  cat > "$file" <<PHP
<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'       => true,
  'endpoint' => '$rel',
  'ts'       => date('c'),
], JSON_UNESCAPED_UNICODE);
PHP
  echo "create $rel"
}

# 1) petty-cash（零用金）
create_stub "petty-cash/ping.php"
create_stub "petty-cash/vouchers_list.php"
create_stub "petty-cash/voucher_create.php"
create_stub "petty-cash/voucher_update.php"
create_stub "petty-cash/voucher_delete.php"

# 2) sales（營業收入）
create_stub "sales/ping.php"
create_stub "sales/invoices_list.php"
create_stub "sales/invoice_create.php"
create_stub "sales/receipts_list.php"
create_stub "sales/revenue_summary.php"

# 3) payroll（薪資）
create_stub "payroll/ping.php"
create_stub "payroll/payroll_list.php"
create_stub "payroll/payroll_generate.php"
create_stub "payroll/payroll_item_update.php"

# 4) expenses（各項費用）
create_stub "expenses/ping.php"
create_stub "expenses/expenses_list.php"
create_stub "expenses/expense_create.php"
create_stub "expenses/expense_update.php"
create_stub "expenses/expense_delete.php"

# 5) cashflow（收支）
create_stub "cashflow/ping.php"
create_stub "cashflow/cashin_list.php"
create_stub "cashflow/cashout_list.php"
create_stub "cashflow/statement.php"

# 6) vehicle-costs（車輛成本）
create_stub "vehicle-costs/ping.php"
create_stub "vehicle-costs/fuel_records_list.php"
create_stub "vehicle-costs/maintenance_list.php"
create_stub "vehicle-costs/vehicle_cost_summary.php"

# 7) master-data（資料維護）
create_stub "master-data/ping.php"
create_stub "master-data/master_customers.php"
create_stub "master-data/master_employees.php"
create_stub "master-data/master_vehicles.php"
create_stub "master-data/account_mappings.php"

echo "✅ all stubs created under $BASE"
