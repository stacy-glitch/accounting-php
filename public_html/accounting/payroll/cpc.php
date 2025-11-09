<?php
$payrollChildren = [
    ['label' => '薪資表', 'href' => './'],
    ['label' => '勞保表', 'href' => './labor.php'],
    ['label' => '健保表', 'href' => './health.php'],
    ['label' => '中油表', 'href' => './cpc.php', 'active' => true],
    ['label' => '司機金額總匯', 'href' => './drivers-summary.php'],
    ['label' => '靠行表', 'href' => './affiliates.php'],
];

$now = new DateTime('first day of this month');
$now->modify('-1 month');
$defaultMonth = (int) $now->format('n');
$defaultYear = (int) $now->format('Y');
$defaultRocYear = $defaultYear - 1911;

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
    ['id' => 'expenses', 'label' => '各項費用', 'href' => '../expenses/'],
    ['id' => 'cashflow', 'label' => '收支（現金流）', 'href' => '../cashflow/'],
    ['id' => 'vehicle-costs', 'label' => '車輛成本', 'href' => '../vehicle-costs/'],
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
  <title>中油表 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251107">
</head>
<body data-initial-roc-year="<?php echo htmlspecialchars((string) $defaultRocYear, ENT_QUOTES, 'UTF-8'); ?>" data-initial-month="<?php echo htmlspecialchars((string) $defaultMonth, ENT_QUOTES, 'UTF-8'); ?>">
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
      <section class="card cpc-card">
        <header class="labor-period-nav">
          <button type="button" class="btn btn--ghost btn--small" data-cpc-nav="prev">‹ 上月</button>
          <h1 class="labor-period-title" data-cpc-period>-- 年 -- 月中油表</h1>
          <button type="button" class="btn btn--ghost btn--small" data-cpc-nav="next">下月 ›</button>
        </header>
        <div class="card__body">
          <div class="cpc-toolbar">
            <div class="cpc-summary" data-cpc-summary>已載入 0 筆資料</div>
            <div class="cpc-upload-actions">
              <button type="button" class="btn btn--ghost labor-upload-btn" data-cpc-upload>
                <span class="labor-action-icon" aria-hidden="true">📁</span>
                上傳
              </button>
              <input type="file" accept=".csv" data-cpc-upload-input hidden>
            </div>
          </div>
          <div class="cpc-upload-message" data-cpc-upload-message>&nbsp;</div>
          <div class="table-container cpc-table-container">
            <table class="cpc-table">
              <thead>
                <tr>
                  <th>車牌號碼</th>
                  <th>司機</th>
                  <th>交易日期</th>
                  <th>油站</th>
                  <th>金額</th>
                  <th>備註</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody data-cpc-table-body>
                <tr>
                  <td colspan="7" class="table-empty">目前沒有中油資料</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/payroll-cpc.js" defer></script>
</body>
</html>
