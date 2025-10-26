(function () {
  'use strict';

  const LIST_ENDPOINT = '../api/petty-cash/list.php';
  const CODE_SOURCES = [
    {
      endpoint: '../api/master-data/master_codes.php?action=list',
      extract: (row) => {
        const code = ((row && (row.code || row.value)) || '').trim();
        const label = ((row && (row.label || row.name)) || '').trim();
        if (!code || !label) return null;
        const normalized = ((row && row.normalized_code) || '').trim() || normalizeCode(code);
        const sources = Array.isArray(row && row.sources) ? row.sources : [];
        return {
          value: code,
          label,
          normalized,
          meta: {
            sources,
          },
        };
      },
    },
  ];
const SUBJECT_SOURCES = [
  {
    endpoint: '../api/master-data/account_mappings.php?action=list',
    extract: (row) => {
      const mapping = (row.mapping || '').trim();
      if (!mapping) return null;
      return { value: mapping, label: mapping, normalized: mapping };
    },
  },
];

  const root = document.body;
  const monthTitleEl = document.querySelector('[data-month-title]');
  const tableEl = document.querySelector('[data-petty-table]');
  const balanceEl = document.querySelector('[data-balance]');
  const formEl = document.querySelector('[data-petty-form]');
  const uploadBtn = document.querySelector('[data-action="upload"]');
  const fileInput = document.querySelector('[data-file-input]');
  const navButtons = document.querySelectorAll('[data-nav]');
  const messageEl = document.querySelector('[data-message]');
  const tableMonthEl = document.querySelector('[data-table-month]');
  const todayInputs = document.querySelectorAll('[data-default-today]');
  const prevDayInputs = document.querySelectorAll('[data-default-prev-day]');
  const codeDatalist = document.getElementById('petty-code-list');
  const subjectDatalist = document.getElementById('petty-subject-list');
  const entryInput = document.getElementById('entry-date');
  const tradeInput = document.getElementById('trade-date');
  const tradeMonthSelect = document.getElementById('trade-month');
  const codeInput = document.getElementById('entry-code');
  const balanceDisplayInput = document.getElementById('balance');
  const openingBalanceEl = document.querySelector('[data-opening-balance]');
  const editOpeningBtn = document.querySelector('[data-action="edit-opening"]');
  const pickerButtons = document.querySelectorAll('[data-picker]');
  const hiddenDatePickers = { entry: null, trade: null };
  const codeLookup = {
    byNormalized: new Map(),
    byDisplay: new Map(),
  };

  if (!tableEl || !monthTitleEl) {
    return;
  }

  const state = {
    year: parseInt(root.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(root.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    loading: false,
    updatingOpening: false,
    openingBalance: 0,
    creating: false,
  };

  let messageTimer = null;

  init();

  function init() {
    bindEvents();
    updateMonthTitle();
    loadRecords();
    fillTodayDefaults();
    fillPrevDayDefaults();
    fillTradeMonthDefaults();
    populateMonthOptions();
    loadReferenceData();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.nav;
        if (dir === 'prev') {
          goToPreviousMonth();
        } else if (dir === 'next') {
          goToNextMonth();
        }
      });
    });

    if (formEl) {
      formEl.addEventListener('submit', handleFormSubmit);
    }

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', handleFileSelect);
    }

    pickerButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const target = button.dataset.picker;
        showDatePicker(target, button);
      });
    });

    if (entryInput) {
      entryInput.addEventListener('blur', syncTradeValuesFromEntry);
    }
    if (tradeInput) {
      tradeInput.addEventListener('blur', () => {
        const parsed = parseRocDate(tradeInput.value, state.year);
        if (!parsed) return;
        updateTradeMonthInputsFromDate(parsed);
        setHiddenPickerValue('trade', parsed);
      });
    }
    if (tradeMonthSelect) {
      tradeMonthSelect.addEventListener('change', () => {
        // selection persists automatically
      });
    }
    if (codeInput) {
      codeInput.addEventListener('input', normalizeCodeInputValue);
      codeInput.addEventListener('change', normalizeCodeInputValue);
      codeInput.addEventListener('blur', normalizeCodeInputValue);
    }
    if (editOpeningBtn) {
      editOpeningBtn.addEventListener('click', handleEditOpeningBalance);
    }
  }

  function loadReferenceData() {
    if (codeDatalist) {
      loadDatalist(codeDatalist, CODE_SOURCES, {
        includeLabel: true,
        limit: 200,
        useCombinedValue: true,
        onItemsReady: storeCodeLookup,
      });
    }
    if (subjectDatalist) {
      loadDatalist(subjectDatalist, SUBJECT_SOURCES, { includeLabel: false, limit: 200 });
    }
  }

  function handleFormSubmit(event) {
    event.preventDefault();
    if (state.loading || state.updatingOpening || state.creating) {
      return;
    }
    const payload = serializeFormData();
    if (!payload) {
      return;
    }
    submitEntry(payload);
  }

  function serializeFormData() {
    if (!formEl) return null;

    const entryRaw = entryInput ? entryInput.value : '';
    const tradeRaw = tradeInput ? tradeInput.value : '';

    const entryDate = parseRocDate(entryRaw, state.year);
    if (!entryDate) {
      showMessage('error', '登記日期格式錯誤，請確認是否為民國年');
      return null;
    }

    let tradeDate = parseRocDate(tradeRaw, state.year);
    if (!tradeDate) {
      tradeDate = new Date(entryDate);
    }

    const codeValue = codeInput ? (codeInput.dataset.codeValue || codeInput.value || '') : '';
    const code = codeValue.trim();
    if (!code) {
      showMessage('error', '請輸入代號');
      return null;
    }

    const subjectInput = document.getElementById('entry-subject');
    const subject = subjectInput ? subjectInput.value.trim() : '';
    if (!subject) {
      showMessage('error', '請輸入會計科目');
      return null;
    }

    const noteInput = document.getElementById('entry-note');
    const note = noteInput ? noteInput.value.trim() : '';

    const incomeInput = document.getElementById('income');
    const expenseInput = document.getElementById('expense');
    const advanceInput = document.getElementById('advance');

    const income = parseAmount(incomeInput ? incomeInput.value : '', '收入金額');
    if (income === null) return null;
    const expense = parseAmount(expenseInput ? expenseInput.value : '', '支出金額');
    if (expense === null) return null;
    const advance = parseAmount(advanceInput ? advanceInput.value : '', '代墊款');
    if (advance === null) return null;

    if (income === 0 && expense === 0 && advance === 0) {
      showMessage('error', '收入、支出、代墊款不可同時為 0');
      return null;
    }

    return {
      entry_date: toIsoDate(entryDate),
      transaction_date: toIsoDate(tradeDate),
      transaction_month: computeTransactionMonth(tradeDate || entryDate),
      code,
      subject,
      note,
      income,
      expense,
      advance,
      advance_status: '',
    };
  }

  function submitEntry(payload) {
    const request = {
      ...payload,
      year: state.year,
      month: state.month,
    };

    state.creating = true;

    fetch('../api/petty-cash/voucher_create.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(request),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((result) => {
        if (!result || result.ok !== true) {
          throw new Error(result?.error || '新增失敗');
        }
        showMessage('success', '已新增零用金記錄');
        formEl.reset();
        fillTodayDefaults();
        fillPrevDayDefaults();
        fillTradeMonthDefaults();
        if (codeInput) {
          codeInput.value = '';
          codeInput.dataset.codeValue = '';
        }
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '新增失敗');
      })
      .finally(() => {
        state.creating = false;
      });
  }

  function fillTodayDefaults() {
    const today = new Date();
    const [rocYear, padMonth] = toRocYearMonth(today.getFullYear(), today.getMonth() + 1);
    const day = String(today.getDate()).padStart(2, '0');
    const formatted = `${rocYear}年${parseInt(padMonth, 10)}月${parseInt(day, 10)}日`;
    todayInputs.forEach((input) => {
      if (input instanceof HTMLInputElement) {
        input.value = formatted;
      }
    });
    updateTradeMonthInputsFromDate(today);
    setHiddenPickerValue('entry', today);
  }

  function fillPrevDayDefaults() {
    const today = new Date();
    today.setDate(today.getDate() - 1);
    const formatted = formatRocString(today);
    prevDayInputs.forEach((input) => {
      if (input instanceof HTMLInputElement) {
        input.value = formatted;
      }
    });
    updateTradeMonthInputsFromDate(today);
    setHiddenPickerValue('trade', today);
  }

  function fillTradeMonthDefaults() {
    const today = new Date();
    const [rocYear, padMonth] = toRocYearMonth(today.getFullYear(), today.getMonth() + 1);
    const value = `${rocYear}年${parseInt(padMonth, 10)}月`;
    populateMonthOptions();
    if (tradeMonthSelect) {
      tradeMonthSelect.value = value;
    }
  }

  function goToPreviousMonth() {
    let { year, month } = state;
    month -= 1;
    if (month < 1) {
      month = 12;
      year -= 1;
    }
    state.year = year;
    state.month = month;
    updateMonthTitle();
    loadRecords();
  }

  function goToNextMonth() {
    let { year, month } = state;
    month += 1;
    if (month > 12) {
      month = 1;
      year += 1;
    }
    state.year = year;
    state.month = month;
    updateMonthTitle();
    loadRecords();
  }

  function updateMonthTitle() {
    const [rocYear, padMonth] = toRocYearMonth(state.year, state.month);
    monthTitleEl.textContent = `${rocYear}年${padMonth}月零用金記錄`;
    if (tableMonthEl) {
      tableMonthEl.textContent = `${rocYear}年${padMonth}月零用金紀錄`;
    }
    populateMonthOptions();
  }

  function loadRecords() {
    if (state.loading) return;
    state.loading = true;
    setTableLoading();
    if (openingBalanceEl) {
      openingBalanceEl.textContent = '--';
    }

    const params = new URLSearchParams({
      year: String(state.year),
      month: String(state.month),
    });

    fetch(`${LIST_ENDPOINT}?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || payload.ok !== true || !payload.data) {
          throw new Error(payload?.error || '未知的回應格式');
        }
        renderRecords(payload.data);
      })
      .catch((error) => {
        renderErrorRow(error.message || '資料載入失敗');
        showMessage('error', error.message || '資料載入失敗');
      })
      .finally(() => {
        state.loading = false;
      });
  }

  function renderRecords(data) {
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    const records = Array.isArray(data.records) ? data.records : [];

    if (typeof data.opening_balance === 'number') {
      setOpeningBalance(data.opening_balance);
    } else if (!Number.isFinite(state.openingBalance)) {
      setOpeningBalance(0);
    } else {
      setOpeningBalance(state.openingBalance);
    }

    if (balanceEl) {
      const balance = typeof data.remaining_balance === 'number' ? data.remaining_balance : 0;
      balanceEl.textContent = formatCurrency(balance);
    }

    if (!records.length) {
      tbody.innerHTML = '<tr><td colspan="12" class="table-empty">本月份尚無資料</td></tr>';
      if (balanceDisplayInput) {
        balanceDisplayInput.value = formatCurrency(state.openingBalance);
      }
      return;
    }

    const rows = records.map((record) => renderRow(record)).join('');
    tbody.innerHTML = rows;

    if (balanceDisplayInput) {
      const lastRecord = records.length ? records[records.length - 1] : null;
      const latestBalance = lastRecord ? toNumber(lastRecord.balance) : state.openingBalance;
      balanceDisplayInput.value = formatCurrency(latestBalance);
    }
  }

  function renderRow(record) {
    if (record.is_summary) {
      return `
        <tr class="petty-summary">
          <td>${escapeHtml(record.label || '小計')}</td>
          <td colspan="8"></td>
          <td class="petty-balance">${formatCurrency(record.balance || 0)}</td>
          <td colspan="2"></td>
        </tr>
      `;
    }

    const income = toNumber(record.income);
    const expense = toNumber(record.expense);
    const advance = toNumber(record.advance);
    const balance = toNumber(record.balance);
    const incomeClass = income > 0 ? 'petty-income' : '';
    const expenseClass = expense > 0 ? 'petty-expense' : '';
    const balanceClass = 'petty-balance';

    const statusHtml = formatStatus(record.advance_status);

    return `
      <tr>
        <td>${formatRocDate(record.entry_date)}</td>
        <td>${escapeHtml(record.code)}</td>
        <td>${escapeHtml(record.subject)}</td>
        <td>${formatRocDate(record.transaction_date)}</td>
        <td>${formatTransactionMonth(record.transaction_month)}</td>
        <td class="${incomeClass}">${formatCurrency(income)}</td>
        <td class="${expenseClass}">${formatCurrency(expense)}</td>
        <td>${advance ? formatCurrency(advance) : ''}</td>
        <td class="petty-status">${statusHtml}</td>
        <td class="${balanceClass}">${formatCurrency(balance)}</td>
        <td>${escapeHtml(record.note)}</td>
        <td class="table__ops">
          <button type="button" class="btn btn--ghost" data-action="edit" data-id="${escapeHtml(record.id || '')}" disabled>編輯</button>
          <button type="button" class="btn btn--secondary" data-action="delete" data-id="${escapeHtml(record.id || '')}" disabled>刪除</button>
        </td>
      </tr>
    `;
  }

  function renderErrorRow(message) {
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="12" class="table-empty">${escapeHtml(message)}</td></tr>`;
  }

  function setTableLoading() {
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="12" class="table-empty">資料載入中…</td></tr>';
  }

  function formatStatus(status) {
    if (!status) return '';
    if (status === '未回收') {
      return `<span class="petty-status__dot"></span><span>${escapeHtml(status)}</span>`;
    }
    return escapeHtml(status);
  }

  function populateMonthOptions() {
    if (!tradeMonthSelect) return;
    const base = new Date(state.year, state.month - 1, 1);
    const months = [];
    for (let offset = -12; offset <= 12; offset += 1) {
      const date = new Date(base);
      date.setMonth(base.getMonth() + offset);
      const [rocYear, padMonth] = toRocYearMonth(date.getFullYear(), date.getMonth() + 1);
      months.push(`${rocYear}年${parseInt(padMonth, 10)}月`);
    }
    months.reverse();
    tradeMonthSelect.innerHTML = months
      .map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`)
      .join('');
  }

  function showDatePicker(type, trigger) {
    const targetInput = type === 'entry' ? entryInput : tradeInput;
    if (!targetInput) return;
    const hiddenPicker = ensureHiddenPicker(type);
    const parsed = parseRocDate(targetInput.value, state.year);
    const date = parsed || new Date();
    hiddenPicker.value = toIsoDate(date);
    positionHiddenPicker(hiddenPicker, trigger);
    requestAnimationFrame(() => {
      if (typeof hiddenPicker.showPicker === 'function') {
        hiddenPicker.showPicker();
      } else {
        hiddenPicker.click();
      }
    });
  }

  function ensureHiddenPicker(type) {
    if (hiddenDatePickers[type]) {
      return hiddenDatePickers[type];
    }
    const input = document.createElement('input');
    input.type = 'date';
    input.className = 'petty-hidden-date';
    document.body.appendChild(input);
    input.addEventListener('change', () => applyHiddenPickerDate(type, input.value));
    hiddenDatePickers[type] = input;
    return input;
  }

  function positionHiddenPicker(picker, trigger) {
    const rect = trigger.getBoundingClientRect();
    picker.style.top = `${window.scrollY + rect.bottom}px`;
    picker.style.left = `${window.scrollX + rect.left}px`;
    picker.style.width = '1px';
    picker.style.height = '1px';
  }

  function applyHiddenPickerDate(type, value) {
    if (!value) return;
    const targetInput = type === 'entry' ? entryInput : tradeInput;
    if (!targetInput) return;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return;
    targetInput.value = formatRocString(date);
    if (type === 'entry') {
      updateTradeMonthInputsFromDate(date);
      if (tradeInput) {
        const prev = new Date(date);
        prev.setDate(prev.getDate() - 1);
        tradeInput.value = formatRocString(prev);
        setHiddenPickerValue('trade', prev);
      }
    } else if (type === 'trade') {
      updateTradeMonthInputsFromDate(date);
    }
    setHiddenPickerValue(type, date);
  }

  function setHiddenPickerValue(type, date) {
    const picker = ensureHiddenPicker(type);
    picker.value = toIsoDate(date);
  }

  function syncTradeValuesFromEntry() {
    if (!entryInput) return;
    const parsed = parseRocDate(entryInput.value, state.year);
    if (!parsed) return;
    const entryDate = parsed;
    updateTradeMonthInputsFromDate(entryDate);
    if (tradeInput) {
      const prev = new Date(entryDate);
      prev.setDate(prev.getDate() - 1);
      tradeInput.value = formatRocString(prev);
    }
  }

  function formatRocDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return escapeHtml(value);
    const rocYear = date.getFullYear() - 1911;
    const month = String(date.getMonth() + 1);
    const day = String(date.getDate());
    return `${rocYear}年${month}月${day}日`;
  }

  function formatCurrency(value) {
    const num = Math.max(0, toNumber(value));
    return num.toLocaleString('zh-TW', { minimumFractionDigits: 0 });
  }

  function toNumber(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) {
      return 0;
    }
    return Math.round(num);
  }

  function parseAmount(value, label) {
    if (value === null || value === undefined) {
      return 0;
    }
    const text = String(value).replace(/,/g, '').trim();
    if (text === '') {
      return 0;
    }
    if (!/^[-+]?\d+(\.\d+)?$/.test(text)) {
      showMessage('error', `${label}僅能輸入數字`);
      return null;
    }
    const num = Number(text);
    if (!Number.isFinite(num) || !Number.isInteger(num)) {
      showMessage('error', `${label}僅能輸入整數`);
      return null;
    }
    if (num < 0) {
      showMessage('error', `${label}不可為負數`);
      return null;
    }
    return num;
  }

  function toRocYearMonth(year, month) {
    const rocYear = year - 1911;
    const padMonth = month.toString().padStart(2, '0');
    return [rocYear, padMonth];
  }

  function computeTransactionMonth(date) {
    if (!date || !(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    const rocYear = date.getFullYear() - 1911;
    if (rocYear <= 0) {
      return '';
    }
    const month = date.getMonth() + 1;
    return `${String(rocYear).padStart(3, '0')}${String(month).padStart(2, '0')}`;
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function populateDatalist(datalist, items, config = {}) {
    if (!datalist) return;
    const seen = new Set();
    const html = items
      .filter((item) => item && item.value)
      .filter((item) => {
        const value = String(item.value).trim();
        const label = item.label ? String(item.label).trim() : '';
        const key = (item.normalized || (config.normalize ? config.normalize(value) : value)).trim();
        if (!value) return false;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      })
      .map((item) => {
        const value = String(item.value || '').trim();
        const label = item.label ? String(item.label).trim() : '';
        const showLabel = config.includeLabel !== false && label;
        const combined = showLabel ? `${value} — ${label}` : value;
        const optionValue = config.useCombinedValue ? combined : value;
        const labelAttr = !config.useCombinedValue && combined ? ` label="${escapeHtml(combined)}"` : '';
        const dataAttr = config.useCombinedValue ? ` data-code="${escapeHtml(value)}"` : '';
        return `<option value="${escapeHtml(optionValue)}"${labelAttr}${dataAttr}></option>`;
      })
      .join('');
    datalist.innerHTML = html;
  }

  function storeCodeLookup(items) {
    codeLookup.byNormalized.clear();
    codeLookup.byDisplay.clear();
    items.forEach((item) => {
      const value = String(item.value || '').trim();
      if (!value) return;
      const label = item.label ? String(item.label).trim() : '';
      const normalized = normalizeCode(value);
      const display = label ? `${value} — ${label}` : value;
      codeLookup.byNormalized.set(normalized, { value, label, display });
      codeLookup.byDisplay.set(display, { value, label, display });
    });
  }

  function loadDatalist(datalist, sources, config = {}) {
    const tasks = sources.map((source) =>
      fetch(source.endpoint, { credentials: 'same-origin' })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))))
        .then((payload) => {
          const rows = Array.isArray(payload?.data) ? payload.data : [];
          return rows
            .map(source.extract)
            .filter((item) => item && item.value);
        })
        .catch(() => [])
    );
    Promise.all(tasks)
      .then((results) => {
        const merged = results.flat();
        const map = new Map();
        merged.forEach((item) => {
          if (!item || !item.value) return;
          const value = String(item.value).trim();
          if (!value) return;
          const label = item.label ? String(item.label).trim() : '';
          const key = (item.normalized || (config.normalize ? config.normalize(value) : value)).trim();
          if (!key) return;
          const current = map.get(key);
          if (!current || (!current.label && label)) {
            map.set(key, { value, label });
          }
        });
        const cleaned = Array.from(map.values())
          .map((item) => ({
            value: item.value,
            label: item.label || '',
          }))
          .sort((a, b) => {
            const an = Number(a.value);
            const bn = Number(b.value);
            if (Number.isFinite(an) && Number.isFinite(bn)) {
              return an - bn;
            }
            return a.value.localeCompare(b.value, 'zh-Hant');
          });
        if (config.limit && Number.isFinite(config.limit)) {
          cleaned.splice(config.limit);
        }
        populateDatalist(datalist, cleaned, config);
        if (typeof config.onItemsReady === 'function') {
          config.onItemsReady(cleaned);
        }
      })
      .catch(() => {});
  }

  function handleEditOpeningBalance() {
    if (state.loading || state.updatingOpening) {
      return;
    }
    const defaultValue = formatNumberInput(state.openingBalance);
    const raw = window.prompt('請輸入期初餘額（可負數）', defaultValue);
    if (raw === null) {
      return;
    }
    const parsed = parseCurrencyInput(raw);
    if (!Number.isFinite(parsed)) {
      showMessage('error', '請輸入有效的金額');
      return;
    }
    const rounded = Math.round(parsed);
    if (!Number.isInteger(rounded)) {
      showMessage('error', '期初餘額僅能輸入整數');
      return;
    }
    if (rounded < 0) {
      showMessage('error', '期初餘額不可為負數');
      return;
    }
    updateOpeningBalance(rounded);
  }

  function updateOpeningBalance(amount) {
    state.updatingOpening = true;
    fetch('../api/petty-cash/opening_balance.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        year: state.year,
        month: state.month,
        opening_balance: amount,
      }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || payload.ok !== true) {
          throw new Error(payload?.error || '更新期初餘額失敗');
        }
        const next = Number(payload?.data?.opening_balance);
        if (Number.isFinite(next)) {
          setOpeningBalance(next);
        } else {
          setOpeningBalance(amount);
        }
        showMessage('success', '期初餘額已更新');
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '更新期初餘額失敗');
      })
      .finally(() => {
        state.updatingOpening = false;
      });
  }

  function normalizeCode(code) {
    const trimmed = String(code || '').trim();
    if (!trimmed) return '';
    const normalized = trimmed.replace(/^0+/, '');
    return normalized || '0';
  }

  function normalizeCodeInputValue(event) {
    if (!codeInput) return;
    const raw = codeInput.value.trim();
    if (!raw) {
      codeInput.value = '';
      codeInput.dataset.codeValue = '';
      return;
    }
    const displayEntry = codeLookup.byDisplay.get(raw);
    if (displayEntry) {
      codeInput.value = displayEntry.display;
      codeInput.dataset.codeValue = displayEntry.value;
      return;
    }
    if (event && event.type === 'input') {
      codeInput.dataset.codeValue = raw;
      return;
    }
    const normalized = normalizeCode(raw);
    if (!normalized) {
      codeInput.value = '';
      codeInput.dataset.codeValue = '';
      return;
    }
    const entry = codeLookup.byNormalized.get(normalized);
    if (entry) {
      codeInput.value = entry.display;
      codeInput.dataset.codeValue = entry.value;
    } else {
      codeInput.value = normalized;
      codeInput.dataset.codeValue = normalized;
    }
  }

  function showMessage(type, text) {
    if (!messageEl) return;
    if (messageTimer) {
      window.clearTimeout(messageTimer);
      messageTimer = null;
    }
    if (!text) {
      messageEl.hidden = true;
      messageEl.className = 'notice';
      messageEl.textContent = '';
      return;
    }
    const allowed = ['success', 'error', 'info'];
    const modifier = allowed.includes(type) ? type : 'info';
    messageEl.hidden = false;
    messageEl.className = `notice notice--${modifier}`;
    messageEl.textContent = text;
    messageTimer = window.setTimeout(() => {
      messageEl.hidden = true;
      messageEl.className = 'notice';
      messageEl.textContent = '';
      messageTimer = null;
    }, 10000);
  }

  function handleFileSelect(event) {
    const input = event.target;
    if (!input.files || !input.files.length) {
      return;
    }
    showMessage('info', `已選擇 ${input.files.length} 個 Excel 檔案，匯入流程尚未實作。`);
    input.value = '';
  }

  function parseRocDate(value, fallbackYear) {
    if (!value) return null;
    const text = String(value).trim();
    if (!text) return null;

    let match = text.match(/(\d{2,3})\D+(\d{1,2})\D+(\d{1,2})/);
    let rocYear;
    let month;
    let day;
    if (match) {
      rocYear = parseInt(match[1], 10);
      month = parseInt(match[2], 10);
      day = parseInt(match[3], 10);
    } else {
      const digits = text.replace(/[^\d]/g, '');
      if (digits.length === 7) {
        rocYear = parseInt(digits.slice(0, 3), 10);
        month = parseInt(digits.slice(3, 5), 10);
        day = parseInt(digits.slice(5, 7), 10);
      } else if (digits.length === 6) {
        rocYear = parseInt(digits.slice(0, 3), 10);
        month = parseInt(digits.slice(3, 4), 10);
        day = parseInt(digits.slice(4, 6), 10);
      } else if (digits.length === 4) {
        rocYear = fallbackYear ? fallbackYear - 1911 : new Date().getFullYear() - 1911;
        month = parseInt(digits.slice(0, 2), 10);
        day = parseInt(digits.slice(2, 4), 10);
      } else {
        return null;
      }
    }

    if (!Number.isFinite(rocYear) || !Number.isFinite(month) || !Number.isFinite(day)) {
      return null;
    }
    const year = rocYear + 1911;
    const date = new Date(year, month - 1, day);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function formatRocString(date) {
    const [rocYear, padMonth] = toRocYearMonth(date.getFullYear(), date.getMonth() + 1);
    const day = date.getDate();
    return `${rocYear}年${parseInt(padMonth, 10)}月${day}日`;
  }

  function updateTradeMonthInputsFromDate(date) {
    if (!date) return;
    const [rocYear, padMonth] = toRocYearMonth(date.getFullYear(), date.getMonth() + 1);
    const value = `${rocYear}年${parseInt(padMonth, 10)}月`;
    populateMonthOptions();
    if (tradeMonthSelect) {
      tradeMonthSelect.value = value;
    }
  }

  function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function formatTransactionMonth(value) {
    if (!value) return '';
    if (/^\d{3,4}$/.test(value)) {
      const numeric = parseInt(value, 10);
      if (numeric > 1000) {
        const roc = Math.floor(numeric / 100);
        const month = numeric % 100;
        return `${roc}年${month}月`;
      }
      return `${numeric}月`;
    }
    return escapeHtml(value);
  }

  function setOpeningBalance(amount) {
    const numeric = Number.isFinite(amount) ? Math.max(0, Math.round(amount)) : 0;
    state.openingBalance = numeric;
    if (openingBalanceEl) {
      openingBalanceEl.textContent = formatCurrency(numeric);
    }
    if (balanceDisplayInput && !state.loading) {
      balanceDisplayInput.value = formatCurrency(numeric);
    }
  }

  function parseCurrencyInput(value) {
    if (value === null || value === undefined) {
      return NaN;
    }
    if (typeof value === 'number') {
      return value;
    }
    const normalized = String(value).replace(/[^0-9\-.]/g, '');
    if (!normalized) {
      return NaN;
    }
    return Number(normalized);
  }

  function formatNumberInput(value) {
    if (!Number.isFinite(value)) {
      return '';
    }
    return (Math.round(value * 100) / 100).toFixed(2);
  }
})();
