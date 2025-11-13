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
        'href' => './',
        'open' => true,
        'children' => [
            ['label' => '營收報表', 'href' => './'],
            ['label' => '應收票據', 'href' => './notes.php'],
            ['label' => '基隆二信', 'href' => './klsb.php'],
            ['label' => '兆豐銀行', 'href' => './mega-bank.php'],
            ['label' => '匯款帳號管理', 'href' => './remittance.php', 'active' => true],
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
  <title>匯款帳號管理 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251228">
</head>
<body>
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
          <button type="button" class="btn btn--success sales-toolbar__nav-button" disabled>‹ 上月</button>
          <h1 class="sales-create-card__title">新增匯款帳號</h1>
          <button type="button" class="btn btn--success sales-toolbar__nav-button" disabled>下月 ›</button>
        </header>
        <form class="petty-form sales-create-card__form" data-remittance-form>
          <div class="petty-form__grid sales-create-card__grid">
            <div class="petty-field petty-field--col1 sales-create-card__field">
              <label class="petty-field__label" for="remit-customer">客戶</label>
              <input
                id="remit-customer"
                type="text"
                class="petty-input"
                data-remittance-field="customer"
                data-remittance-customer
                list="remittance-customer-list"
                autocomplete="off"
              >
              <datalist id="remittance-customer-list" data-remittance-customer-list></datalist>
            </div>
            <div class="petty-field petty-field--col2 sales-create-card__field">
              <label class="petty-field__label" for="remit-account">帳戶</label>
              <input id="remit-account" type="text" class="petty-input" data-remittance-field="account">
            </div>
            <div class="petty-field petty-field--col3 sales-create-card__field">
              <label class="petty-field__label" for="remit-bank">匯款銀行</label>
              <select id="remit-bank" class="petty-input" data-remittance-field="bank">
                <option value=""></option>
                <option value="基隆二信">基隆二信</option>
                <option value="兆豐銀行">兆豐銀行</option>
              </select>
            </div>
            <div class="petty-field sales-create-card__field sales-create-card__field--wide">
              <label class="petty-field__label" for="remit-remark">備註</label>
              <textarea id="remit-remark" class="petty-input sales-create-card__textarea" data-remittance-field="remark" rows="2"></textarea>
            </div>
          </div>
          <div class="petty-form__actions petty-form__actions--center sales-create-card__actions">
            <button type="submit" class="btn btn--success">＋ 新增記錄</button>
          </div>
        </form>
      </section>

      <section class="sales-card">
        <div class="sales-toolbar">
          <div class="sales-toolbar__spacer" aria-hidden="true"></div>
          <h1 class="sales-toolbar__title">匯款帳號表</h1>
          <div class="sales-toolbar__actions">
            <button type="button" class="btn btn--success" data-remittance-upload-trigger>上傳 .xlsx</button>
            <input
              id="remittance-upload-input"
              type="file"
              accept=".xlsx,.csv"
              data-remittance-upload
              hidden
            >
          </div>
        </div>
        <div class="table-container">
          <table class="sales-table sales-table--remittance" data-remittance-table>
            <thead>
              <tr>
                <th scope="col">客戶</th>
                <th scope="col">帳戶</th>
                <th scope="col">匯款銀行</th>
                <th scope="col">備註</th>
                <th scope="col">操作</th>
              </tr>
            </thead>
            <tbody data-remittance-rows>
              <tr>
                <td colspan="5" class="table-empty">尚無匯款帳號，請新增一筆</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/sales-remittance.js?v=20251107b" defer></script>
</body>
</html>
