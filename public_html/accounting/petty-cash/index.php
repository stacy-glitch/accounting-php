<?php
$modules = [
    ['id' => 'petty-cash', 'label' => '零用金', 'href' => './', 'active' => true],
    ['id' => 'sales', 'label' => '營業收入', 'href' => '../sales/'],
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
  <title>零用金系統 | Accounting</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20251102">
  <!-- ROC date picker -->
</head>
<body data-initial-year="<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>" data-initial-month="<?php echo htmlspecialchars((string) $month, ENT_QUOTES, 'UTF-8'); ?>">
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
                class="sidebar__nav-item sidebar__nav-item--toggle"
                data-sidebar-toggle
                aria-expanded="false"
              >
                <span class="sidebar__nav-label"><?php echo htmlspecialchars($module['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="sidebar__nav-arrow" aria-hidden="true"></span>
              </button>
              <ul class="sidebar__subnav" hidden>
                <?php foreach ($module['children'] as $child): ?>
                  <li>
                    <a
                      class="sidebar__subnav-item"
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
      <h1 class="content__title">零用金記錄</h1>

      <div class="petty-toolbar">
        <button type="button" class="btn btn--ghost petty-toolbar__nav" data-nav="prev">‹ 上月</button>
        <div class="petty-toolbar__title" data-month-title>-- 年 -- 月零用金記錄</div>
        <div class="petty-toolbar__actions">
          <button type="button" class="btn btn--ghost petty-toolbar__nav" data-nav="next">下月 ›</button>
          <input type="file" data-file-input accept=".pdf,.xlsx,.xls,.csv" hidden>
        </div>
      </div>

      <div class="notice" data-message hidden></div>

      <section class="petty-card">
        <header class="petty-card__header">
          <h2 class="petty-card__title">新增零用金記錄</h2>
          <div class="petty-card__status" data-editing-indicator hidden></div>
          <div class="petty-card__metrics">
            <div class="petty-card__metric petty-card__metric--opening">
              <span class="petty-card__metric-label">期初餘額</span>
              <span class="petty-card__metric-value petty-card__metric-value--opening" data-opening-balance>--</span>
              <button type="button" class="petty-button petty-button--link" data-action="edit-opening">調整</button>
            </div>
          </div>
        </header>
        <form class="petty-form" data-petty-form>
          <div class="petty-form__grid">
            <div class="petty-field petty-field--col1 petty-field--with-button">
              <label for="entry-date" class="petty-field__label">登記日期</label>
              <div class="petty-field__line">
                <input id="entry-date" name="entry_date" type="text" class="petty-input" placeholder="請輸入或選擇日期" list="petty-date-list" data-default-today>
                <button type="button" class="petty-button petty-button--outline" data-picker="entry">選擇</button>
                <input type="date" data-native-picker="entry" hidden>
              </div>
            </div>
            <div class="petty-field petty-field--col2 petty-field--with-button">
              <label for="trade-date" class="petty-field__label">實際交易日期</label>
              <div class="petty-field__line">
                <input id="trade-date" name="trade_date" type="text" class="petty-input" placeholder="請輸入或選擇日期" list="petty-date-list" data-default-prev-day>
                <button type="button" class="petty-button petty-button--outline" data-picker="trade">選擇</button>
                <input type="date" data-native-picker="trade" hidden>
              </div>
            </div>
            <div class="petty-field petty-field--col3 petty-field--with-button petty-field--month">
              <label for="trade-month" class="petty-field__label">實際交易年月</label>
              <div class="petty-field__line">
                <input id="trade-month-display" type="text" class="petty-input" placeholder="請輸入或選擇年月" list="petty-month-list">
                <button type="button" class="petty-button petty-button--outline" data-picker="month">選擇</button>
                <div class="petty-month-menu" data-month-menu hidden></div>
              </div>
              <input id="trade-month" name="trade_month" type="hidden">
            </div>
            <div class="petty-field petty-field--col1">
              <label for="entry-code" class="petty-field__label">代號</label>
              <input id="entry-code" name="code" type="text" class="petty-input" placeholder="請輸入代號" list="petty-code-list" autocomplete="off">
              <datalist id="petty-code-list"></datalist>
            </div>
            <div class="petty-field petty-field--col2">
              <label for="entry-subject" class="petty-field__label">會計科目</label>
              <input id="entry-subject" name="subject" type="text" class="petty-input" placeholder="請輸入或選擇會計科目" list="petty-subject-list" autocomplete="off">
              <datalist id="petty-subject-list"></datalist>
            </div>
            <div class="petty-field petty-field--col3">
              <label for="entry-note" class="petty-field__label">備註</label>
              <input id="entry-note" name="note" type="text" class="petty-input" placeholder="備註說明">
            </div>
            <div class="petty-field petty-field--col1">
              <label for="income" class="petty-field__label">收入金額</label>
              <input id="income" name="income" type="number" step="1" min="0" class="petty-input" value="0">
            </div>
            <div class="petty-field petty-field--col2">
              <label for="expense" class="petty-field__label">支出金額</label>
              <input id="expense" name="expense" type="number" step="1" min="0" class="petty-input" value="0">
            </div>
            <div class="petty-field petty-field--col3 petty-field--invoice">
              <label class="petty-field__label">發票</label>
              <div class="petty-field__line petty-field__line--invoice">
                <div class="petty-invoice-info" data-new-invoice-label>尚未選擇檔案</div>
                <div class="petty-invoice-actions">
                  <button type="button" class="btn btn--ghost petty-invoice-form-button" data-action="new-invoice-choose">上傳</button>
                  <button type="button" class="btn btn--ghost petty-invoice-form-button petty-invoice-button--delete" data-action="new-invoice-clear">刪除</button>
                </div>
              </div>
              <input type="file" accept="image/*" data-new-invoice-input hidden>
            </div>
            <div class="petty-field petty-field--col1">
              <label for="advance-income" class="petty-field__label">代墊款收入</label>
              <input id="advance-income" name="advance_income" type="number" step="1" min="0" class="petty-input" value="0">
            </div>
            <div class="petty-field petty-field--col2">
              <label for="advance-expense" class="petty-field__label">代墊款支出</label>
              <input id="advance-expense" name="advance_expense" type="number" step="1" min="0" class="petty-input" value="0">
            </div>
            <div class="petty-field petty-field--col3">
              <label for="balance" class="petty-field__label">剩餘金額</label>
              <input id="balance" name="balance" type="text" class="petty-input" value="0" readonly>
            </div>
          </div>
          <datalist id="petty-date-list"></datalist>
          <datalist id="petty-month-list"></datalist>
          <div class="petty-form__actions petty-form__actions--center">
            <button type="submit" class="btn btn--success" data-action="submit-entry">＋ 新增記錄</button>
          </div>
        </form>
      </section>

      <section class="table-card">
        <header class="table-card__header">
          <button type="button" class="btn btn--ghost petty-toolbar__nav" data-nav="prev">‹ 上月</button>
          <h2 class="table-card__title" data-table-month>-- 年 -- 月零用金記錄</h2>
          <div class="table-card__actions">
            <button type="button" class="btn btn--ghost petty-toolbar__nav" data-nav="next">下月 ›</button>
            <button type="button" class="btn btn--ghost table-card__upload" data-action="upload">📁 上傳對帳單</button>
          </div>
        </header>
        <div class="table-container">
          <table data-petty-table>
            <thead>
              <tr>
                <th scope="col">登記日</th>
                <th scope="col">代號</th>
                <th scope="col">會計科目</th>
                <th scope="col">交易日</th>
                <th scope="col">收入</th>
                <th scope="col">支出</th>
                <th scope="col">代墊收入</th>
                <th scope="col">代墊支出</th>
                <th scope="col">發票</th>
                <th scope="col">餘額</th>
                <th scope="col">備註</th>
                <th scope="col">操作</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="12" class="table-empty">資料載入中…</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="upload-summary" data-upload-summary hidden>
        <h3 class="upload-summary__title">本月對帳單</h3>
        <div class="upload-summary__subtitle" data-upload-summary-month>-- 年 -- 月</div>
        <table class="upload-summary__table">
          <tbody data-upload-summary-rows></tbody>
        </table>
      </section>
    </main>
  </div>
  <script src="../assets/js/sidebar.js" defer></script>
  <script src="../assets/js/petty-cash.js?v=20251112" defer></script>
</body>
</html>
