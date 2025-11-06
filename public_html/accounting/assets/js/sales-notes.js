(function () {
  'use strict';

  const CUSTOMERS_ENDPOINT = '../api/master-data/master_customers.php';
  const NOTES_UPLOAD_ENDPOINT = '../api/sales/notes_upload.php';
  const NOTES_DOWNLOAD_ENDPOINT = '../api/sales/notes_download.php';
  const NOTES_LATEST_ENDPOINT = '../api/sales/notes_latest.php';
  const NOTES_UPLOAD_ACCEPT = '.csv,.xls,.xlsx,.ods,.pdf,.zip';

  const root = document.body;
  if (!root) {
    return;
  }

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
  const uploadNotesButton = document.querySelector('[data-action="upload-notes"]');
  const downloadNotesButton = document.querySelector('[data-action="download-notes"]');
  let uploadInput = document.querySelector('[data-notes-upload-input]');

  const state = {
    year: parseInt(root.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(root.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    customers: [],
    customerMap: new Map(),
    customerNameMap: new Map(),
    records: [],
    editingIndex: null,
    editDraft: null,
    uploading: false,
    downloading: false,
  };

  setupUploadInput();
  const dateMenuState = {
    entry: createDateMenuState(),
    due: createDateMenuState(),
    deposit: createDateMenuState(),
  };

  init();

  async function init() {
    updateTitles();
    bindEvents();
    await loadCustomers();
    initializeDates();
    initializeMonthSelection();
    await loadInitialRecords();
    renderTable();
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

    if (uploadNotesButton) {
      uploadNotesButton.addEventListener('click', () => {
        if (state.uploading) {
          return;
        }
        setupUploadInput();
        if (uploadInput) {
          uploadInput.value = '';
          uploadInput.click();
        } else {
          alert('找不到上傳元件，請重新整理頁面後再試。');
        }
      });
    }

    if (uploadInput) {
      uploadInput.addEventListener('change', handleUploadInputChange);
    }

    if (downloadNotesButton) {
      downloadNotesButton.addEventListener('click', () => {
        if (!state.downloading) {
          downloadNotesFile();
        }
      });
    }

    document.addEventListener('click', handleDocumentClick, true);

    if (tableRows) {
      tableRows.addEventListener('click', handleTableClick);
    }
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
    state.records = [];
    state.editingIndex = null;
    state.editDraft = null;
    updateTitles();
    renderTable();
    loadInitialRecords();
  }

  function updateTitles() {
    const text = formatRocMonthTitle(state.year, state.month);
    if (tableTitleEl) {
      tableTitleEl.textContent = `${text}應收票據表`;
    }
  }

  function renderTable() {
    if (!tableRows) {
      return;
    }
    if (!Array.isArray(state.records) || state.records.length === 0) {
      tableRows.innerHTML = '<tr><td colspan="10" class="table-empty">尚無資料</td></tr>';
      return;
    }
    const sortedRecords = state.records
      .slice()
      .sort((a, b) => compareDateString(a.depositDate, b.depositDate));
    state.records = sortedRecords;

    let runningTotal = 0;
    const rows = sortedRecords
      .map((record, index) => {
        const monthsText = (record.months && record.months.length) ? record.months.join('、') : '';
        const numericAmount = toNumber(record.amount);
        const amountForSum = Number.isFinite(numericAmount) ? numericAmount : 0;
        runningTotal += amountForSum;
        record.amountFormatted = Number.isFinite(numericAmount) ? formatNumber(numericAmount) : '';
        record.total = runningTotal;
        record.totalFormatted = formatNumber(runningTotal);

        if (state.editingIndex === index && state.editDraft) {
          return renderEditableRow(record, index, runningTotal);
        }

        return `
          <tr data-index="${index}">
            <td>${escapeHtml(record.customerDisplay)}</td>
            <td>${escapeHtml(record.noteNumber)}</td>
            <td>${escapeHtml(record.entryDate)}</td>
            <td>${escapeHtml(record.dueDate)}</td>
            <td>${escapeHtml(record.depositDate)}</td>
            <td>${escapeHtml(monthsText)}</td>
            <td class="text-end">${escapeHtml(record.amountFormatted)}</td>
            <td class="text-end">${escapeHtml(formatNumber(runningTotal))}</td>
            <td>${escapeHtml(record.note)}</td>
            <td class="table__ops">
              <button type="button" class="btn btn--ghost btn--small" data-action="edit-note">編輯</button>
              <button type="button" class="btn btn--secondary btn--small" data-action="delete-note">刪除</button>
            </td>
          </tr>
        `;
      })
      .join('');
    tableRows.innerHTML = rows;
  }

  function renderEditableRow(record, index, runningTotal) {
    const monthsText = (record.months && record.months.length) ? record.months.join('、') : '';
    const draft = state.editDraft || {};
    const value = (key, fallback = '') => escapeAttribute(draft[key] ?? record[key] ?? fallback);
    const numberValue = (key, fallback = '') => escapeAttribute(
      draft[key] != null && draft[key] !== '' ? draft[key] : (record[key] != null ? record[key] : fallback)
    );
    return `
      <tr data-index="${index}" class="notes-edit-row">
        <td>${escapeHtml(record.customerDisplay)}</td>
        <td><div class="notes-edit-field"><input type="text" class="petty-input" data-edit-field="noteNumber" value="${value('noteNumber')}"></div></td>
        <td><div class="notes-edit-field"><input type="text" class="petty-input" data-edit-field="entryDate" value="${value('entryDate')}"></div></td>
        <td><div class="notes-edit-field"><input type="text" class="petty-input" data-edit-field="dueDate" value="${value('dueDate')}"></div></td>
        <td><div class="notes-edit-field"><input type="text" class="petty-input" data-edit-field="depositDate" value="${value('depositDate')}"></div></td>
        <td><div class="notes-edit-field"><input type="text" class="petty-input" data-edit-field="months" value="${escapeAttribute(draft.monthsText ?? monthsText)}"></div></td>
        <td><div class="notes-edit-field"><input type="number" class="petty-input" data-edit-field="amount" value="${numberValue('amount')}"></div></td>
        <td class="text-end">${escapeHtml(formatNumber(runningTotal))}</td>
        <td><div class="notes-edit-field"><input type="text" class="petty-input" data-edit-field="note" value="${value('note')}"></div></td>
        <td class="table__ops">
          <button type="button" class="btn btn--success btn--small" data-action="save-note">儲存</button>
          <button type="button" class="btn btn--ghost btn--small" data-action="cancel-edit">取消</button>
        </td>
      </tr>
    `;
  }

  function initializeDates() {
    const today = startOfDay(new Date());
    dateInputs.forEach((input) => {
      input.value = formatRocDate(today);
      const type = input.dataset.notesDate;
      if (type && dateMenuState[type]) {
        setDateSelection(type, today);
      }
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
      state.customerNameMap = new Map();
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
        if (name) {
          state.customerNameMap.set(normalizeCustomerNameKey(name), { code, name });
        }
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
    const storedName = customerInput.dataset.name;
    if (storedName) {
      customerInput.value = storedName;
      return;
    }
    const code = customerInput.dataset.code;
    if (code && state.customerMap.has(code)) {
      customerInput.value = state.customerMap.get(code) || '';
    }
  }

  function resolveCustomerInput(options) {
    if (!customerInput) {
      return;
    }
    const { strict = false, silent = false } = options || {};
    const raw = customerInput.value.trim();
    if (!raw) {
      customerInput.dataset.code = '';
      return;
    }

    const info = resolveCustomerInfo(raw);
    if (info.code) {
      applyCustomerSelection(info);
      return;
    }

    customerInput.dataset.code = '';
    customerInput.dataset.name = '';
    if (strict && !silent) {
      alert('找不到相符的客戶，請重新輸入。');
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
    const input = document.querySelector(`[data-notes-date="${type}"]`);
    if (!input) {
      return;
    }

    const parsed = parseDateText(String(input.value || ''));
    const stateForType = dateMenuState[type] || createDateMenuState();
    if (parsed) {
      setDateSelection(type, parsed);
    } else if (!stateForType.selected) {
      setDateSelection(type, startOfDay(new Date()));
    }

    renderDateMenu(type);
    menu.hidden = false;
  }

  function renderDateMenu(type) {
    const menu = dateMenus[type];
    if (!menu) {
      return;
    }
    const stateForType = dateMenuState[type] || createDateMenuState();
    const input = document.querySelector(`[data-notes-date="${type}"]`);
    const selectedDate = stateForType.selected ? startOfDay(stateForType.selected) : null;
    const today = startOfDay(new Date());
    const monthLabel = formatRocMonth(stateForType.viewYear, stateForType.viewMonth);
    const days = buildCalendarDays(stateForType.viewYear, stateForType.viewMonth);

    menu.innerHTML = `
      <div class="notes-date-menu__panel" data-notes-calendar="${type}">
        <div class="notes-calendar">
          <div class="notes-calendar__header">
            <div class="notes-calendar__nav-group">
              <button type="button" class="notes-calendar__nav-button" data-action="prev-month">‹</button>
              <div class="notes-calendar__month">${monthLabel}</div>
              <button type="button" class="notes-calendar__nav-button" data-action="next-month">›</button>
            </div>
            <button type="button" class="notes-calendar__today-button" data-action="pick-today">今天</button>
          </div>
          <div class="notes-calendar__weekdays">
            ${['日', '一', '二', '三', '四', '五', '六']
              .map((label) => `<span>${label}</span>`)
              .join('')}
          </div>
          <div class="notes-calendar__grid">
            ${days
              .map((item) => {
                const classes = ['notes-calendar__cell'];
                if (!item.currentMonth) {
                  classes.push('notes-calendar__cell--muted');
                }
                if (selectedDate && isSameDay(item.date, selectedDate)) {
                  classes.push('notes-calendar__cell--selected');
                } else if (isSameDay(item.date, today)) {
                  classes.push('notes-calendar__cell--today');
                }
                return `
                  <button type="button" class="${classes.join(' ')}" data-action="pick-day" data-date="${item.date.toISOString()}">
                    ${item.label}
                  </button>
                `;
              })
              .join('')}
          </div>
          <div class="notes-calendar__footer">
            <button type="button" class="notes-calendar__clear" data-action="clear-date">清除</button>
          </div>
        </div>
      </div>
    `;

    const panel = menu.querySelector('.notes-date-menu__panel');
    if (!panel) {
      return;
    }
    panel.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      const action = target.dataset.action;
      if (action === 'prev-month') {
        shiftCalendarMonth(type, -1);
        renderDateMenu(type);
      } else if (action === 'next-month') {
        shiftCalendarMonth(type, 1);
        renderDateMenu(type);
      } else if (action === 'pick-day') {
        const iso = target.dataset.date;
        if (iso) {
          const picked = startOfDay(new Date(iso));
          setDateSelection(type, picked);
          if (input) {
            input.value = formatRocDate(picked);
          }
          closeDateMenus();
        }
      } else if (action === 'pick-today') {
        const todayDate = startOfDay(new Date());
        setDateSelection(type, todayDate);
        if (input) {
          input.value = formatRocDate(todayDate);
        }
        closeDateMenus();
      } else if (action === 'clear-date') {
        const stateRef = dateMenuState[type];
        if (stateRef) {
          stateRef.selected = null;
        }
        if (input) {
          input.value = '';
        }
        closeDateMenus();
      }
    });
  }

  function buildCalendarDays(year, month) {
    const firstOfMonth = new Date(year, month - 1, 1);
    const start = new Date(firstOfMonth);
    const offset = start.getDay();
    start.setDate(start.getDate() - offset);
    const days = [];
    for (let i = 0; i < 42; i += 1) {
      const current = new Date(start);
      current.setDate(start.getDate() + i);
      days.push({
        date: current,
        label: current.getDate(),
        currentMonth: current.getMonth() === month - 1,
      });
    }
    return days;
  }

  function shiftCalendarMonth(type, offset) {
    const stateForType = dateMenuState[type];
    if (!stateForType) {
      return;
    }
    let { viewYear, viewMonth } = stateForType;
    viewMonth += offset;
    if (viewMonth < 1) {
      viewMonth = 12;
      viewYear -= 1;
    } else if (viewMonth > 12) {
      viewMonth = 1;
      viewYear += 1;
    }
    stateForType.viewYear = viewYear;
    stateForType.viewMonth = viewMonth;
  }

  function createDateMenuState(initialDate) {
    const base = startOfDay(initialDate instanceof Date ? initialDate : new Date());
    return {
      viewYear: base.getFullYear(),
      viewMonth: base.getMonth() + 1,
      selected: base,
    };
  }

  function setDateSelection(type, date) {
    const stateForType = dateMenuState[type];
    const base = startOfDay(date);
    if (stateForType) {
      stateForType.selected = base;
      stateForType.viewYear = base.getFullYear();
      stateForType.viewMonth = base.getMonth() + 1;
    } else {
      dateMenuState[type] = createDateMenuState(base);
    }
  }

  function startOfDay(date) {
    const copy = new Date(date);
    copy.setHours(0, 0, 0, 0);
    return copy;
  }

  function isSameDay(a, b) {
    return (
      a instanceof Date &&
      b instanceof Date &&
      !Number.isNaN(a.getTime()) &&
      !Number.isNaN(b.getTime()) &&
      a.getFullYear() === b.getFullYear() &&
      a.getMonth() === b.getMonth() &&
      a.getDate() === b.getDate()
    );
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

  let selectedMonths = [];

  function initializeMonthSelection() {
    selectedMonths = [formatMonthValue(state.year, state.month)];
    selectedMonths = sortMonths(selectedMonths);
    updateMonthInput();
  }

  function showMonthMenu() {
    if (!monthMenu) {
      return;
    }
    closeMonthMenu();
    renderMonthMenu();
    monthMenu.hidden = false;
  }

  function renderMonthMenu() {
    if (!monthMenu) {
      return;
    }
    selectedMonths = sortMonths(selectedMonths);
    const base = new Date(state.year, state.month - 1, 1);
    const months = Array.from({ length: 12 }).map((_, index) => {
      const date = new Date(base);
      date.setMonth(base.getMonth() - index);
      return {
        value: formatMonthValue(date.getFullYear(), date.getMonth() + 1),
        label: formatRocMonth(date.getFullYear(), date.getMonth() + 1),
      };
    });

    monthMenu.innerHTML = `
      <div class="notes-month-menu__panel">
        <div class="notes-month-menu__list">
          ${months
            .map(
              (item) => `
                <button type="button" class="notes-month-menu__option${selectedMonths.includes(item.value) ? ' notes-month-menu__option--active' : ''}" data-value="${item.value}">
                  ${item.label}
                </button>
              `
            )
            .join('')}
        </div>
        <div class="notes-month-menu__footer">
          <button type="button" class="notes-month-menu__clear" data-action="clear-month">清除</button>
          <div class="notes-month-menu__actions">
            <button type="button" class="notes-month-menu__action-button notes-month-menu__action-button--primary" data-action="apply-months">完成</button>
          </div>
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
      const option = target.closest('[data-value]');
      if (option) {
        toggleMonthSelection(option.dataset.value);
        updateMonthInput();
        option.classList.toggle('notes-month-menu__option--active', selectedMonths.includes(option.dataset.value));
        return;
      }
      const action = target.dataset.action;
      if (action === 'clear-month') {
        selectedMonths = [];
        updateMonthInput();
        renderMonthMenu();
      } else if (action === 'apply-months') {
        updateMonthInput();
        closeMonthMenu();
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
    const sorted = sortMonths(selectedMonths);
    let previousYear = null;
    const labels = sorted
      .map((value) => {
        const [yearString, monthString] = value.split('-');
        const year = Number(yearString);
        const month = Number(monthString);
        const rocYear = year - 1911;
        if (previousYear === year) {
          return `${month}月`;
        }
        previousYear = year;
        return `${rocYear}年${month}月`;
      })
      .join('、');
    monthInput.value = labels;
  }

  function toggleMonthSelection(value) {
    if (!value) {
      return;
    }
    if (selectedMonths.includes(value)) {
      selectedMonths = selectedMonths.filter((item) => item !== value);
    } else {
      selectedMonths = [...selectedMonths, value];
    }
    selectedMonths = sortMonths(selectedMonths);
  }

  function sortMonths(values) {
    return [...values].sort((a, b) => a.localeCompare(b));
  }

  function setupUploadInput() {
    if (!(uploadInput instanceof HTMLInputElement)) {
      uploadInput = document.querySelector('[data-notes-upload-input]');
    }
    if (!(uploadInput instanceof HTMLInputElement)) {
      uploadInput = document.createElement('input');
      uploadInput.type = 'file';
      uploadInput.hidden = true;
      uploadInput.setAttribute('data-notes-upload-input', 'dynamic');
      document.body.appendChild(uploadInput);
    }
    uploadInput.setAttribute('accept', NOTES_UPLOAD_ACCEPT);
  }

  function handleUploadInputChange(event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.files || !input.files.length) {
      return;
    }
    const file = input.files[0];
    if (!file) {
      return;
    }
    uploadNotesFile(file);
  }

  async function uploadNotesFile(file) {
    if (!file || state.uploading) {
      return;
    }
    const formData = new FormData();
    formData.append('year', String(state.year));
    formData.append('month', String(state.month));
    formData.append('file', file);

    state.uploading = true;
    try {
      const response = await fetch(NOTES_UPLOAD_ENDPOINT, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const result = await readJsonPayload(response);
      if (!response.ok || !result.ok) {
        throw new Error(result.error || '上傳失敗，請稍後再試。');
      }
      const parseMessage = typeof result.parse_error === 'string' ? result.parse_error.trim() : '';
      const records = normalizeUploadedRecords(result.records);
      if (records.length) {
        state.records = records;
        state.editingIndex = null;
        state.editDraft = null;
        renderTable();
      }
      let message = records.length
        ? `上傳成功，已讀取 ${records.length} 筆資料。`
        : '上傳成功，檔案已儲存。';
      if (parseMessage) {
        message += `\n${parseMessage}`;
      }
      alert(message);
    } catch (error) {
      console.error('[notes] upload failed', error);
      alert(error instanceof Error ? error.message : '上傳失敗，請稍後再試。');
    } finally {
      state.uploading = false;
      if (uploadInput) {
        uploadInput.value = '';
      }
    }
  }

  async function downloadNotesFile() {
    state.downloading = true;
    try {
      const params = new URLSearchParams({
        year: String(state.year),
        month: String(state.month),
      });
      const response = await fetch(`${NOTES_DOWNLOAD_ENDPOINT}?${params.toString()}`, {
        credentials: 'same-origin',
      });
      const contentType = response.headers.get('Content-Type') || '';
      if (!response.ok || contentType.includes('application/json')) {
        let message = '下載失敗，尚未找到檔案。';
        try {
          const payload = await response.json();
          if (payload && typeof payload.error === 'string' && payload.error) {
            message = payload.error;
          }
        } catch (_) {
          message = '下載失敗，請稍後再試。';
        }
        throw new Error(message);
      }

      const blob = await response.blob();
      let filename = extractFilename(response.headers.get('Content-Disposition'));
      if (!filename) {
        const fallbackExt = detectExtensionFromContentType(contentType);
        const paddedMonth = String(state.month).padStart(2, '0');
        filename = `notes_${state.year}_${paddedMonth}${fallbackExt ? `.${fallbackExt}` : ''}`;
      }
      triggerFileDownload(blob, filename);
    } catch (error) {
      console.error('[notes] download failed', error);
      alert(error instanceof Error ? error.message : '下載失敗，請稍後再試。');
    } finally {
      state.downloading = false;
    }
  }

  function normalizeUploadedRecords(data) {
    if (!Array.isArray(data)) {
      return [];
    }
    return data
      .map((item) => {
        const months = Array.isArray(item?.months)
          ? item.months.map((value) => String(value || '').trim()).filter(Boolean)
          : parseMonthList(String(item?.months || ''));
        const amount = toNumber(item?.amount);
        const totalRaw = toNumber(item?.total);
        const total = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : amount;
        const noteNumber = toText(item?.note_number ?? item?.number ?? item?.ticket);
        const customerRaw = toText(item?.customer ?? item?.customer_code ?? item?.customer_name);
        const customerNameField = toText(item?.customer_name ?? item?.name);
        const customerInfo = resolveCustomerInfo(customerRaw || customerNameField);
        const customerDisplay = customerInfo.name || customerNameField || customerInfo.display || customerRaw;
        const hasContent =
          customerDisplay ||
          noteNumber ||
          amount !== null ||
          total !== null ||
          months.length ||
          toText(item?.note);
        if (!hasContent) {
          return null;
        }
        return {
          customerCode: customerInfo.code,
          customerName: customerDisplay,
          customerDisplay,
          noteNumber,
          entryDate: toText(item?.entry_date),
          dueDate: toText(item?.due_date),
          depositDate: toText(item?.deposit_date),
          months,
          amount,
          total,
          amountFormatted: amount !== null ? formatNumber(amount) : '',
          totalFormatted: total !== null ? formatNumber(total) : '',
          note: toText(item?.note),
        };
      })
      .filter(Boolean);
  }

  function toText(value) {
    return String(value || '').trim();
  }

  function toNumber(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }
    const text = String(value || '').trim();
    if (!text) {
      return null;
    }
    const normalized = text.replace(/[,，\s$]/g, '');
    if (!normalized) {
      return null;
    }
    const num = Number(normalized);
    return Number.isFinite(num) ? num : null;
  }

  function parseMonthList(text) {
    const trimmed = String(text || '').trim();
    if (!trimmed) {
      return [];
    }
    return trimmed
      .split(/[,，、\s]+/u)
      .map((part) => part.trim())
      .filter(Boolean);
  }

  function handleTableClick(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const action = target.dataset.action;
    if (!action) {
      return;
    }
    const row = target.closest('tr');
    if (!row) {
      return;
    }
    const index = Number(row.dataset.index);
    if (!Number.isFinite(index) || index < 0 || index >= state.records.length) {
      return;
    }

    if (action === 'edit-note') {
      beginEditRecord(index);
    } else if (action === 'cancel-edit') {
      cancelEditRecord();
    } else if (action === 'save-note') {
      saveEditedRecord(index, row);
    } else if (action === 'delete-note') {
      deleteRecord(index);
    }
  }

  function beginEditRecord(index) {
    state.editingIndex = index;
    const record = state.records[index];
    state.editDraft = {
      ...record,
      monthsText: Array.isArray(record.months) ? record.months.join('、') : '',
    };
    renderTable();
  }

  function cancelEditRecord() {
    state.editingIndex = null;
    state.editDraft = null;
    renderTable();
  }

  function saveEditedRecord(index, row) {
    const inputs = row.querySelectorAll('[data-edit-field]');
    const draft = {};
    inputs.forEach((input) => {
      if (!(input instanceof HTMLInputElement)) {
        return;
      }
      const field = input.dataset.editField;
      if (!field) {
        return;
      }
      draft[field] = input.value;
    });

    const record = { ...state.records[index] };
    record.noteNumber = toText(draft.noteNumber);
    record.entryDate = toText(draft.entryDate);
    record.dueDate = toText(draft.dueDate);
    record.depositDate = toText(draft.depositDate);
    record.months = parseMonthList(draft.months || draft.monthsText || '');
    record.amount = toNumber(draft.amount);
    record.amountFormatted = Number.isFinite(record.amount) ? formatNumber(record.amount) : '';
    record.total = null;
    record.totalFormatted = '';
    record.note = toText(draft.note);

    state.records[index] = record;
    state.editingIndex = null;
    state.editDraft = null;
    renderTable();
  }

  function deleteRecord(index) {
    if (!window.confirm('確定要刪除此筆應收票據嗎？')) {
      return;
    }
    state.records.splice(index, 1);
    if (state.editingIndex === index) {
      state.editingIndex = null;
      state.editDraft = null;
    }
    renderTable();
  }

  function compareDateString(a, b) {
    const parsedA = parseDateToValue(a);
    const parsedB = parseDateToValue(b);
    if (parsedA !== parsedB) {
      return parsedA - parsedB;
    }
    return 0;
  }

  function parseDateToValue(value) {
    const text = String(value || '').trim();
    if (!text) {
      return Number.MAX_SAFE_INTEGER;
    }
    const rocMatch = text.match(/^(\d{2,3})年(\d{1,2})月(\d{1,2})日$/);
    if (rocMatch) {
      const year = Number(rocMatch[1]) + 1911;
      const month = Number(rocMatch[2]);
      const day = Number(rocMatch[3]);
      return year * 10000 + month * 100 + day;
    }
    const rocSlash = text.match(/^(\d{2,3})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (rocSlash) {
      const year = Number(rocSlash[1]) + 1911;
      const month = Number(rocSlash[2]);
      const day = Number(rocSlash[3]);
      return year * 10000 + month * 100 + day;
    }
    const isoMatch = text.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);
    if (isoMatch) {
      const year = Number(isoMatch[1]);
      const month = Number(isoMatch[2]);
      const day = Number(isoMatch[3]);
      return year * 10000 + month * 100 + day;
    }
    return Number.MAX_SAFE_INTEGER;
  }

  function applyCustomerSelection(info) {
    if (!customerInput) {
      return;
    }
    const display = info.name || info.display || info.code || '';
    customerInput.value = display;
    customerInput.dataset.code = info.code || '';
    customerInput.dataset.name = info.name || '';
  }

  function resolveCustomerInfo(raw) {
    const text = toText(raw);
    if (!text) {
      return { code: '', name: '', display: '' };
    }

    const tokens = splitCustomerTokens(text);
    for (const token of tokens) {
      if (state.customerMap.has(token)) {
        const name = state.customerMap.get(token) || '';
        return { code: token, name, display: name || token };
      }
    }

    const normalizedName = normalizeCustomerNameKey(text);
    if (normalizedName && state.customerNameMap.has(normalizedName)) {
      const matched = state.customerNameMap.get(normalizedName);
      if (matched && matched.code) {
        const resolvedName = matched.name || state.customerMap.get(matched.code) || '';
        return { code: matched.code, name: resolvedName, display: resolvedName || matched.code };
      }
    }

    const codeMatch = text.match(/([A-Za-z0-9]{2,})/);
    if (codeMatch) {
      const candidate = codeMatch[1];
      if (state.customerMap.has(candidate)) {
        const name = state.customerMap.get(candidate) || '';
        return { code: candidate, name, display: name || candidate };
      }
    }

    return { code: '', name: '', display: text };
  }

  function splitCustomerTokens(text) {
    return text
      .replace(/[（）()]/g, ' ')
      .split(/[\s,，、／\/\-－﹣–—~～]+/u)
      .map((token) => token.trim())
      .filter(Boolean);
  }

  function normalizeCustomerNameKey(value) {
    return String(value || '')
      .replace(/[（）()]/g, '')
      .replace(/\s+/gu, '')
      .toLowerCase();
  }

  async function readJsonPayload(response) {
    const text = await response.text();
    if (!text) {
      return {};
    }
    try {
      return JSON.parse(text);
    } catch (error) {
      console.error('[notes] parse json failed', error);
      return {};
    }
  }

  function extractFilename(contentDisposition) {
    if (!contentDisposition) {
      return null;
    }
    const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match && utf8Match[1]) {
      try {
        return decodeURIComponent(utf8Match[1]);
      } catch (_) {
        // ignore decode error
      }
    }
    const quotedMatch = contentDisposition.match(/filename="([^"]+)"/i);
    if (quotedMatch && quotedMatch[1]) {
      return quotedMatch[1];
    }
    return null;
  }

  function detectExtensionFromContentType(contentType) {
    const map = [
      { key: 'spreadsheetml', ext: 'xlsx' },
      { key: 'ms-excel', ext: 'xls' },
      { key: 'oasis.opendocument.spreadsheet', ext: 'ods' },
      { key: 'text/csv', ext: 'csv' },
      { key: 'application/pdf', ext: 'pdf' },
      { key: 'application/zip', ext: 'zip' },
    ];
    const lowered = contentType.toLowerCase();
    const found = map.find((entry) => lowered.includes(entry.key));
    return found ? found.ext : '';
  }

  function triggerFileDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename || 'download';
    document.body.appendChild(anchor);
    anchor.click();
    setTimeout(() => {
      URL.revokeObjectURL(url);
      anchor.remove();
    }, 0);
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatNumber(value) {
    if (!Number.isFinite(value)) {
      return '';
    }
    return value.toLocaleString('zh-TW');
  }

  function escapeAttribute(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function formatMonthValue(year, month) {
    return `${year}-${String(month).padStart(2, '0')}`;
  }

  function formatRocMonth(year, month) {
    const roc = year - 1911;
    return `${roc}年${month}月`;
  }

  async function loadInitialRecords() {
    try {
      const params = new URLSearchParams({
        year: String(state.year),
        month: String(state.month),
      });
      const response = await fetch(`${NOTES_LATEST_ENDPOINT}?${params.toString()}`, {
        credentials: 'same-origin',
      });
      if (!response.ok) {
        return;
      }
      const payload = await response.json();
      if (Array.isArray(payload?.records) && payload.records.length) {
        state.records = normalizeUploadedRecords(payload.records);
        state.editingIndex = null;
        state.editDraft = null;
        renderTable();
      }
      if (payload?.parse_error) {
        console.info('[notes] parse info:', payload.parse_error);
      }
    } catch (error) {
      console.error('[notes] load initial records failed', error);
    }
  }

})();
