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
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => '營收報表', 'href' => './', 'active' => true],
            ['label' => '應收票據', 'href' => './notes.php'],
            ['label' => '基隆二信', 'href' => './klsb.php'],
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
?><!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>營收報表 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251220">
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
      <section class="petty-card sales-create-card">
        <header class="sales-create-card__toolbar">
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-sales-nav="prev">‹ 上月</button>
          <h1 class="sales-create-card__title" data-sales-month-title>-- 年 -- 月營收報表</h1>
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-sales-nav="next">下月 ›</button>
        </header>
        <form class="petty-form" data-sales-create-form>
          <div class="petty-form__grid sales-create-card__grid">
            <div class="petty-field petty-field--col1 sales-create-card__field">
              <label for="sales-create-customer" class="petty-field__label">客戶（代號）</label>
              <div class="petty-field__line sales-create-card__customer-input">
                <input id="sales-create-customer" type="text" class="petty-input" data-create-customer list="sales-customer-list" placeholder="輸入客戶代號">
                <datalist id="sales-customer-list" data-customer-list></datalist>
              </div>
            </div>
            <div class="petty-field petty-field--col2 sales-create-card__field">
              <label for="sales-create-freight" class="petty-field__label">運費</label>
              <input id="sales-create-freight" type="number" class="petty-input" data-create-field="freight" placeholder="0" min="0" step="1">
            </div>
            <div class="petty-field petty-field--col3 sales-create-card__field">
              <label for="sales-create-invoice" class="petty-field__label">發票金額</label>
              <input id="sales-create-invoice" type="number" class="petty-input" data-create-field="invoice_amount" placeholder="0" min="0" step="1">
            </div>
            <div class="petty-field petty-field--col1 sales-create-card__field">
              <label for="sales-create-tax" class="petty-field__label">稅金</label>
              <input id="sales-create-tax" type="number" class="petty-input" data-create-field="tax" placeholder="0" min="0" step="1">
            </div>
            <div class="petty-field petty-field--col2 sales-create-card__field">
              <label for="sales-create-warehouse" class="petty-field__label">代墊支出</label>
              <input id="sales-create-warehouse" type="number" class="petty-input" data-create-field="warehouse_fee" placeholder="0" min="0" step="1">
            </div>
            <div class="petty-field petty-field--col3 sales-create-card__field">
              <label for="sales-create-total" class="petty-field__label">合計</label>
              <input id="sales-create-total" type="number" class="petty-input" data-create-field="total" placeholder="0" min="0" step="1">
            </div>
            <div class="petty-field petty-field--col1 sales-create-card__field">
              <label for="sales-create-actual" class="petty-field__label">實收</label>
              <input id="sales-create-actual" type="number" class="petty-input" data-create-field="actual_received" placeholder="0" min="0" step="1">
            </div>
            <div class="petty-field petty-field--col2 sales-create-card__field">
              <label for="sales-create-date" class="petty-field__label">收款日期</label>
              <input id="sales-create-date" type="text" class="petty-input" data-create-field="received_date">
            </div>
            <div class="petty-field petty-field--col3 sales-create-card__field">
              <label for="sales-create-method" class="petty-field__label">收款方式</label>
              <input id="sales-create-method" type="text" class="petty-input" data-create-field="received_method">
            </div>
            <div class="petty-field sales-create-card__field sales-create-card__field--wide">
              <label for="sales-create-note" class="petty-field__label">備註</label>
              <textarea id="sales-create-note" class="petty-input sales-create-card__textarea" data-create-field="note" rows="2"></textarea>
            </div>
          </div>
          <div class="petty-form__actions petty-form__actions--center sales-create-card__actions">
            <button type="submit" class="btn btn--success" data-action="create-revenue">＋ 新增營收</button>
          </div>
        </form>
      </section>
      <section class="sales-card">
        <div class="sales-toolbar">
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-sales-nav="prev">‹ 上月</button>
          <h1 class="sales-toolbar__title" data-sales-month-title>-- 年 -- 月營收報表</h1>
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-sales-nav="next">下月 ›</button>
        </div>
        <div class="sales-toolbar actions-row">
          <div></div>
          <div class="sales-toolbar__actions">
            <button type="button" class="btn btn--success" data-action="upload">📁 上傳</button>
            <button type="button" class="btn btn--danger-soft" data-action="download">📥 下載</button>
            <input type="file" accept=".pdf,.xlsx,.xls,.csv" data-sales-upload hidden>
          </div>
        </div>
        <div class="table-container">
          <table class="sales-table" data-sales-table>
            <thead>
              <tr>
                <th scope="col">客戶</th>
                <th scope="col">運費</th>
                <th scope="col">發票金額</th>
                <th scope="col">稅金</th>
                <th scope="col">代墊支出</th>
                <th scope="col">合計</th>
                <th scope="col">實收</th>
                <th scope="col">收款日期</th>
                <th scope="col">收款方式</th>
                <th scope="col">備註</th>
                <th scope="col">操作</th>
              </tr>
            </thead>
            <tbody data-sales-rows>
              <tr>
                <td colspan="11" class="table-empty">資料載入中…</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="advance-detail" data-advance-detail-drawer hidden>
          <div class="advance-detail__panel">
            <div class="advance-detail__header">
              <h3 class="advance-detail__title" data-advance-detail-title>代墊支出明細</h3>
              <button type="button" class="btn btn--secondary btn--small" data-advance-detail-close>關閉</button>
            </div>
            <div class="advance-detail__body">
              <table class="advance-detail__table">
                <thead>
                  <tr>
                    <th scope="col">登記日</th>
                    <th scope="col">交易日</th>
                    <th scope="col">金額</th>
                    <th scope="col">備註</th>
                  </tr>
                </thead>
                <tbody data-advance-detail-body>
                  <tr>
                    <td colspan="4" class="table-empty">目前沒有銷帳明細</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/sales-index.js?v=20251220" defer></script>
</body>
</html>
