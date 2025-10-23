<?php
$modules = [
    ['label' => '零用金', 'href' => '../petty-cash/'],
    ['label' => '營業收入', 'href' => '../sales/'],
    ['label' => '薪資', 'href' => '../payroll/'],
    ['label' => '各項費用', 'href' => '../expenses/'],
    ['label' => '收支（現金流）', 'href' => '../cashflow/'],
    ['label' => '車輛成本', 'href' => '../vehicle-costs/'],
    ['label' => '資料維護', 'href' => './', 'active' => true],
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
          <li>
            <a
              class="sidebar__nav-item<?php echo !empty($module['active']) ? ' sidebar__nav-item--active' : ''; ?>"
              href="<?php echo htmlspecialchars($module['href'], ENT_QUOTES, 'UTF-8'); ?>"
            >
              <?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
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
  <script src="../assets/js/master.js" defer></script>
</body>
</html>
