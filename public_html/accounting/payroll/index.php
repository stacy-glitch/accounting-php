<?php
$payrollChildren = [
    ['label' => '薪資表', 'href' => './', 'active' => true],
    ['label' => '勞保表', 'href' => './labor.php'],
    ['label' => '健保表', 'href' => './health.php'],
    ['label' => '中油表', 'href' => './cpc.php'],
    ['label' => '司機金額總匯', 'href' => './drivers-summary.php'],
    ['label' => '靠行表', 'href' => './affiliates.php'],
];

$modules = [
    [
        'id' => 'petty-cash',
        'label' => '零用金',
        'href' => '../petty-cash/',
        'children' => [
            ['label' => '零用金表', 'href' => '../petty-cash/'],
            ['label' => '代墊款表', 'href' => '../petty-cash/advances.php'],
        ],
    ],
    [
        'id' => 'sales',
        'label' => '營業收入',
        'href' => '../sales/',
        'children' => [
            ['label' => '營收報表', 'href' => '../sales/'],
            ['label' => '應收票據', 'href' => '../sales/notes.php'],
            ['label' => '基隆二信', 'href' => '../sales/klsb.php'],
            ['label' => '兆豐銀行', 'href' => '../sales/mega-bank.php'],
            ['label' => '匯款帳號管理', 'href' => '../sales/remittance.php'],
        ],
    ],
    [
        'id' => 'payroll',
        'label' => '薪資管理',
        'href' => './',
        'active' => true,
        'open' => true,
        'children' => $payrollChildren,
    ],
    ['id' => 'cashflow', 'label' => '收支管理', 'href' => '../cashflow/'],
    ['id' => 'vehicle-costs', 'label' => '車輛成本', 'href' => '../vehicle-costs/'],
    ['id' => 'expenses', 'label' => '各項費用', 'href' => '../expenses/'],
    [
        'id' => 'master-data',
        'label' => '資料維護',
        'href' => '../master/?tab=customers',
        'children' => [
            ['label' => '客戶資料', 'href' => '../master/?tab=customers'],
            ['label' => '車輛資料', 'href' => '../master/?tab=vehicles'],
            ['label' => '員工資料', 'href' => '../master/?tab=employees'],
            ['label' => '會計科目', 'href' => '../master/?tab=accounts'],
        ],
    ],
];

$payrollEmployeeOptions = require __DIR__ . '/../config/payroll_employees.php';
if (!is_array($payrollEmployeeOptions)) {
    $payrollEmployeeOptions = [];
}
$payrollRowCount = 11;
?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>薪資表 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251110">
</head>
<body data-roc-year="<?php echo htmlspecialchars((string) (date('Y') - 1911), ENT_QUOTES, 'UTF-8'); ?>" data-month="<?php echo htmlspecialchars((string) date('n'), ENT_QUOTES, 'UTF-8'); ?>">
  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar__title">會計系統</div>
      <ul class="sidebar__nav">
        <?php foreach ($modules as $module):
          $children = !empty($module['children']) && is_array($module['children']) ? $module['children'] : [];
          $hasActiveChild = false;
          foreach ($children as $child) {
            if (!empty($child['active'])) {
              $hasActiveChild = true;
              break;
            }
          }
          $isGroupActive = !empty($module['active']) || $hasActiveChild;
          $isGroupOpen = $hasActiveChild || !empty($module['open']);
        ?>
          <li
            class="sidebar__group<?php echo $isGroupActive ? ' sidebar__group--active' : ''; ?><?php echo !empty($children) ? ' sidebar__group--has-children' : ''; ?>"
            data-sidebar-group
          >
            <?php if (!empty($children)): ?>
              <button
                type="button"
                class="sidebar__nav-item sidebar__nav-item--toggle<?php echo $isGroupActive ? ' sidebar__nav-item--active' : ''; ?>"
                data-sidebar-toggle
                aria-expanded="<?php echo $isGroupOpen ? 'true' : 'false'; ?>"
              >
                <span class="sidebar__nav-label"><?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="sidebar__nav-arrow" aria-hidden="true"></span>
              </button>
              <ul class="sidebar__subnav"<?php echo $isGroupOpen ? '' : ' hidden'; ?>>
                <?php foreach ($children as $child): ?>
                  <li>
                    <a
                      class="sidebar__subnav-item<?php echo !empty($child['active']) ? ' sidebar__subnav-item--active' : ''; ?>"
                      href="<?php echo htmlspecialchars($child['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                      <?php echo htmlspecialchars($child['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <a
                class="sidebar__nav-item<?php echo !empty($module['active']) ? ' sidebar__nav-item--active' : ''; ?>"
                href="<?php echo htmlspecialchars($module['href'], ENT_QUOTES, 'UTF-8'); ?>"
              >
                <span class="sidebar__nav-label"><?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>
    <main class="content">
      <section class="payroll-card payroll-card--live" id="payroll-sheet">
        <header class="payroll-card__header">
          <div class="payroll-company">
            <span class="payroll-company-name">足達貨運公司</span>
            <div class="payroll-period-controls">
              <select id="payroll-year" class="payroll-select" data-payroll-select="year"></select>
              <span>年</span>
              <select id="payroll-month" class="payroll-select" data-payroll-select="month"></select>
              <span>月份薪資表</span>
            </div>
            <div class="payroll-period-text" data-payroll-period-text></div>
          </div>
          <div class="payroll-employee-picker">
            <select data-payroll-select="employee" class="payroll-select payroll-select--wide"></select>
            <div class="payroll-employee-display" data-payroll-employee-display></div>
          </div>
        </header>

        <div class="payroll-table-wrapper">
          <table class="payroll-table">
            <colgroup>
              <col class="payroll-col-label">
              <col class="payroll-col-amount">
              <col class="payroll-col-label">
              <col class="payroll-col-amount">
              <col class="payroll-col-note">
            </colgroup>
            <thead>
              <tr>
                <th colspan="2">支出項目</th>
                <th colspan="2">收入項目</th>
                <th>備註</th>
              </tr>
            </thead>
            <tbody>
              <?php for ($i = 0; $i < $payrollRowCount; $i++): ?>
                <tr>
                  <td class="payroll-cell-label">
                    <input type="text" class="payroll-input" data-expense-label="<?php echo $i; ?>">
                  </td>
                  <td class="payroll-cell-amount">
                    <span class="payroll-currency">$</span>
                    <input type="number" class="payroll-input payroll-input--amount" data-expense-amount="<?php echo $i; ?>">
                  </td>
                  <td class="payroll-cell-label">
                    <input type="text" class="payroll-input" data-income-label="<?php echo $i; ?>">
                  </td>
                  <td class="payroll-cell-amount">
                    <span class="payroll-currency">$</span>
                    <input type="number" class="payroll-input payroll-input--amount" data-income-amount="<?php echo $i; ?>">
                  </td>
                  <?php if ($i === 0): ?>
                    <td class="payroll-note-cell" rowspan="<?php echo $payrollRowCount + 2; ?>">
                      <div class="payroll-note-live">
                        <textarea class="payroll-note" data-payroll-note></textarea>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endfor; ?>
              <tr class="payroll-total-row">
                <td class="payroll-total-label">合計</td>
                <td class="payroll-total-value" data-expense-total>$ 0</td>
                <td class="payroll-total-label">合計</td>
                <td class="payroll-total-value" data-income-total>$ 0</td>
              </tr>
              <tr class="payroll-net-row">
                <td class="payroll-total-label" colspan="2">收入－支出</td>
                <td class="payroll-net-total" colspan="2" data-net-amount>$ 0</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="payroll-card__footer">
          <button type="button" class="btn btn--secondary" data-payroll-action="add-detail">新增明細</button>
        </div>
      </section>

      <section class="card payroll-saved-card">
        <div class="payroll-saved-nav">
          <button type="button" class="btn btn--ghost btn--small" data-saved-action="prev-month">‹ 上月</button>
          <div class="payroll-saved-period" data-saved-period>-- 年 -- 月薪資紀錄</div>
          <button type="button" class="btn btn--ghost btn--small" data-saved-action="next-month">下月 ›</button>
        </div>
        <div class="card__body">
          <div class="payroll-saved-select">
            <label>
              <span>選擇員工預覽</span>
              <select class="payroll-select payroll-select--wide" data-saved-select>
                <option value="">-- 未選擇 --</option>
              </select>
            </label>
          </div>
          <div class="payroll-saved-preview" data-saved-preview>
            <div class="payroll-template-empty">尚未儲存薪資表</div>
          </div>
          <div class="payroll-saved-actions">
            <div class="payroll-print-picker" data-print-picker>
              <label class="payroll-print-label" for="payroll-print-picker__toggle">選擇列印員工</label>
              <button
                type="button"
                id="payroll-print-picker__toggle"
                class="payroll-print-picker__toggle"
                data-print-picker-toggle
              >
                <span data-print-picker-summary>未選擇</span>
                <span class="payroll-print-picker__arrow" aria-hidden="true"></span>
              </button>
              <div class="payroll-print-picker__dropdown" data-print-picker-dropdown hidden></div>
            </div>
            <div class="payroll-card__action-buttons">
              <button type="button" class="btn" data-payroll-print-selected>列印選取</button>
              <button type="button" class="btn btn--secondary" data-payroll-action="print">列印當前</button>
            </div>
          </div>
        </div>
      </section>

      <section class="card payroll-template-card">
        <div class="card__header">
          <div class="payroll-template-header">
            <label class="payroll-template-label">
              <span>員工代號</span>
              <select class="payroll-select payroll-select--wide" data-template-select></select>
            </label>
            <div class="payroll-template-employee" data-template-employee-display></div>
          </div>
          <button type="button" class="btn btn--secondary" data-template-action="create">新增模板</button>
        </div>
        <div class="card__body">
          <div class="payroll-template-table-wrapper">
            <table class="payroll-table payroll-table--template">
              <colgroup>
                <col class="payroll-col-label">
                <col class="payroll-col-amount">
                <col class="payroll-col-label">
                <col class="payroll-col-amount">
                <col class="payroll-col-note">
              </colgroup>
              <thead>
                <tr>
                  <th colspan="2">支出項目</th>
                  <th colspan="2">收入項目</th>
                  <th>備註</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 0; $i < $payrollRowCount; $i++): ?>
                  <tr>
                    <td class="payroll-cell-label">
                      <input type="text" class="payroll-input" data-template-expense-label="<?php echo $i; ?>">
                    </td>
                    <td class="payroll-cell-amount">
                      <span class="payroll-currency">$</span>
                      <input type="number" class="payroll-input payroll-input--amount" data-template-expense-amount="<?php echo $i; ?>">
                    </td>
                    <td class="payroll-cell-label">
                      <input type="text" class="payroll-input" data-template-income-label="<?php echo $i; ?>">
                    </td>
                    <td class="payroll-cell-amount">
                      <span class="payroll-currency">$</span>
                      <input type="number" class="payroll-input payroll-input--amount" data-template-income-amount="<?php echo $i; ?>">
                    </td>
                    <?php if ($i === 0): ?>
                      <td class="payroll-note-cell" rowspan="<?php echo $payrollRowCount + 2; ?>">
                        <div class="payroll-note-live">
                          <textarea class="payroll-note" data-template-note></textarea>
                        </div>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endfor; ?>
                <tr class="payroll-total-row">
                  <td class="payroll-total-label">合計</td>
                  <td class="payroll-total-value" data-template-expense-total>$ 0</td>
                  <td class="payroll-total-label">合計</td>
                  <td class="payroll-total-value" data-template-income-total>$ 0</td>
                </tr>
                <tr class="payroll-net-row">
                  <td class="payroll-total-label" colspan="2">收入－支出</td>
                  <td class="payroll-net-total" colspan="2" data-template-net>$ 0</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="payroll-template-actions">
            <button type="button" class="btn btn--secondary" data-template-action="save">儲存模板</button>
          </div>
        </div>
      </section>

      <section class="card payroll-template-list-card">
        <div class="card__header">
          <h2 class="card__title">已儲存模板預覽</h2>
        </div>
        <div class="card__body" data-template-list>
          <div class="notice notice--info">尚未建立模板</div>
        </div>
      </section>

      <section class="payroll-print-stack print-scale" data-payroll-print-stack></section>
    </main>
  </div>
  <script>
    window.PAYROLL_EMPLOYEE_LIST = <?php
      $exportEmployees = [];
      foreach ($payrollEmployeeOptions as $employee) {
        $code = isset($employee['code']) ? (string) $employee['code'] : '';
        $name = isset($employee['name']) ? (string) $employee['name'] : '';
        if ($code === '' || $name === '') {
          continue;
        }
        $exportEmployees[] = ['id' => $code, 'name' => $name];
      }
      echo json_encode($exportEmployees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    ?>;
  </script>
  <script src="../assets/js/sidebar.js?v=20251110" defer></script>
  <script src="../assets/js/payroll-index.js?v=20251110" defer></script>
</body>
</html>
