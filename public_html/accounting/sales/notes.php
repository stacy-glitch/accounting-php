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
            ['label' => '營收報表', 'href' => './'],
            ['label' => '應收票據', 'href' => './notes.php', 'active' => true],
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
  <title>應收票據 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251224">
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
          <h1 class="sales-toolbar__title">新增應收票據</h1>
        </header>
        <form class="petty-form sales-create-card__form" autocomplete="off" data-notes-create-form>
          <datalist id="notes-date-list"></datalist>
          <div class="petty-form__grid sales-create-card__grid notes-create-grid">
            <div class="notes-create-grid__row notes-create-grid__row--header">
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--customer">
                <label for="notes-create-customer" class="petty-field__label">客戶（代號）</label>
                <input id="notes-create-customer" type="text" class="petty-input" placeholder="輸入客戶代號" list="notes-customer-list" data-notes-customer autocomplete="off">
                <datalist id="notes-customer-list" data-notes-customer-list></datalist>
              </div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--number">
                <label for="notes-create-number" class="petty-field__label">票號</label>
                <input id="notes-create-number" type="text" class="petty-input" data-notes-field="number" placeholder="請輸入票據號碼">
              </div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--amount">
                <label for="notes-create-amount" class="petty-field__label">金額</label>
                <input id="notes-create-amount" type="number" class="petty-input" data-notes-field="amount" min="0" step="1" placeholder="0">
              </div>
            </div>
            <div class="notes-create-grid__row">
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--with-button">
                <label for="notes-create-entry-date" class="petty-field__label">登記日</label>
                <div class="notes-date-field">
                  <input id="notes-create-entry-date" type="text" class="petty-input" placeholder="請選擇日期" list="notes-date-list" data-notes-date="entry">
                  <button type="button" class="petty-button petty-button--outline" data-notes-picker="entry">選擇</button>
                </div>
                <div class="notes-date-menu" data-notes-date-menu="entry" hidden></div>
              </div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--with-button">
                <label for="notes-create-due-date" class="petty-field__label">到期日</label>
                <div class="notes-date-field">
                  <input id="notes-create-due-date" type="text" class="petty-input" placeholder="請選擇日期" list="notes-date-list" data-notes-date="due">
                  <button type="button" class="petty-button petty-button--outline" data-notes-picker="due">選擇</button>
                </div>
                <div class="notes-date-menu" data-notes-date-menu="due" hidden></div>
              </div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--with-button">
                <label for="notes-create-deposit-date" class="petty-field__label">入帳日</label>
                <div class="notes-date-field">
                  <input id="notes-create-deposit-date" type="text" class="petty-input" placeholder="請選擇日期" list="notes-date-list" data-notes-date="deposit">
                  <button type="button" class="petty-button petty-button--outline" data-notes-picker="deposit">選擇</button>
                </div>
                <div class="notes-date-menu" data-notes-date-menu="deposit" hidden></div>
              </div>
            </div>
            <div class="notes-create-grid__row">
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--with-button">
                <label for="notes-create-months" class="petty-field__label">帳款月份</label>
                <div class="notes-date-field">
                  <input id="notes-create-months" type="text" class="petty-input" placeholder="請輸入或選擇年月" data-notes-month readonly>
                  <button type="button" class="petty-button petty-button--outline" data-notes-month-picker>選擇</button>
                </div>
                <div class="notes-month-menu" data-notes-month-menu hidden></div>
              </div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--spacer"></div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--spacer"></div>
            </div>
            <div class="notes-create-grid__row notes-create-grid__row--wide">
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--note">
                <label for="notes-create-note" class="petty-field__label">備註</label>
                <input id="notes-create-note" type="text" class="petty-input" data-notes-field="note" placeholder="備註說明">
              </div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--spacer"></div>
              <div class="notes-create-grid__cell notes-create-grid__cell--third notes-create-grid__cell--spacer"></div>
            </div>
          </div>
          <div class="petty-form__actions petty-form__actions--center sales-create-card__actions">
            <button type="submit" class="btn btn--success" data-action="create-note">＋ 新增票據</button>
          </div>
        </form>
      </section>

      <section class="sales-card" data-notes-table-card>
        <div class="sales-toolbar">
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-notes-nav="prev">‹ 上月</button>
          <h1 class="sales-toolbar__title" data-notes-table-title>-- 年 -- 月應收票據表</h1>
          <button type="button" class="btn btn--success sales-toolbar__nav-button" data-notes-nav="next">下月 ›</button>
        </div>
        <div class="sales-toolbar actions-row">
          <div></div>
          <div class="sales-toolbar__actions">
            <button type="button" class="btn btn--success" data-action="upload-notes">📁 上傳</button>
            <button type="button" class="btn btn--danger-soft" data-action="download-notes">📥 下載</button>
            <input type="file" data-notes-upload-input accept=".csv,.xls,.xlsx,.ods,.pdf,.zip" hidden>
          </div>
        </div>
        <div class="table-container">
          <table class="sales-table" data-notes-table>
            <thead>
              <tr>
                <th scope="col">客戶</th>
                <th scope="col">票號</th>
                <th scope="col">登記日</th>
                <th scope="col">到期日</th>
                <th scope="col">入帳日</th>
                <th scope="col">帳款月份</th>
                <th scope="col">金額</th>
                <th scope="col">總計</th>
                <th scope="col">備註</th>
                <th scope="col">操作</th>
              </tr>
            </thead>
            <tbody data-notes-rows>
              <tr>
                <td colspan="10" class="table-empty">尚無資料</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/sales-notes.js?v=20251224" defer></script>
</body>
</html>
