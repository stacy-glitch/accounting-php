<?php
$payrollChildren = [
    ['label' => '薪資表', 'href' => './'],
    ['label' => '勞保表', 'href' => './labor.php'],
    ['label' => '健保表', 'href' => './health.php'],
    ['label' => '中油表', 'href' => './cpc.php'],
    ['label' => '司機金額總匯', 'href' => './drivers-summary.php'],
    ['label' => '靠行表', 'href' => './affiliates.php', 'active' => true],
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
?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>靠行表 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251107">
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
      <section class="card affiliate-card">
        <div class="payroll-table-wrapper">
          <table class="payroll-table affiliate-table">
            <colgroup>
              <col class="payroll-col-label">
              <col class="payroll-col-amount">
              <col class="payroll-col-label">
              <col class="payroll-col-amount">
            </colgroup>
            <thead>
              <tr class="affiliate-heading-row">
                <th colspan="4" class="affiliate-heading affiliate-heading--center affiliate-heading--plain">
                  <span class="affiliate-heading__value" data-affiliate-driver-heading>—</span>
                  <label class="visually-hidden" for="affiliate-employee">司機</label>
                  <select id="affiliate-employee" class="affiliate-heading__select affiliate-heading__select--driver" data-affiliate-select="employee">
                    <option value="">-- 選擇司機 --</option>
                  </select>
                </th>
              </tr>
              <tr class="affiliate-heading-row">
                <th colspan="4" class="affiliate-heading affiliate-heading--center affiliate-heading--plain">
                  <span class="affiliate-heading__value" data-affiliate-period-heading>—</span>
                  <div class="affiliate-heading__period-selects">
                    <label class="visually-hidden" for="affiliate-year">年份</label>
                    <select id="affiliate-year" class="affiliate-heading__select affiliate-heading__select--year" data-affiliate-select="year"></select>
                    <label class="visually-hidden" for="affiliate-range">月份區間</label>
                    <select id="affiliate-range" class="affiliate-heading__select affiliate-heading__select--range" data-affiliate-select="range"></select>
                  </div>
                </th>
              </tr>
              <tr class="affiliate-heading-row">
                <th colspan="3" class="affiliate-heading affiliate-heading--plain"></th>
                <th class="affiliate-heading affiliate-heading--right affiliate-heading--plain">車號：<span data-affiliate-car>—</span></th>
              </tr>
              <tr class="affiliate-columns-row">
                <th>支出項目</th>
                <th>金額</th>
                <th>收入項目</th>
                <th>金額</th>
              </tr>
            </thead>
            <tbody>
              <?php $rowCount = 8; ?>
              <?php for ($i = 0; $i < $rowCount; $i++): ?>
                <tr>
                  <td class="payroll-cell-label">
                    <input type="text" class="payroll-input" data-affiliate-expense-label="<?php echo $i; ?>">
                  </td>
                  <td class="payroll-cell-amount">
                    <span class="payroll-currency">$</span>
                    <input type="number" class="payroll-input payroll-input--amount" data-affiliate-expense-amount="<?php echo $i; ?>">
                  </td>
                  <td class="payroll-cell-label">
                    <input type="text" class="payroll-input" data-affiliate-income-label="<?php echo $i; ?>">
                  </td>
                  <td class="payroll-cell-amount">
                    <span class="payroll-currency">$</span>
                    <input type="number" class="payroll-input payroll-input--amount" data-affiliate-income-amount="<?php echo $i; ?>">
                  </td>
                </tr>
              <?php endfor; ?>
              <tr class="payroll-total-row">
                <td class="payroll-total-label">支出合計</td>
                <td class="payroll-total-value" data-affiliate-expense-total>$ 0</td>
                <td class="payroll-total-label">收入合計</td>
                <td class="payroll-total-value" data-affiliate-income-total>$ 0</td>
              </tr>
              <tr class="payroll-net-row">
                <td class="payroll-total-label" colspan="3">本期費用（支出－收入）</td>
                <td class="payroll-net-total" data-affiliate-net>$ 0</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="affiliate-actions">
          <label for="affiliate-upload" class="btn btn--ghost affiliate-upload-btn">📁 上傳.xlsx</label>
          <input id="affiliate-upload" type="file" accept=".xlsx" data-affiliate-upload hidden>
          <button type="button" class="btn btn--secondary" data-affiliate-add>新增明細</button>
          <button type="button" class="btn" data-affiliate-download>下載 PDF</button>
        </div>
      </section>

      <section class="card affiliate-saved-card">
        <div class="card__header">
          <h2 class="card__title">靠行明細列表</h2>
        </div>
        <div class="card__body" data-affiliate-saved>
          <div class="payroll-template-empty">尚未新增明細</div>
        </div>
      </section>

      <section class="affiliate-print-stack" data-affiliate-print-stack></section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/payroll-affiliates.js" defer></script>
</body>
</html>
