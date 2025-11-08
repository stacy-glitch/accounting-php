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
        'href' => './',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => '零用金表', 'href' => './'],
            ['label' => '代墊款表', 'href' => './advances.php', 'active' => true],
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
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>代墊款表 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251220">
</head>
<body data-initial-year="<?php echo htmlspecialchars((string) date('Y'), ENT_QUOTES, 'UTF-8'); ?>" data-initial-month="<?php echo htmlspecialchars((string) date('n'), ENT_QUOTES, 'UTF-8'); ?>">
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
          <li class="sidebar__group<?php echo $isGroupActive ? ' sidebar__group--active' : ''; ?><?php echo !empty($children) ? ' sidebar__group--has-children' : ''; ?>" data-sidebar-group>
            <?php if (!empty($children)): ?>
              <button type="button" class="sidebar__nav-item sidebar__nav-item--toggle<?php echo $isGroupActive ? ' sidebar__nav-item--active' : ''; ?>" data-sidebar-toggle aria-expanded="<?php echo $isGroupOpen ? 'true' : 'false'; ?>">
                <span class="sidebar__nav-label"><?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="sidebar__nav-arrow" aria-hidden="true"></span>
              </button>
              <ul class="sidebar__subnav"<?php echo $isGroupOpen ? '' : ' hidden'; ?>>
                <?php foreach ($children as $child): ?>
                  <li>
                    <a class="sidebar__subnav-item<?php echo !empty($child['active']) ? ' sidebar__subnav-item--active' : ''; ?>" href="<?php echo htmlspecialchars($child['href'], ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars($child['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <a class="sidebar__nav-item<?php echo !empty($module['active']) ? ' sidebar__nav-item--active' : ''; ?>" href="<?php echo htmlspecialchars($module['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="sidebar__nav-label"><?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>
    <main class="content">
      <section class="petty-card petty-card--search">
        <header class="petty-card__header">
          <h2 class="petty-card__title">代墊款搜尋</h2>
        </header>
        <form class="petty-search-form" data-advance-search-form>
          <div class="petty-search-grid">
            <label class="petty-search-field">
              <span class="petty-search-label">代號</span>
              <div class="petty-search-input">
                <input type="text" class="petty-input" placeholder="請輸入代號" list="advance-code-list" data-advance-search-code autocomplete="off">
                <datalist id="advance-code-list"></datalist>
              </div>
            </label>
            <div class="petty-search-actions">
              <button type="submit" class="btn btn--success" data-action="search-code">搜尋</button>
            </div>
          </div>
        </form>
        <div class="notice" data-search-message hidden></div>
        <div class="table-container petty-search-result" data-search-result hidden>
          <table>
            <thead>
              <tr>
                <th scope="col">代號</th>
                <th scope="col">登記日</th>
                <th scope="col">交易日</th>
                <th scope="col">未銷金額</th>
                <th scope="col">對應表單</th>
                <th scope="col">備註</th>
              </tr>
            </thead>
            <tbody data-search-result-body>
              <tr>
                <td colspan="6" class="table-empty">尚未查詢</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="petty-search-summary" data-search-summary hidden>
          <span>未銷總額：</span>
          <strong data-search-total>0</strong>
        </div>
      </section>

      <section class="petty-card petty-card--advances">
        <header class="petty-card__header petty-card__header--advances">
          <button type="button" class="btn btn--success petty-toolbar__nav petty-advances-nav__button petty-advances-nav__button--prev" data-month-nav="prev">‹ 上月</button>
          <h2 class="petty-card__title petty-advances-nav__title" data-month-title>-- 年 -- 月代墊款表</h2>
          <button type="button" class="btn btn--success petty-toolbar__nav petty-advances-nav__button petty-advances-nav__button--next" data-month-nav="next">下月 ›</button>
        </header>
        <div class="table-container">
          <table data-advance-month-table>
            <thead>
              <tr>
                <th scope="col">代號</th>
                <th scope="col">登記日</th>
                <th scope="col">交易日</th>
                <th scope="col">未銷金額</th>
                <th scope="col">對應表單</th>
                <th scope="col">備註</th>
              </tr>
            </thead>
            <tbody data-month-rows>
              <tr>
                <td colspan="6" class="table-empty">資料載入中…</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="petty-advance-footer" data-month-footer hidden>
          <span>本月未銷總額：</span>
          <strong data-month-total>0</strong>
        </footer>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/petty-advances.js?v=20251221" defer></script>
</body>
</html>
