<?php
$payrollNav = [
    ['label' => '薪資表', 'href' => '../payroll/'],
    ['label' => '勞保表', 'href' => '../payroll/labor.php'],
    ['label' => '健保表', 'href' => '../payroll/health.php'],
    ['label' => '中油表', 'href' => '../payroll/cpc.php'],
    ['label' => '司機金額總匯', 'href' => '../payroll/drivers-summary.php'],
    ['label' => '靠行表', 'href' => '../payroll/affiliates.php'],
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
        'href' => '../payroll/',
        'children' => $payrollNav,
    ],
    [
        'id' => 'cashflow',
        'label' => '收支管理',
        'href' => './',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => '收支表', 'href' => './', 'active' => true],
        ],
    ],
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

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>收支表 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251102">
</head>
<body data-initial-year="<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>" data-initial-month="<?php echo htmlspecialchars((string) $month, ENT_QUOTES, 'UTF-8'); ?>">
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
      <section class="petty-card cashflow-card">
        <header class="petty-card__header petty-card__header--compact">
          <div class="petty-toolbar petty-toolbar--spaced">
            <button type="button" class="btn btn--ghost petty-toolbar__nav" data-cashflow-nav="prev">‹ 上月</button>
            <div class="petty-toolbar__title" data-cashflow-month-title>--</div>
            <button type="button" class="btn btn--ghost petty-toolbar__nav" data-cashflow-nav="next">下月 ›</button>
          </div>
        </header>

        <div class="notice" data-cashflow-message hidden></div>

        <div class="table-container cashflow-table" data-cashflow-table>
          <table class="petty-table">
            <thead>
              <tr>
                <th>支出項目</th>
                <th style="text-align:right;">支出金額</th>
                <th>收入項目</th>
                <th style="text-align:right;">收入金額</th>
              </tr>
            </thead>
            <tbody data-cashflow-rows>
              <tr>
                <td colspan="4" class="table-empty">資料載入中…</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="cashflow-total-row">
                <td>合計</td>
                <td class="cashflow-total" data-cashflow-expense-total>0</td>
                <td>合計</td>
                <td class="cashflow-total" data-cashflow-income-total>0</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </main>
  </div>

  <script src="../assets/js/sidebar.js?v=20251114" defer></script>
  <script src="../assets/js/cashflow.js?v=20251114" defer></script>
</body>
</html>
