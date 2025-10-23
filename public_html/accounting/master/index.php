<?php
$masterTabs = [
    ['id' => 'customers', 'label' => '客戶資料'],
    ['id' => 'vehicles', 'label' => '車輛資料'],
    ['id' => 'employees', 'label' => '員工資料'],
    ['id' => 'accounts', 'label' => '會計科目'],
];
$requestedTab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : '';
$validTabIds = array_column($masterTabs, 'id');
$currentTab = in_array($requestedTab, $validTabIds, true) ? $requestedTab : $masterTabs[0]['id'];

$modules = [
    ['id' => 'petty-cash', 'label' => '零用金', 'href' => '../petty-cash/'],
    ['id' => 'sales', 'label' => '營業收入', 'href' => '../sales/'],
    ['id' => 'payroll', 'label' => '薪資', 'href' => '../payroll/'],
    ['id' => 'expenses', 'label' => '各項費用', 'href' => '../expenses/'],
    ['id' => 'cashflow', 'label' => '收支（現金流）', 'href' => '../cashflow/'],
    ['id' => 'vehicle-costs', 'label' => '車輛成本', 'href' => '../vehicle-costs/'],
    [
        'id' => 'master-data',
        'label' => '資料維護',
        'href' => './',
        'active' => true,
        'open' => true,
        'children' => array_map(function ($tab) use ($currentTab) {
            return [
                'label' => $tab['label'],
                'tab' => $tab['id'],
                'href' => '?tab=' . urlencode($tab['id']),
                'active' => $tab['id'] === $currentTab,
            ];
        }, $masterTabs),
    ],
];
?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>資料維護 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar__title">會計系統</div>
      <ul class="sidebar__nav">
        <?php foreach ($modules as $module): ?>
          <li
            class="sidebar__group<?php echo !empty($module['active']) ? ' sidebar__group--active' : ''; ?><?php echo !empty($module['children']) ? ' sidebar__group--has-children' : ''; ?>"
            data-sidebar-group
          >
            <?php if (!empty($module['children'])): ?>
              <button
                type="button"
                class="sidebar__nav-item sidebar__nav-item--toggle<?php echo !empty($module['active']) ? ' sidebar__nav-item--active' : ''; ?>"
                data-sidebar-toggle
                aria-expanded="<?php echo !empty($module['open']) ? 'true' : 'false'; ?>"
              >
                <span class="sidebar__nav-label"><?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="sidebar__nav-arrow" aria-hidden="true"></span>
              </button>
              <ul class="sidebar__subnav"<?php echo !empty($module['open']) ? '' : ' hidden'; ?>>
                <?php foreach ($module['children'] as $child): ?>
                  <li>
                    <a
                      class="sidebar__subnav-item<?php echo !empty($child['active']) ? ' sidebar__subnav-item--active' : ''; ?>"
                      href="<?php echo htmlspecialchars($child['href'], ENT_QUOTES, 'UTF-8'); ?>"
                      data-tab-link="<?php echo htmlspecialchars($child['tab'], ENT_QUOTES, 'UTF-8'); ?>"
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
      <h1 class="content__title">資料維護</h1>
      <div class="card">
        <div class="card__header">
          <h2 class="card__title">主檔資料</h2>
          <div class="card__actions" data-card-actions>
            <button type="button" class="btn" data-action="create">＋ 新增</button>
            <button type="button" class="btn btn--secondary" data-action="upload">📤 上傳舊檔</button>
          </div>
        </div>
        <div class="tabs" data-tab-list></div>
        <div class="card__body">
          <div class="notice" data-message hidden></div>
          <div data-form-container></div>
          <div data-status class="loading-state"></div>
          <div class="table-container" data-table-container></div>
        </div>
      </div>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/master.js" defer></script>
</body>
</html>
