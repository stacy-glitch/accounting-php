(function () {
  'use strict';

  const CUSTOMERS_ENDPOINT = '../api/master-data/master_customers.php';

  const root = document.body;
  if (!root) {
    return;
  }

  const createTitleEl = document.querySelector('[data-notes-create-title]');
  const tableTitleEl = document.querySelector('[data-notes-table-title]');
  const navButtons = document.querySelectorAll('[data-notes-nav]');
  const createForm = document.querySelector('[data-notes-create-form]');
  const customerInput = document.querySelector('[data-notes-customer]');
  const customerListEl = document.querySelector('[data-notes-customer-list]');
  const tableRows = document.querySelector('[data-notes-rows]');
  const dateInputs = document.querySelectorAll('[data-notes-date]');
  const dateButtons = document.querySelectorAll('[data-notes-picker]');
  const dateMenus = {
    entry: document.querySelector('[data-notes-date-menu="entry"]'),
    due: document.querySelector('[data-notes-date-menu="due"]'),
    deposit: document.querySelector('[data-notes-date-menu="deposit"]'),
  };
  const monthInput = document.querySelector('[data-notes-month]');
  const monthButton = document.querySelector('[data-notes-month-picker]');
  const monthMenu = document.querySelector('[data-notes-month-menu]');

  const state = {
    year: parseInt(root.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(root.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    customers: [],
    customerMap: new Map(),
  };

  init();

  async function init() {
    updateTitles();
    bindEvents();
    await loadCustomers();
    initializeDates();
    initializeMonthSelection();
    renderPlaceholder();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.notesNav;
        if (dir === 'prev') {
          shiftMonth(-1);
        } else if (dir === 'next') {
          shiftMonth(1);
        }
      });
    });

    if (customerInput) {
      customerInput.addEventListener('focus', handleCustomerFocus);
      customerInput.addEventListener('input', () => resolveCustomerInput({ strict: false, silent: true }));
      customerInput.addEventListener('change', () => resolveCustomerInput({ strict: false }));
      customerInput.addEventListener('blur', () => resolveCustomerInput({ strict: true }));
    }

    if (createForm) {
      createForm.addEventListener('submit', (event) => {
        event.preventDefault();
        alert('應收票據新增功能尚未串接，請稍後實作後端 API。');
      });
    }

    dateButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const type = button.dataset.notesPicker;
        if (!type) {
          return;
        }
        showDateMenu(type, button);
      });
    });

    dateInputs.forEach((input) => {
      input.addEventListener('blur', () => {
        normalizeDateInput(input);
      });
    });

    if (monthButton) {
      monthButton.addEventListener('click', () => {
        showMonthMenu();
      });
    }

    document.addEventListener('click', handleDocumentClick, true);
  }

  function shiftMonth(offset) {
    let { year, month } = state;
    month += offset;
    if (month < 1) {
      month = 12;
      year -= 1;
    } else if (month > 12) {
      month = 1;
      year += 1;
    }
    state.year = year;
    state.month = month;
    updateTitles();
    renderPlaceholder();
  }

  function updateTitles() {
    const text = formatRocMonthTitle(state.year, state.month);
    if (createTitleEl) {
      createTitleEl.textContent = `${text}新增應收票據`;
    }
    if (tableTitleEl) {
      tableTitleEl.textContent = `${text}應收票據表`;
    }
  }

  function renderPlaceholder() {
    if (!tableRows) {
      return;
    }
    tableRows.innerHTML = '<tr><td colspan="10" class="table-empty">尚無資料</td></tr>';
  }

  function initializeDates() {
    const today = new Date();
    dateInputs.forEach((input) => {
      input.value = formatRocDate(today);
    });
  }

  async function loadCustomers() {
    if (!customerListEl) {
      return;
    }
    try {
      const response = await fetch(CUSTOMERS_ENDPOINT, { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error(`載入客戶失敗 (${response.status})`);
      }
      const payload = await response.json();
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      rows.sort((a, b) => {
        const codeA = String(a.code || '').trim();
        const codeB = String(b.code || '').trim();
        return codeA.localeCompare(codeB, 'zh-Hant', { sensitivity: 'base', numeric: true });
      });
      state.customers = rows;
      const fragment = document.createDocumentFragment();
      state.customerMap = new Map();
      rows.forEach((row) => {
        const code = String(row.code || '').trim();
        const name = String(row.name || '').trim();
        if (!code) {
          return;
        }
        const option = document.createElement('option');
        option.value = `${code} — ${name}`;
        option.dataset.code = code;
        fragment.appendChild(option);
        state.customerMap.set(code, name);
      });
      customerListEl.innerHTML = '';
      customerListEl.appendChild(fragment);
    } catch (error) {
      console.error('[notes] load customers failed', error);
    }
  }

  function handleCustomerFocus() {
    if (!customerInput) {
      return;
    }
    const value = customerInput.value.trim();
    if (value && state.customerMap.has(value)) {
      customerInput.value = value;
    }
  }

  function resolveCustomerInput(options) {
    if (!customerInput) {
      return;
    }
    const { strict = false, silent = false } = options || {};
    const raw = customerInput.value.trim();
    if (!raw) {
      return;
    }

    if (raw.includes('—')) {
      const code = raw.split('—')[0].trim();
      customerInput.value = code;
      return;
    }

    if (state.customerMap.has(raw)) {
      return;
    }

    if (strict && !silent) {
      alert('找不到相符的客戶代號，請重新輸入。');
      customerInput.value = '';
    }
  }

  function formatRocMonthTitle(year, month) {
    const roc = year - 1911;
    if (roc > 0) {
      return `${roc}年${month}月`;
    }
    return `${year}年${month}月`;
  }

  function formatRocDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    const rocYear = date.getFullYear() - 1911;
    const month = date.getMonth() + 1;
    const day = date.getDate();
    return `${rocYear}年${month}月${day}日`;
  }

  function normalizeDateInput(input) {
    const text = String(input.value || '').trim();
    if (!text) {
      return;
    }
    const parsed = parseDateText(text);
    if (parsed) {
      input.value = formatRocDate(parsed);
    }
  }

  function parseDateText(text) {
    const trimmed = text.replace(/[\s]/g, '');
    const rocMatch = trimmed.match(/^(\d{2,3})年(\d{1,2})月(\d{1,2})日$/);
    if (rocMatch) {
      const year = parseInt(rocMatch[1], 10) + 1911;
      const month = parseInt(rocMatch[2], 10);
      const day = parseInt(rocMatch[3], 10);
      return new Date(year, month - 1, day);
    }
    const rocSlash = trimmed.match(/^(\d{2,3})[\/](\d{1,2})[\/](\d{1,2})$/);
    if (rocSlash) {
      const year = parseInt(rocSlash[1], 10) + 1911;
      const month = parseInt(rocSlash[2], 10);
      const day = parseInt(rocSlash[3], 10);
      return new Date(year, month - 1, day);
    }
    const isoMatch = trimmed.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);
    if (isoMatch) {
      const year = parseInt(isoMatch[1], 10);
      const month = parseInt(isoMatch[2], 10);
      const day = parseInt(isoMatch[3], 10);
      return new Date(year, month - 1, day);
    }
    return null;
  }

  function showDateMenu(type) {
    closeDateMenus();
    const menu = dateMenus[type];
    if (!menu) {
      return;
    }

    const today = new Date();
    const options = [
      { label: '今天', date: today },
      { label: '明天', date: addDays(today, 1) },
      { label: '昨天', date: addDays(today, -1) },
    ];

    const currentInput = document.querySelector(`[data-notes-date="${type}"]`);
    menu.hidden = false;
    menu.innerHTML = `
      <div class="notes-date-menu__panel">
        <div class="notes-date-menu__list">
          ${options
            .map(
              (item) => `
                <button type="button" class="notes-date-menu__option" data-action="apply-date" data-date="${item.date.toISOString()}">
                  ${item.label}｜${formatRocDate(item.date)}
                </button>
              `
            )
            .join('')}
        </div>
        <div class="notes-date-menu__footer">
          <input type="date" class="notes-date-menu__input" data-action="custom-date">
          <button type="button" class="btn btn--secondary btn--small" data-action="clear-date">清除</button>
        </div>
      </div>
    `;

    const panel = menu.querySelector('.notes-date-menu__panel');
    if (panel) {
      panel.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        const action = target.dataset.action;
        if (action === 'apply-date') {
          const iso = target.dataset.date;
          if (iso && currentInput) {
            currentInput.value = formatRocDate(new Date(iso));
          }
          closeDateMenus();
        } else if (action === 'clear-date') {
          if (currentInput) {
            currentInput.value = '';
          }
          closeDateMenus();
        }
      });

      const customInput = panel.querySelector('input[type="date"]');
      if (customInput && currentInput) {
        customInput.addEventListener('change', () => {
          if (customInput.value) {
            currentInput.value = formatRocDate(new Date(customInput.value));
          }
          closeDateMenus();
        });
      }
    }
  }

  function closeDateMenus() {
    Object.values(dateMenus).forEach((menu) => {
      if (menu) {
        menu.hidden = true;
        menu.innerHTML = '';
      }
    });
  }

  function handleDocumentClick(event) {
    const target = event.target;
    if (target instanceof HTMLElement) {
      if (target.closest('.notes-date-field') || target.closest('.notes-date-menu__panel')) {
        return;
      }
      if (target.closest('[data-notes-month-picker]') || target.closest('.notes-month-menu__panel')) {
        return;
      }
    }
    closeDateMenus();
    closeMonthMenu();
  }

  function addDays(date, offset) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + offset);
    return copy;
  }

  let selectedMonths = [];

  function initializeMonthSelection() {
    selectedMonths = [formatMonthValue(state.year, state.month)];
    updateMonthInput();
  }

  function showMonthMenu() {
    if (!monthMenu) {
      return;
    }
    closeMonthMenu();
    const months = Array.from({ length: 12 }).map((_, index) => {
      const month = index + 1;
      return {
        value: formatMonthValue(state.year, month),
        label: formatRocMonth(state.year, month),
      };
    });

    monthMenu.hidden = false;
    monthMenu.innerHTML = `
      <div class="notes-month-menu__panel">
        ${months
          .map(
            (item) => `
              <label class="notes-month-menu__item">
                <input type="checkbox" value="${item.value}" ${selectedMonths.includes(item.value) ? 'checked' : ''}>
                <span>${item.label}</span>
              </label>
            `
          )
          .join('')}
        <div class="notes-month-menu__actions">
          <button type="button" class="btn btn--secondary btn--small" data-action="clear-months">清除</button>
          <button type="button" class="btn btn--success btn--small" data-action="apply-months">套用</button>
        </div>
      </div>
    `;

    const panel = monthMenu.querySelector('.notes-month-menu__panel');
    if (!panel) {
      return;
    }
    panel.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.matches('input[type="checkbox"]')) {
        return;
      }
      const action = target.dataset.action;
      if (action === 'apply-months') {
        const checkboxes = Array.from(panel.querySelectorAll('input[type="checkbox"]'));
        selectedMonths = checkboxes.filter((box) => box.checked).map((box) => String(box.value));
        updateMonthInput();
        closeMonthMenu();
      } else if (action === 'clear-months') {
        selectedMonths = [];
        updateMonthInput();
        const checkboxes = panel.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach((box) => {
          box.checked = false;
        });
      }
    });
  }

  function closeMonthMenu() {
    if (monthMenu) {
      monthMenu.hidden = true;
      monthMenu.innerHTML = '';
    }
  }

  function updateMonthInput() {
    if (!monthInput) {
      return;
    }
    if (!selectedMonths.length) {
      monthInput.value = '';
      return;
    }
    const labels = selectedMonths
      .map((value) => {
        const [year, month] = value.split('-');
        return formatRocMonth(Number(year), Number(month));
      })
      .join('、');
    monthInput.value = labels;
  }

  function formatMonthValue(year, month) {
    return `${year}-${String(month).padStart(2, '0')}`;
  }

  function formatRocMonth(year, month) {
    const roc = year - 1911;
    return `${roc}年${month}月`;
  }
})();
