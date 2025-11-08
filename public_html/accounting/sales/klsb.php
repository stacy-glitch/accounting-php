<?php
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
        'href' => './',
        'open' => true,
        'children' => [
            ['label' => '營收報表', 'href' => './'],
            ['label' => '應收票據', 'href' => './notes.php'],
            ['label' => '基隆二信', 'href' => './klsb.php', 'active' => true],
            ['label' => '兆豐銀行', 'href' => './mega-bank.php'],
            ['label' => '匯款帳號管理', 'href' => './remittance.php'],
        ],
    ],
    ['id' => 'payroll', 'label' => '薪資', 'href' => '../payroll/'],
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

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>基隆二信 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251227">
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
      <section class="sales-card" data-klsb-card>
        <div class="sales-toolbar">
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-klsb-nav="prev">‹ 上月</button>
          <h1 class="sales-toolbar__title" data-klsb-title>-- 年 -- 月基隆二信明細</h1>
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-klsb-nav="next">下月 ›</button>
        </div>
        <div class="sales-toolbar actions-row">
          <div></div>
          <div class="sales-toolbar__actions">
            <button type="button" class="btn btn--success" data-action="upload-klsb">📁 上傳</button>
            <button type="button" class="btn btn--danger-soft" data-action="download-klsb">📥 下載</button>
            <input type="file" data-klsb-upload-input accept=".xlsx,.xls,.csv,.pdf,.jpg,.jpeg,.png" hidden>
          </div>
        </div>
        <div class="table-container">
          <table class="sales-table" data-klsb-table>
            <thead>
              <tr>
                <th scope="col">交易日期</th>
                <th scope="col">摘要</th>
                <th scope="col">支出金額</th>
                <th scope="col">收入金額</th>
                <th scope="col">帳戶餘額</th>
                <th scope="col">備註</th>
                <th scope="col">對帳</th>
                <th scope="col">對應表單</th>
                <th scope="col">操作</th>
              </tr>
            </thead>
            <tbody data-klsb-rows>
              <tr>
                <td colspan="9" class="table-empty">資料載入中…</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/sales-klsb.js?v=20251228" defer></script>
</body>
</html>
