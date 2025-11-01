(function () {
  'use strict';

  const LIST_ENDPOINT = '../api/petty-cash/list.php';
  const UPDATE_ENDPOINT = '../api/petty-cash/voucher_update.php';
  const IMPORT_ENDPOINT = '../api/petty-cash/upload.php';
  const INVOICE_UPLOAD_ENDPOINT = '../api/petty-cash/invoice_upload.php';
  const INVOICE_DELETE_ENDPOINT = '../api/petty-cash/invoice_delete.php';
  const ALLOWED_INVOICE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
  const DELETE_ENDPOINT = '../api/petty-cash/voucher_delete.php';
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
const SUBMIT_LABEL_CREATE = '＋ 新增記錄';
const SUBMIT_LABEL_UPDATE = '儲存變更';
const SUBMIT_LABEL_SAVING = '儲存中…';

  const root = document.body;
  const monthTitleEl = document.querySelector('[data-month-title]');
  const tableEl = document.querySelector('[data-petty-table]');
  const balanceEl = document.querySelector('[data-balance]');
  const formEl = document.querySelector('[data-petty-form]');
  const uploadButtons = document.querySelectorAll('[data-action="upload"]');
  const fileInput = document.querySelector('[data-file-input]');
  const navButtons = document.querySelectorAll('[data-nav]');
  const messageEl = document.querySelector('[data-message]');
  const tableMonthEl = document.querySelector('[data-table-month]');
  const todayInputs = document.querySelectorAll('[data-default-today]');
  const prevDayInputs = document.querySelectorAll('[data-default-prev-day]');
  const codeDatalist = document.getElementById('petty-code-list');
  const subjectDatalist = document.getElementById('petty-subject-list');
  const dateDatalist = document.getElementById('petty-date-list');
  const monthDatalist = document.getElementById('petty-month-list');
  const entryInput = document.getElementById('entry-date');
  const tradeInput = document.getElementById('trade-date');
  const tradeMonthInput = document.getElementById('trade-month');
  const tradeMonthDisplay = document.getElementById('trade-month-display');
  const codeInput = document.getElementById('entry-code');
  const balanceDisplayInput = document.getElementById('balance');
  const subjectInput = document.getElementById('entry-subject');
  const noteInput = document.getElementById('entry-note');
  const incomeInput = document.getElementById('income');
  const expenseInput = document.getElementById('expense');
  const advanceIncomeInput = document.getElementById('advance-income');
  const advanceExpenseInput = document.getElementById('advance-expense');
  const openingBalanceEl = document.querySelector('[data-opening-balance]');
  const editOpeningBtn = document.querySelector('[data-action="edit-opening"]');
  const submitBtn = formEl ? formEl.querySelector('[data-action="submit-entry"]') : formEl ? formEl.querySelector('button[type="submit"]') : null;
  const editingIndicator = document.querySelector('[data-editing-indicator]');
  const pickerButtons = document.querySelectorAll('[data-picker]');
  const hiddenDatePickers = { entry: null, trade: null, month: null };
  const tradeMonthState = { manual: false };
  const uploadSummarySection = document.querySelector('[data-upload-summary]');
  const uploadSummaryMonthLabel = uploadSummarySection
    ? uploadSummarySection.querySelector('[data-upload-summary-month]')
    : null;
  const uploadSummaryRows = uploadSummarySection
    ? uploadSummarySection.querySelector('[data-upload-summary-rows]')
    : null;
  const monthMenu = document.querySelector('[data-month-menu]');
  let monthMenuOpen = false;
  let monthMenuTrigger = null;
  const invoiceUploadInput = document.createElement('input');
  invoiceUploadInput.type = 'file';
  invoiceUploadInput.accept = 'image/*';
  invoiceUploadInput.hidden = true;
  document.body.appendChild(invoiceUploadInput);
  let invoiceUploadTargetId = null;
  let invoiceUploadTrigger = null;
  invoiceUploadInput.addEventListener('change', handleInvoiceFileChange);
  const newInvoiceInput = document.querySelector('[data-new-invoice-input]');
  const newInvoiceChooseBtn = document.querySelector('[data-action="new-invoice-choose"]');
  const newInvoiceClearBtn = document.querySelector('[data-action="new-invoice-clear"]');
  const newInvoiceLabel = document.querySelector('[data-new-invoice-label]');
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
    updating: false,
    importing: false,
    deletingId: null,
    records: [],
    tableRows: [],
    editingId: null,
    editingRecord: null,
    uploads: {},
    selectedMonthNormalized: '',
    selectedMonthIso: '',
    invoiceUploadingId: null,
    invoiceDeletingId: null,
    newInvoiceFile: null,
    newInvoiceUploading: false,
    invoiceDeletingId: null,
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
    loadReferenceData();
    renderUploadSummary();
    syncFormButtons();
    syncEditingIndicator();
    updateNewInvoiceControls();
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleDocumentKeydown);
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

    if (uploadButtons.length && fileInput) {
      uploadButtons.forEach((button) => {
        button.addEventListener('click', () => fileInput.click());
      });
      fileInput.addEventListener('change', handleFileSelect);
    }
    if (newInvoiceChooseBtn && newInvoiceInput) {
      newInvoiceChooseBtn.addEventListener('click', () => {
        if (state.newInvoiceUploading) return;
        newInvoiceInput.click();
      });
    }
    if (newInvoiceClearBtn) {
      newInvoiceClearBtn.addEventListener('click', () => {
        if (state.newInvoiceUploading) return;
        clearNewInvoiceSelection();
      });
    }
    if (newInvoiceInput) {
      newInvoiceInput.addEventListener('change', handleNewInvoiceInputChange);
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
      tradeInput.addEventListener('input', () => {
        if (!tradeMonthInput || !tradeMonthDisplay) return;
        const raw = (tradeInput.value || '').trim();
        if (raw) {
          tradeMonthState.manual = false;
          const parsed = parseRocDate(raw, state.year);
          if (parsed) {
            const normalized = computeTransactionMonth(parsed);
            const iso = normalizedToIsoMonth(normalized);
            tradeMonthInput.dataset.autoValue = normalized;
            tradeMonthInput.dataset.autoIso = iso;
            state.selectedMonthNormalized = '';
            state.selectedMonthIso = '';
            tradeMonthInput.value = '';
            tradeMonthDisplay.value = '';
          }
        }
      });
      tradeInput.addEventListener('blur', () => {
        const parsed = parseRocDate(tradeInput.value, state.year);
        if (!parsed) {
          if (tradeMonthInput && tradeMonthDisplay && !tradeMonthState.manual) {
            tradeMonthInput.value = '';
            tradeMonthInput.dataset.autoValue = tradeMonthInput.dataset.defaultValue || '';
            tradeMonthInput.dataset.autoIso = tradeMonthInput.dataset.defaultIso || '';
            state.selectedMonthNormalized = '';
            state.selectedMonthIso = '';
            tradeMonthDisplay.value = '';
          }
          return;
        }
        tradeMonthState.manual = false;
        updateTradeMonthInputsFromDate(parsed);
        setHiddenPickerValue('trade', toIsoDate(parsed));
        if (tradeMonthDisplay) {
          tradeMonthDisplay.value = ''; // 日期優先時顯示空白
        }
      });
    }
    if (tradeMonthDisplay) {
      tradeMonthDisplay.addEventListener('input', handleTradeMonthDisplayInput);
      tradeMonthDisplay.addEventListener('blur', handleTradeMonthDisplayBlur);
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
    if (state.loading || state.updatingOpening || state.creating || state.updating || state.importing) {
      return;
    }
    if (state.editingId) {
      showMessage('info', '請先完成表格中的編輯或取消後再新增新紀錄');
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
    return buildPayloadFromValues(
      {
        entryRaw: entryInput ? entryInput.value : '',
        tradeRaw: tradeInput ? tradeInput.value : '',
        tradeMonthRaw: tradeMonthInput ? tradeMonthInput.value : '',
        codeRaw: codeInput ? codeInput.dataset.codeValue || codeInput.value : '',
        subjectRaw: subjectInput ? subjectInput.value : '',
        noteRaw: noteInput ? noteInput.value : '',
        incomeRaw: incomeInput ? incomeInput.value : '',
        expenseRaw: expenseInput ? expenseInput.value : '',
        advanceIncomeRaw: advanceIncomeInput ? advanceIncomeInput.value : '',
        advanceExpenseRaw: advanceExpenseInput ? advanceExpenseInput.value : '',
      },
      {
        advanceStatus: '',
      }
    );
  }

  function buildPayloadFromValues(raw, options = {}) {
    const { advanceStatus = '', existingRecord = null } = options || {};
    const entryRaw = (raw.entryRaw || '').trim();
    const tradeRaw = (raw.tradeRaw || '').trim();
    const tradeMonthRaw = (raw.tradeMonthRaw || '').trim();
    const codeRaw = raw.codeRaw !== undefined ? raw.codeRaw : '';
    const subjectRaw = raw.subjectRaw !== undefined ? raw.subjectRaw : '';
    const noteRaw = raw.noteRaw !== undefined ? raw.noteRaw : '';
    const incomeRaw = raw.incomeRaw !== undefined ? raw.incomeRaw : '';
    const expenseRaw = raw.expenseRaw !== undefined ? raw.expenseRaw : '';
    const advanceIncomeRaw = raw.advanceIncomeRaw !== undefined ? raw.advanceIncomeRaw : '';
    const advanceExpenseRaw = raw.advanceExpenseRaw !== undefined ? raw.advanceExpenseRaw : '';

    const entryDate = parseRocDate(entryRaw, state.year);
    if (!entryDate) {
      showMessage('error', '登記日期格式錯誤，請確認是否為民國年');
      return null;
    }

    if (tradeRaw && tradeMonthRaw) {
      showMessage('error', '實際交易日期與實際交易年月僅能擇一填寫');
      return null;
    }

    let tradeDate = null;
    let transactionMonth = '';

    if (tradeRaw) {
      tradeDate = parseRocDate(tradeRaw, state.year);
      if (!tradeDate) {
        showMessage('error', '實際交易日期格式錯誤，請確認是否為民國年');
        return null;
      }
    } else if (tradeMonthRaw) {
      const normalizedMonth = normalizeTradeMonthValue(tradeMonthRaw);
      if (!normalizedMonth) {
        showMessage('error', '實際交易年月格式錯誤');
        return null;
      }
      transactionMonth = normalizedMonth;
    } else if (existingRecord) {
      if (existingRecord.transaction_date) {
        const existingDate = toLocalDate(existingRecord.transaction_date);
        if (existingDate) {
          tradeDate = existingDate;
        }
      }
      if (!tradeDate && existingRecord.transaction_month) {
        const preservedMonth =
          normalizeTradeMonthValue(existingRecord.transaction_month) || String(existingRecord.transaction_month).trim();
        transactionMonth = preservedMonth;
      }
    }

    if (!tradeDate && !transactionMonth) {
      tradeDate = new Date(entryDate);
    }

    let transactionDateIso = tradeDate ? toIsoDate(tradeDate) : null;
    let transactionMonthValue = transactionMonth;

    if (transactionMonthValue && !/^\d{5}$/.test(transactionMonthValue)) {
      transactionMonthValue = normalizeTradeMonthValue(transactionMonthValue) || '';
    }

    if (!transactionMonthValue && tradeMonthRaw) {
      transactionMonthValue = normalizeTradeMonthValue(tradeMonthRaw) || '';
    }

    if (transactionDateIso && !tradeMonthRaw && !(existingRecord && existingRecord.transaction_month && !existingRecord.transaction_date)) {
      transactionMonthValue = '';
    }

    const code = resolveCodeValue(codeRaw);

    const subject = String(subjectRaw || '').trim();
    if (!subject) {
      showMessage('error', '請輸入會計科目');
      return null;
    }

    const note = String(noteRaw || '').trim();

    const income = parseAmount(incomeRaw, '收入金額');
    if (income === null) return null;
    const expense = parseAmount(expenseRaw, '支出金額');
    if (expense === null) return null;
    const advanceIncome = parseAmount(advanceIncomeRaw, '代墊收入');
    if (advanceIncome === null) return null;
    const advanceExpense = parseAmount(advanceExpenseRaw, '代墊支出');
    if (advanceExpense === null) return null;

    if (income === 0 && expense === 0 && advanceIncome === 0 && advanceExpense === 0) {
      showMessage('error', '收入、支出、代墊收入、代墊支出不可同時為 0');
      return null;
    }

    return {
      entry_date: toIsoDate(entryDate),
      transaction_date: transactionDateIso,
      transaction_month: transactionMonthValue,
      code,
      subject,
      note,
      income,
      expense,
      advance_income: advanceIncome,
      advance_expense: advanceExpense,
      advance: advanceExpense,
      advance_status: advanceStatus,
    };
  }

  function submitEntry(payload) {
    const request = {
      ...payload,
      year: state.year,
      month: state.month,
    };

    state.creating = true;
    syncFormButtons();
    updateNewInvoiceControls();

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
        const newId = Number(result?.data?.id || 0);
        resetFormToDefaults();
        showMessage('success', '已新增零用金記錄');

        if (state.newInvoiceFile && Number.isFinite(newId) && newId > 0) {
          state.newInvoiceUploading = true;
          updateNewInvoiceControls();
          return performInvoiceUpload(newId, state.newInvoiceFile)
            .then((payload) => {
              showMessage('success', payload.message || '發票已上傳');
            })
            .catch((error) => {
              showMessage('error', error.message || '發票上傳失敗（紀錄已新增）');
            })
            .finally(() => {
              state.newInvoiceUploading = false;
              state.newInvoiceFile = null;
              clearNewInvoiceSelection();
              updateNewInvoiceControls();
              loadRecords();
            });
        }

        state.newInvoiceFile = null;
        clearNewInvoiceSelection();
        updateNewInvoiceControls();
        loadRecords();
        return null;
      })
      .catch((error) => {
        showMessage('error', error.message || '新增失敗');
      })
      .finally(() => {
        state.creating = false;
        syncFormButtons();
        updateNewInvoiceControls();
      });
  }

  function fillTodayDefaults() {
    const today = new Date();
    const formatted = formatRocString(today);
    todayInputs.forEach((input) => {
      if (input instanceof HTMLInputElement) {
        input.value = formatted;
      }
    });
    updateTradeMonthInputsFromDate(today);
    setHiddenPickerValue('entry', toIsoDate(today));
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
    setHiddenPickerValue('trade', toIsoDate(today));
  }

  function fillTradeMonthDefaults() {
    const today = new Date();
    const normalized = computeTransactionMonth(today);
    const iso = buildIsoMonth(today.getFullYear(), today.getMonth() + 1);
    if (tradeMonthInput) {
      tradeMonthState.manual = false;
      tradeMonthInput.dataset.defaultValue = normalized;
      tradeMonthInput.dataset.defaultIso = iso;
      tradeMonthInput.dataset.autoValue = normalized;
      tradeMonthInput.dataset.autoIso = iso;
      tradeMonthInput.value = '';
    }
    setHiddenPickerValue('month', iso);
    if (tradeMonthDisplay) {
      tradeMonthDisplay.value = '';
    }
    state.selectedMonthNormalized = '';
    state.selectedMonthIso = '';
  }

  function resetFormToDefaults() {
    if (!formEl) return;
    formEl.reset();
    fillTodayDefaults();
    fillPrevDayDefaults();
    fillTradeMonthDefaults();
    if (codeInput) {
      codeInput.value = '';
      codeInput.dataset.codeValue = '';
    }
    if (tradeMonthDisplay) {
      tradeMonthDisplay.value = '';
    }
    if (tradeMonthInput) {
      tradeMonthInput.dataset.autoIso = tradeMonthInput.dataset.defaultIso || '';
    }
    if (balanceDisplayInput) {
      balanceDisplayInput.value = formatCurrency(state.openingBalance);
    }
    clearNewInvoiceSelection();
    syncFormButtons();
    syncEditingIndicator();
    highlightEditingRow();
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
    if (state.editingId) {
      cancelInlineEditing();
    }
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
    if (state.editingId) {
      cancelInlineEditing();
    }
    loadRecords();
  }

  function updateMonthTitle() {
    const [rocYear, padMonth] = toRocYearMonth(state.year, state.month);
    const monthNumber = parseInt(padMonth, 10);
    const displayMonth = Number.isFinite(monthNumber) ? monthNumber : padMonth;
    monthTitleEl.textContent = `${rocYear}年${displayMonth}月零用金記錄`;
    if (tableMonthEl) {
      tableMonthEl.textContent = `${rocYear}年${displayMonth}月零用金記錄`;
    }
    fillTradeMonthDefaults();
    renderUploadSummary();
  }

  function loadRecords() {
    if (state.loading) return;
    state.loading = true;
    syncFormButtons();
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
        syncFormButtons();
      });
  }

  function getCurrentMonthKey() {
    return `${state.year}-${String(state.month).padStart(2, '0')}`;
  }

  function renderUploadSummary() {
    if (!uploadSummarySection || !uploadSummaryRows) {
      return;
    }
    const key = getCurrentMonthKey();
    const entry = state.uploads[key] || {};
    const [year, month] = key.split('-');
    const files = ['pdf', 'excel', 'csv']
      .map((type) => {
        const info = entry[type];
        if (!info) return null;
        return { type, info };
      })
      .filter(Boolean);

    if (!files.length) {
      uploadSummaryRows.innerHTML = '';
      uploadSummarySection.hidden = true;
      return;
    }

    if (uploadSummaryMonthLabel) {
      uploadSummaryMonthLabel.textContent = `${year}年${month}月`;
    }

    const rows = files
      .map(({ type, info }) => {
        const label = type === 'pdf' ? 'PDF' : type === 'excel' ? 'Excel' : 'CSV';
        const name = escapeHtml(info.name || '');
        const metaText = buildUploadMeta(info);
        const metaHtml = metaText ? `<div class="upload-summary__meta">${escapeHtml(metaText)}</div>` : '';
        return `<tr><th scope="row">${label}</th><td data-upload-summary-label="${type}">${name}${metaHtml}</td></tr>`;
      })
      .join('');
    uploadSummaryRows.innerHTML = rows;
    uploadSummarySection.hidden = false;
  }

  function buildUploadMeta(info) {
    if (!info || typeof info !== 'object') {
      return '';
    }
    const parts = [];
    const counts = info.counts || {};
    if (isFiniteNumber(counts.inserted)) {
      parts.push(`新增 ${counts.inserted} 筆`);
    }
    if (isFiniteNumber(counts.deleted) && counts.deleted > 0) {
      parts.push(`覆蓋 ${counts.deleted} 筆`);
    }
    if (isFiniteNumber(counts.skipped) && counts.skipped > 0) {
      parts.push(`略過 ${counts.skipped} 筆`);
    }
    const message = typeof info.message === 'string' ? info.message.trim() : '';
    if (message) {
      parts.push(message);
    }
    return parts.length ? parts.join('，') : '';
  }

  function renderInvoiceCell(record, options = {}) {
    const showButton = options.showButton !== false;
    const showStatus = options.showStatus !== false;
    const statusText =
      showStatus && record.advance_status ? `<span class="petty-status-tag">${escapeHtml(record.advance_status)}</span>` : '';
    const rawUrl = (record.invoice_url || record.invoice_path || '').trim();
    let url = '';
    if (rawUrl) {
      if (/^https?:\/\//i.test(rawUrl)) {
        url = rawUrl;
      } else if (/^\.\./.test(rawUrl)) {
        url = rawUrl;
      } else {
        url = `../${rawUrl.replace(/^\/+/, '')}`;
      }
    }
    const hasInvoice = Boolean(url);
    const viewHtml = hasInvoice
      ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="petty-invoice-link">查看</a>`
      : '<span class="petty-invoice-placeholder">未上傳</span>';
    const uploadHtml =
      showButton && record.id
        ? `<button type="button" class="btn btn--ghost petty-invoice-button" data-action="invoice-upload" data-id="${escapeHtml(
            record.id || ''
          )}">上傳</button>`
        : '';
    const deleteHtml =
      showButton && hasInvoice && record.id
        ? `<button type="button" class="btn btn--ghost petty-invoice-button petty-invoice-button--delete" data-action="invoice-delete" data-id="${escapeHtml(
            record.id || ''
          )}">刪除</button>`
        : '';
    return `<div class="petty-invoice-cell">${statusText}${viewHtml}${uploadHtml}${deleteHtml}</div>`;
  }

  function renderInvoiceEditor(record, monthDisplay) {
    const base = renderInvoiceCell(record, { showStatus: true });
    return `<div class="petty-invoice-editor">${base}</div>`;
  }

  function refreshDateMonthDatalists() {
    const dateValues = new Set();
    const monthValues = new Set();

    state.records.forEach((record) => {
      if (!record || record.is_summary) return;
      const entryDisplay = formatRocDate(record.entry_date);
      if (entryDisplay) {
        dateValues.add(entryDisplay);
      }
      if (record.transaction_date) {
        const tradeDisplay = formatRocDate(record.transaction_date);
        if (tradeDisplay) {
          dateValues.add(tradeDisplay);
        }
      }
      if (record.transaction_month) {
        const monthDisplay = formatTransactionMonth(record.transaction_month);
        if (monthDisplay) {
          monthValues.add(monthDisplay);
        }
      }
    });

    const monthChoices = buildMonthChoices(state.year, state.month, 18);
    monthChoices.forEach((item) => {
      if (item && item.label) {
        monthValues.add(item.label);
      }
    });

    if (dateDatalist) {
      const sortedDates = Array.from(dateValues).sort((a, b) => {
        const da = parseRocDate(a, state.year) || new Date();
        const db = parseRocDate(b, state.year) || new Date();
        return db.getTime() - da.getTime();
      });
      dateDatalist.innerHTML = sortedDates.map((value) => `<option value="${escapeHtml(value)}"></option>`).join('');
    }

    if (monthDatalist) {
      const sortedMonths = Array.from(monthValues).sort((a, b) => a.localeCompare(b, 'zh-Hant'));
      monthDatalist.innerHTML = sortedMonths.map((value) => `<option value="${escapeHtml(value)}"></option>`).join('');
    }
  }

  function renderRecords(data) {
    const records = Array.isArray(data.records) ? data.records : [];
    state.tableRows = records.map((row) => (row ? { ...row } : row));
    state.records = state.tableRows.filter((row) => {
      if (!row || row.is_summary) return false;
      const key = getRecordKey(row.id);
      return Boolean(key);
    });
    refreshDateMonthDatalists();
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

    refreshTableRows();

    if (balanceDisplayInput) {
      const lastRecord = state.records.length ? state.records[state.records.length - 1] : null;
      const latestBalance = lastRecord ? toNumber(lastRecord.balance) : state.openingBalance;
      balanceDisplayInput.value = formatCurrency(latestBalance);
    }
  }

  function refreshTableRows() {
    if (!tableEl) return;
    const rowsData = Array.isArray(state.tableRows) ? state.tableRows : [];
    if (!rowsData.length) {
      const tbody = tableEl.querySelector('tbody');
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="12" class="table-empty">本月份尚無資料</td></tr>';
      if (balanceDisplayInput) {
        balanceDisplayInput.value = formatCurrency(state.openingBalance);
      }
      highlightEditingRow();
      return;
    }

    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    const rows = rowsData
      .filter((record) => record)
      .map((record) => renderRow(record))
      .join('');
    tbody.innerHTML = rows || '<tr><td colspan="12" class="table-empty">本月份尚無資料</td></tr>';
    bindRecordActionButtons(tbody);
    highlightEditingRow();
  }

  function renderRow(record) {
    if (record.is_summary) {
      return `
        <tr class="petty-summary">
          <td></td>
          <td></td>
          <td class="petty-summary__label">${escapeHtml(record.label || '小計')}</td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td class="petty-balance">${formatCurrency(record.balance || 0)}</td>
          <td></td>
          <td></td>
          <td></td>
        </tr>
      `;
    }

    const recordKey = getRecordKey(record.id);
    if (state.editingId && recordKey && state.editingId === recordKey) {
      return renderEditableRow(state.editingRecord || record);
    }

    const hasTradeDate = Boolean(record.transaction_date);
    const transactionDateDisplay = hasTradeDate
      ? formatRocDate(record.transaction_date)
      : record.transaction_month
      ? formatTransactionMonth(record.transaction_month)
      : '';
    const income = toNumber(record.income);
    const expense = toNumber(record.expense);
    const advanceIncome = toNumber(
      record.advance_income !== undefined ? record.advance_income : record.advanceIncome || 0
    );
    let advanceExpense = toNumber(
      record.advance_expense !== undefined ? record.advance_expense : record.advanceExpense || 0
    );
    if (advanceExpense === 0 && record.advance) {
      advanceExpense = toNumber(record.advance);
    }
    const balance = toNumber(record.balance);
    const incomeClass = income > 0 ? 'petty-income' : '';
    const expenseClass = expense > 0 ? 'petty-expense' : '';
    const balanceClass = 'petty-balance';

    const rowAttr = record.id ? ` data-record-id="${escapeHtml(String(record.id))}"` : '';
    const invoiceHtml = renderInvoiceCell(record);

    return `
      <tr${rowAttr}>
        <td>${formatRocDate(record.entry_date)}</td>
        <td>${escapeHtml(formatCodeDisplay(record.code))}</td>
        <td>${escapeHtml(record.subject)}</td>
        <td>${transactionDateDisplay}</td>
        <td class="${incomeClass}">${formatCurrency(income)}</td>
        <td class="${expenseClass}">${formatCurrency(expense)}</td>
        <td>${advanceIncome ? formatCurrency(advanceIncome) : ''}</td>
        <td>${advanceExpense ? formatCurrency(advanceExpense) : ''}</td>
        <td class="${balanceClass}">${formatCurrency(balance)}</td>
        <td>${invoiceHtml}</td>
        <td>${escapeHtml(record.note)}</td>
        <td class="table__ops">
          <button type="button" class="btn btn--ghost" data-action="edit" data-id="${escapeHtml(record.id || '')}">編輯</button>
          <button type="button" class="btn btn--secondary" data-action="delete" data-id="${escapeHtml(record.id || '')}">刪除</button>
        </td>
      </tr>
    `;
  }

  function formatCodeDisplay(code) {
    const text = String(code == null ? '' : code).trim();
    if (text === '') {
      return '';
    }
    if (/^-?\d+(\.0+)?$/.test(text)) {
      const numeric = Number(text);
      if (Number.isInteger(numeric)) {
        return String(numeric);
      }
      return String(numeric);
    }
    return text;
  }

  function renderEditableRow(record) {
    const rowAttr = record.id ? ` data-record-id="${escapeHtml(String(record.id))}"` : '';
    const entryDisplay = formatRocDate(record.entry_date);
    const transactionDisplay = record.transaction_date ? formatRocDate(record.transaction_date) : '';
    const monthDisplay =
      !record.transaction_date && record.transaction_month ? formatTransactionMonth(record.transaction_month) : '';
    const codeDisplay = getCodeDisplayValue(record.code);
    const incomeValue = toNumber(record.income || 0);
    const expenseValue = toNumber(record.expense || 0);
    const advanceIncomeValue = toNumber(
      record.advance_income !== undefined ? record.advance_income : record.advanceIncome || 0
    );
    let advanceExpenseValue = toNumber(
      record.advance_expense !== undefined ? record.advance_expense : record.advanceExpense || 0
    );
    if (advanceExpenseValue === 0 && record.advance) {
      advanceExpenseValue = toNumber(record.advance);
    }
    const balance = toNumber(record.balance);

    return `
      <tr${rowAttr} class="table-row--editing">
        <td>
          <input type="text" class="petty-inline-input" data-edit-field="entry_date" value="${escapeHtml(entryDisplay)}" placeholder="請輸入或選擇日期" list="petty-date-list">
        </td>
        <td>
          <input type="text" class="petty-inline-input" data-edit-field="code" value="${escapeHtml(codeDisplay)}" list="petty-code-list" placeholder="請輸入代號" data-code-value="${escapeHtml(record.code || '')}">
        </td>
        <td>
          <input type="text" class="petty-inline-input" data-edit-field="subject" value="${escapeHtml(record.subject || '')}" list="petty-subject-list" placeholder="請輸入或選擇">
        </td>
        <td>
          <input type="text" class="petty-inline-input" data-edit-field="trade_date" value="${escapeHtml(transactionDisplay)}" placeholder="請輸入或選擇日期" list="petty-date-list">
        </td>
        <td>
          <input type="number" class="petty-inline-input petty-inline-input--number" data-edit-field="income" min="0" step="1" value="${escapeHtml(String(incomeValue))}">
        </td>
        <td>
          <input type="number" class="petty-inline-input petty-inline-input--number" data-edit-field="expense" min="0" step="1" value="${escapeHtml(String(expenseValue))}">
        </td>
        <td>
          <input type="number" class="petty-inline-input petty-inline-input--number" data-edit-field="advance_income" min="0" step="1" value="${escapeHtml(String(advanceIncomeValue))}">
        </td>
        <td>
          <input type="number" class="petty-inline-input petty-inline-input--number" data-edit-field="advance_expense" min="0" step="1" value="${escapeHtml(String(advanceExpenseValue))}">
        </td>
        <td class="petty-balance">${formatCurrency(balance)}</td>
        <td>${renderInvoiceEditor(record, monthDisplay)}</td>
        <td>
          <input type="text" class="petty-inline-input" data-edit-field="note" value="${escapeHtml(record.note || '')}" placeholder="備註">
        </td>
        <td class="table__ops">
          <button type="button" class="btn btn--success" data-action="save-edit" data-id="${escapeHtml(record.id || '')}">儲存</button>
          <button type="button" class="btn btn--ghost" data-action="cancel-edit" data-id="${escapeHtml(record.id || '')}">取消</button>
        </td>
      </tr>
    `;
  }

  function renderErrorRow(message) {
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="12" class="table-empty">${escapeHtml(message)}</td></tr>`;
  }

  function bindRecordActionButtons(tbody) {
    const editButtons = tbody.querySelectorAll('[data-action="edit"]');
    editButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const id = button.dataset.id;
        const record = findRecordById(id);
        if (record) {
          startEditingRecord(record);
        } else {
          showMessage('error', '找不到要編輯的紀錄');
        }
      });
    });

    const deleteButtons = tbody.querySelectorAll('[data-action="delete"]');
    deleteButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const id = button.dataset.id;
        const record = findRecordById(id);
        if (record) {
          confirmDeleteRecord(record, button);
        } else {
          showMessage('error', '找不到要刪除的紀錄');
        }
      });
    });

    const invoiceButtons = tbody.querySelectorAll('[data-action="invoice-upload"]');
    invoiceButtons.forEach((button) => {
      const id = button.dataset.id;
      const record = findRecordById(id);
      if (!record) {
        button.disabled = true;
        return;
      }
      if (state.invoiceUploadingId && String(state.invoiceUploadingId) === String(record.id)) {
        button.disabled = true;
        button.textContent = '上傳中…';
      } else {
        button.disabled = false;
        button.textContent = '上傳';
      }
      button.addEventListener('click', () => {
        handleInvoiceUploadClick(String(record.id), button);
      });
    });

    const invoiceDeleteButtons = tbody.querySelectorAll('[data-action="invoice-delete"]');
    invoiceDeleteButtons.forEach((button) => {
      const id = button.dataset.id;
      const record = findRecordById(id);
      if (!record) {
        button.disabled = true;
        return;
      }
      if (state.invoiceDeletingId && String(state.invoiceDeletingId) === String(record.id)) {
        button.disabled = true;
        button.textContent = '刪除中…';
      } else {
        button.disabled = false;
        button.textContent = '刪除';
      }
      button.addEventListener('click', () => {
        handleInvoiceDeleteClick(String(record.id), button);
      });
    });

    const saveButtons = tbody.querySelectorAll('[data-action="save-edit"]');
    saveButtons.forEach((button) => {
      button.addEventListener('click', () => {
        handleInlineSave(button.dataset.id, button);
      });
    });

    const cancelButtons = tbody.querySelectorAll('[data-action="cancel-edit"]');
    cancelButtons.forEach((button) => {
      button.addEventListener('click', () => {
        cancelInlineEditing();
      });
    });
  }

  function findRecordById(id) {
    const key = getRecordKey(id);
    if (!key) {
      return null;
    }
    return state.records.find((item) => getRecordKey(item.id) === key) || null;
  }

  function startEditingRecord(record) {
    if (state.updating || state.creating || state.deletingId) {
      return;
    }
    const key = getRecordKey(record.id);
    if (!key) {
      showMessage('error', '找不到要編輯的紀錄');
      return;
    }
    state.editingId = key;
    state.editingRecord = { ...record };
    syncFormButtons();
    syncEditingIndicator();
    refreshTableRows();
    focusEditingRow();
  }

  function cancelInlineEditing(options = {}) {
    if (!state.editingId) {
      return;
    }
    state.editingId = null;
    state.editingRecord = null;
    syncFormButtons();
    syncEditingIndicator();
    if (!options.silent) {
      refreshTableRows();
    }
  }

  function handleInlineSave(id, trigger) {
    if (state.updating || state.creating) {
      return;
    }
    const record = findRecordById(id);
    if (!record) {
      showMessage('error', '找不到要更新的紀錄');
      return;
    }
    const row = getRowElementById(id);
    if (!row) {
      showMessage('error', '找不到要更新的紀錄');
      return;
    }
    const payload = buildPayloadFromValues(collectRowEditValues(row), {
      advanceStatus: record.advance_status || '',
      existingRecord: record,
    });
    if (!payload) {
      return;
    }
    if (trigger instanceof HTMLButtonElement) {
      trigger.disabled = true;
    }
    updateEntry(record.id, payload, {
      onFinally: () => {
        if (trigger instanceof HTMLButtonElement) {
          trigger.disabled = false;
        }
      },
    });
  }

  function handleInvoiceUploadClick(id, trigger) {
    if (!id) {
      return;
    }
    invoiceUploadTargetId = id;
    invoiceUploadTrigger = trigger instanceof HTMLButtonElement ? trigger : null;
    invoiceUploadInput.value = '';
    invoiceUploadInput.click();
  }

  function handleInvoiceFileChange() {
    const file = invoiceUploadInput.files && invoiceUploadInput.files[0];
    if (!invoiceUploadTargetId || !file) {
      invoiceUploadTargetId = null;
      invoiceUploadTrigger = null;
      invoiceUploadInput.value = '';
      return;
    }

    const recordId = Number(invoiceUploadTargetId);
    if (!Number.isFinite(recordId) || recordId <= 0) {
      invoiceUploadTargetId = null;
      invoiceUploadTrigger = null;
      invoiceUploadInput.value = '';
      return;
    }

    state.invoiceUploadingId = recordId;
    syncFormButtons();
    if (invoiceUploadTrigger) {
      invoiceUploadTrigger.disabled = true;
      invoiceUploadTrigger.textContent = '上傳中…';
    }
    showMessage('info', '發票上傳中，請稍候…');

    performInvoiceUpload(recordId, file)
      .then((payload) => {
        showMessage('success', payload.message || '發票已上傳');
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '發票上傳失敗');
      })
      .finally(() => {
        state.invoiceUploadingId = null;
        syncFormButtons();
        if (invoiceUploadTrigger) {
          invoiceUploadTrigger.disabled = false;
          invoiceUploadTrigger.textContent = '上傳';
        }
        invoiceUploadTargetId = null;
        invoiceUploadTrigger = null;
        invoiceUploadInput.value = '';
      });
  }

  function handleInvoiceDeleteClick(id, trigger) {
    if (!id) {
      return;
    }
    const numericId = Number(id);
    if (!Number.isFinite(numericId) || numericId <= 0) {
      return;
    }
    const confirmed = window.confirm('確定要刪除這筆發票嗎？');
    if (!confirmed) {
      return;
    }

    state.invoiceDeletingId = numericId;
    syncFormButtons();
    if (trigger) {
      trigger.disabled = true;
      trigger.textContent = '刪除中…';
    }

    fetch(INVOICE_DELETE_ENDPOINT, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ id: numericId }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || payload.ok !== true) {
          throw new Error(payload?.error || '刪除發票失敗');
        }
        showMessage('success', payload.message || '發票已刪除');
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '刪除發票失敗');
      })
      .finally(() => {
        state.invoiceDeletingId = null;
        syncFormButtons();
        if (trigger) {
          trigger.disabled = false;
          trigger.textContent = '刪除';
        }
      });
  }

  function performInvoiceUpload(id, file) {
    const formData = new FormData();
    formData.append('invoice', file, file.name);
    formData.append('id', String(id));

    return fetch(INVOICE_UPLOAD_ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    }).then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json().then((payload) => {
        if (!payload || payload.ok !== true) {
          throw new Error(payload?.error || '發票上傳失敗');
        }
        return payload;
      });
    });
  }

  function handleNewInvoiceInputChange() {
    if (!newInvoiceInput) return;
    const file = newInvoiceInput.files && newInvoiceInput.files[0];
    if (!file) {
      state.newInvoiceFile = null;
      updateNewInvoiceControls();
      return;
    }
    if (!isAllowedInvoiceFile(file.name)) {
      showMessage('error', '僅支援上傳圖片檔案（JPG、PNG、WEBP、HEIC）');
      newInvoiceInput.value = '';
      state.newInvoiceFile = null;
      updateNewInvoiceControls();
      return;
    }
    state.newInvoiceFile = file;
    updateNewInvoiceControls();
  }

  function clearNewInvoiceSelection() {
    state.newInvoiceFile = null;
    if (newInvoiceInput) {
      newInvoiceInput.value = '';
    }
    updateNewInvoiceControls();
  }

  function updateNewInvoiceControls() {
    const hasFile = !!state.newInvoiceFile;
    if (newInvoiceLabel) {
      newInvoiceLabel.textContent = hasFile ? state.newInvoiceFile.name : '尚未選擇檔案';
    }
    if (newInvoiceClearBtn) {
      newInvoiceClearBtn.disabled = !hasFile || state.newInvoiceUploading || state.creating;
    }
    if (newInvoiceChooseBtn) {
      newInvoiceChooseBtn.disabled = state.newInvoiceUploading || state.creating;
    }
  }

  function isAllowedInvoiceFile(filename) {
    if (!filename) {
      return false;
    }
    const parts = filename.split('.');
    if (parts.length < 2) {
      return false;
    }
    const ext = parts.pop().toLowerCase();
    return ALLOWED_INVOICE_EXTENSIONS.includes(ext);
  }

  function collectRowEditValues(row) {
    const readValue = (field) => {
      const input = row.querySelector(`[data-edit-field="${field}"]`);
      if (!input) return '';
      if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
        if (field === 'code') {
          const inputValue = input.value;
          if (inputValue && inputValue.trim()) {
            return inputValue;
          }
          const datasetValue = input.dataset.codeValue || input.getAttribute('data-code') || '';
          return datasetValue || '';
        }
        return input.value;
      }
      return '';
    };
    return {
      entryRaw: readValue('entry_date'),
      tradeRaw: readValue('trade_date'),
      tradeMonthRaw: readValue('transaction_month'),
      codeRaw: readValue('code'),
      subjectRaw: readValue('subject'),
      noteRaw: readValue('note'),
      incomeRaw: readValue('income'),
      expenseRaw: readValue('expense'),
      advanceIncomeRaw: readValue('advance_income'),
      advanceExpenseRaw: readValue('advance_expense'),
    };
  }

  function focusEditingRow() {
    const row = getRowElementById(state.editingId);
    if (!row) return;
    setupInlineRowInteractions(row, state.editingRecord);
    const focusable = row.querySelector('input, select, textarea, button');
    if (focusable instanceof HTMLElement) {
      focusable.focus();
      if (focusable instanceof HTMLInputElement) {
        focusable.select();
      }
    }
  }

  function getRowElementById(id) {
    if (!tableEl) return null;
    const key = getRecordKey(id);
    if (!key) return null;
    const escaped =
      window.CSS && typeof window.CSS.escape === 'function'
        ? window.CSS.escape(key)
        : key.replace(/(["\\])/g, '\\$1');
    return tableEl.querySelector(`tbody [data-record-id="${escaped}"]`);
  }

  function getRecordKey(id) {
    if (id === null || id === undefined) {
      return '';
    }
    return String(id);
  }

  function getCodeDisplayValue(code) {
    const trimmed = String(code || '').trim();
    if (!trimmed) {
      return '';
    }
    const normalized = normalizeCode(trimmed);
    const lookup = codeLookup.byNormalized.get(normalized);
    if (lookup && lookup.display) {
      return lookup.display;
    }
    return trimmed;
  }
  function setupInlineRowInteractions(row, record) {
    if (!row) return;
    const entryField = row.querySelector('[data-edit-field="entry_date"]');
    const tradeField = row.querySelector('[data-edit-field="trade_date"]');
    const monthField = row.querySelector('[data-edit-field="transaction_month"]');

    const ensureExclusive = (source) => {
      if (source === 'trade' && monthField) {
        monthField.value = '';
      }
      if (source === 'month') {
        if (!monthField) return;
        const normalized = normalizeTradeMonthValue(monthField.value);
        if (!normalized) return;
        const entryDateObj = entryField ? parseRocDate(entryField.value, state.year) : null;
        const derived = dateFromNormalizedMonth(normalized, entryDateObj);
        if (tradeField && derived) {
          const isoDate = toIsoDate(derived);
          tradeField.value = formatRocDate(isoDate);
          setHiddenPickerValue('trade', isoDate);
        }
      }
    };

    if (entryField) {
      entryField.addEventListener('blur', () => {
        const parsed = parseRocDate(entryField.value, state.year);
        if (!parsed) {
          return;
        }
        if (tradeField && tradeField.value.trim()) {
          return;
        }
        if (monthField && monthField.value.trim()) {
          return;
        }
        const prev = new Date(parsed);
        prev.setDate(prev.getDate() - 1);
        if (tradeField) {
          tradeField.value = formatRocString(prev);
        }
      });
    }

    if (tradeField) {
      tradeField.addEventListener('input', () => {
        ensureExclusive('trade');
      });
      tradeField.addEventListener('blur', () => {
        const parsed = parseRocDate(tradeField.value, state.year);
        if (parsed) {
          tradeField.value = formatRocString(parsed);
          ensureExclusive('trade');
        }
      });
    }

    if (monthField) {
      monthField.addEventListener('input', () => {
        tradeMonthState.manual = true;
      });
      monthField.addEventListener('blur', () => {
        const normalized = normalizeTradeMonthValue(monthField.value);
        if (normalized) {
          monthField.value = formatTransactionMonth(normalized);
          ensureExclusive('month');
        }
      });
    }
  }

  function handleTradeMonthDisplayInput() {
    if (!tradeMonthDisplay) return;
    const raw = tradeMonthDisplay.value || '';
    if (raw.trim() === '') {
      clearMonthSelection();
      return;
    }
    tradeMonthState.manual = true;
  }

  function handleTradeMonthDisplayBlur() {
    if (!tradeMonthDisplay) return;
    const raw = (tradeMonthDisplay.value || '').trim();
    if (raw === '') {
      clearMonthSelection();
      return;
    }
    const normalized = normalizeTradeMonthValue(raw);
    if (!normalized) {
      showMessage('error', '實際交易年月格式錯誤');
      if (state.selectedMonthNormalized) {
        tradeMonthDisplay.value = formatTransactionMonth(state.selectedMonthNormalized);
      } else {
        clearMonthSelection();
      }
      return;
    }
    applyManualTradeMonth(normalized);
  }

  function applyManualTradeMonth(normalized) {
    if (!normalized) {
      clearMonthSelection();
      return;
    }
    const iso = normalizedToIsoMonth(normalized);
    tradeMonthState.manual = true;
    state.selectedMonthNormalized = normalized;
    state.selectedMonthIso = iso;
    if (tradeMonthInput) {
      tradeMonthInput.value = normalized;
      tradeMonthInput.dataset.autoValue = normalized;
      tradeMonthInput.dataset.autoIso = iso || '';
    }
    if (tradeMonthDisplay) {
      tradeMonthDisplay.value = formatTransactionMonth(normalized);
    }
    const entryDateObj = entryInput ? parseRocDate(entryInput.value, state.year) : null;
    const derivedDate = dateFromNormalizedMonth(normalized, entryDateObj);
    if (tradeInput && derivedDate) {
      const isoDate = toIsoDate(derivedDate);
      tradeInput.value = formatRocDate(isoDate);
      setHiddenPickerValue('trade', isoDate);
    }
    if (iso) {
      setHiddenPickerValue('month', iso);
    }
  }

  function updateEntry(id, payload, options = {}) {
    const request = {
      ...payload,
      id,
      year: state.year,
      month: state.month,
    };
    state.updating = true;
    syncFormButtons();

    fetch(UPDATE_ENDPOINT, {
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
          throw new Error(result?.error || '更新失敗');
        }
        showMessage('success', '已更新零用金記錄');
        cancelInlineEditing({ silent: true });
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '更新失敗');
      })
      .finally(() => {
        state.updating = false;
        syncFormButtons();
        if (typeof options.onFinally === 'function') {
          options.onFinally();
        }
      });
  }

  function confirmDeleteRecord(record, trigger) {
    if (state.deletingId) {
      return;
    }
    const entryText = formatRocDate(record.entry_date);
    const confirmMessage = `確定刪除 ${entryText || '這筆'} 零用金紀錄？`;
    if (!window.confirm(confirmMessage)) {
      return;
    }
    state.deletingId = getRecordKey(record.id);
    if (trigger) {
      trigger.disabled = true;
    }

    fetch(DELETE_ENDPOINT, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        id: record.id,
        year: state.year,
        month: state.month,
      }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((result) => {
        if (!result || result.ok !== true) {
          throw new Error(result?.error || '刪除失敗');
        }
        if (state.editingId && getRecordKey(state.editingId) === getRecordKey(record.id)) {
          cancelInlineEditing({ silent: true });
        }
        showMessage('success', '已刪除零用金記錄');
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '刪除失敗');
        if (trigger && trigger instanceof HTMLButtonElement) {
          trigger.disabled = false;
        }
      })
      .finally(() => {
        state.deletingId = null;
      });
  }

  function highlightEditingRow() {
    if (!tableEl) return;
    const rows = tableEl.querySelectorAll('tbody tr');
    rows.forEach((row) => {
      row.classList.remove('is-editing');
    });
    if (!state.editingId) {
      return;
    }
    const target = getRowElementById(state.editingId);
    if (target) {
      target.classList.add('is-editing');
    }
  }

  function syncFormButtons() {
    if (!submitBtn) {
      return;
    }
    if (state.creating || state.updating) {
      submitBtn.textContent = SUBMIT_LABEL_SAVING;
    } else {
      submitBtn.textContent = SUBMIT_LABEL_CREATE;
    }
    const disabled =
      state.creating ||
      state.updating ||
      state.loading ||
      state.updatingOpening ||
      state.importing ||
      Boolean(state.editingId) ||
      state.invoiceUploadingId !== null ||
      state.invoiceDeletingId !== null;
    submitBtn.disabled = disabled;
  }

  function syncEditingIndicator() {
    if (!editingIndicator) return;
    if (!state.editingId || !state.editingRecord) {
      editingIndicator.hidden = true;
      editingIndicator.textContent = '';
      return;
    }
    const dateText = formatRocDate(state.editingRecord.entry_date);
    const subjectText = state.editingRecord.subject ? `｜${state.editingRecord.subject}` : '';
    editingIndicator.hidden = false;
    editingIndicator.textContent = `編輯中：${dateText || ''}${subjectText}（請在下方表格儲存或取消）`;
  }

  function setTableLoading() {
    const tbody = tableEl.querySelector('tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="12" class="table-empty">資料載入中…</td></tr>';
  }

  function showDatePicker(type, trigger) {
    if (type === 'month') {
      showMonthPicker(trigger);
      return;
    }
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
    input.type = type === 'month' ? 'month' : 'date';
    input.className = 'petty-hidden-date';
    document.body.appendChild(input);
    input.addEventListener('change', () => applyHiddenPickerDate(type, input.value));
    hiddenDatePickers[type] = input;
    return input;
  }

  function showMonthPicker(trigger) {
    if (!monthMenu) return;
    if (monthMenuOpen && monthMenuTrigger === trigger) {
      hideMonthMenu();
      return;
    }
    monthMenuTrigger = trigger;
    renderMonthMenu();
    positionMonthMenu(trigger);
    monthMenu.hidden = false;
    monthMenuOpen = true;
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
    if (type === 'month') {
      handleMonthSelection(value);
      return;
    }
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
        setHiddenPickerValue('trade', toIsoDate(prev));
      }
    } else if (type === 'trade') {
      updateTradeMonthInputsFromDate(date);
    }
    setHiddenPickerValue(type, toIsoDate(date));
  }

  function setHiddenPickerValue(type, value) {
    const picker = ensureHiddenPicker(type);
    picker.value = value;
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
    const date = toLocalDate(value);
    if (!date) return escapeHtml(value);
    const rocYear = date.getFullYear() - 1911;
    if (rocYear <= 0) {
      return escapeHtml(value);
    }
    const monthText = String(date.getMonth() + 1).padStart(2, '0');
    const dayText = String(date.getDate()).padStart(2, '0');
    const yearText = String(rocYear).padStart(3, '0');
    return `${yearText}/${monthText}/${dayText}`;
  }

  function toLocalDate(value) {
    if (!value) return null;
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
      return new Date(value.getFullYear(), value.getMonth(), value.getDate());
    }
    const text = String(value).trim();
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (!match) {
      return null;
    }
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (
      !Number.isInteger(year) ||
      !Number.isInteger(month) ||
      !Number.isInteger(day) ||
      month < 1 ||
      month > 12 ||
      day < 1 ||
      day > 31
    ) {
      return null;
    }
    return new Date(year, month - 1, day);
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
    const defaultValue = String(Math.max(0, Math.round(state.openingBalance)));
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

  function resolveCodeValue(raw) {
    if (raw === null || raw === undefined) {
      return '';
    }
    const text = String(raw).trim();
    if (!text) {
      return '';
    }
    const displayEntry = codeLookup.byDisplay.get(text);
    if (displayEntry && displayEntry.value) {
      return displayEntry.value;
    }
    if (text.includes('—')) {
      const candidate = text.split('—')[0].trim();
      if (candidate) {
        return candidate;
      }
    }
    const normalized = normalizeCode(text);
    const normalizedEntry = codeLookup.byNormalized.get(normalized);
    if (normalizedEntry && normalizedEntry.value) {
      return normalizedEntry.value;
    }
    return text;
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
    const file = input.files[0];
    const type = detectStatementType(file.name);
    if (!type) {
      showMessage('error', '僅支援上傳 PDF、Excel、CSV 檔案');
      input.value = '';
      return;
    }
    const key = getCurrentMonthKey();
    const formData = new FormData();
    formData.append('file', file, file.name);
    formData.append('year', String(state.year));
    formData.append('month', String(state.month));
    formData.append('mode', 'replace');

    state.importing = true;
    syncFormButtons();
    showMessage('info', `正在匯入 ${file.name}，請稍候…`);

    fetch(IMPORT_ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((result) => {
        if (!result || result.ok !== true) {
          throw new Error(result?.error || '匯入失敗');
        }
        const typeLabel = type === 'pdf' ? 'PDF' : type === 'excel' ? 'Excel' : 'CSV';
        const [year, month] = key.split('-');

        if (!state.uploads[key]) {
          state.uploads[key] = {};
        }
        state.uploads[key][type] = {
          name: file.name,
          importedAt: new Date().toISOString(),
          counts: result.data || {},
          message: result.message || '',
        };
        renderUploadSummary();
        showMessage('success', result.message || `已為 ${year}年${month}月 匯入 ${typeLabel}：${file.name}`);
        loadRecords();
      })
      .catch((error) => {
        showMessage('error', error.message || '匯入失敗');
      })
      .finally(() => {
        state.importing = false;
        syncFormButtons();
        input.value = '';
      });
  }

  function detectStatementType(filename) {
    if (!filename) return '';
    const ext = filename.split('.').pop().toLowerCase();
    if (ext === 'pdf') return 'pdf';
    if (ext === 'xlsx' || ext === 'xls') return 'excel';
    if (ext === 'csv') return 'csv';
    return '';
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

  function formatRocString(value) {
    const date = toLocalDate(value);
    if (!date) {
      return '';
    }
    const [rocYear, padMonth] = toRocYearMonth(date.getFullYear(), date.getMonth() + 1);
    const yearText = String(rocYear).padStart(3, '0');
    const dayText = String(date.getDate()).padStart(2, '0');
    return `${yearText}/${padMonth}/${dayText}`;
  }

  function updateTradeMonthInputsFromDate(date) {
    if (!tradeMonthInput || !date || !(date instanceof Date) || Number.isNaN(date.getTime())) {
      return;
    }
    if (tradeMonthState.manual) {
      return;
    }
    const normalized = computeTransactionMonth(date);
    const iso = buildIsoMonth(date.getFullYear(), date.getMonth() + 1);
    tradeMonthInput.dataset.autoValue = normalized;
    tradeMonthInput.dataset.autoIso = iso;
    if (!tradeMonthState.manual) {
      state.selectedMonthNormalized = '';
      state.selectedMonthIso = '';
      tradeMonthInput.value = '';
      if (tradeMonthDisplay) {
        tradeMonthDisplay.value = '';
      }
    }
  }

  
  function renderMonthMenu() {
    if (!monthMenu) return;
    const items = buildMonthChoices(state.year, state.month, 18);
    const rows = items
      .map((item) => {
        const active = state.selectedMonthNormalized === item.normalized ? ' petty-month-menu__item--active' : '';
        return `<div class="petty-month-menu__item${active}" data-month-iso="${item.iso}" data-month-normalized="${item.normalized}">${item.label}</div>`;
      })
      .join('');
    monthMenu.innerHTML = rows;
    monthMenu.querySelectorAll('[data-month-iso]').forEach((element) => {
      element.addEventListener('click', () => {
        handleMonthSelection(element.dataset.monthIso);
        hideMonthMenu();
      });
    });
  }

  function buildMonthChoices(year, month, count) {
    const result = [];
    let y = year;
    let m = month;
    for (let i = 0; i < count; i += 1) {
      const iso = buildIsoMonth(y, m);
      const normalized = normalizedFromIsoMonth(iso);
      const label = formatRocMonth(y, m);
      result.push({ iso, normalized, label });
      m -= 1;
      if (m < 1) {
        m = 12;
        y -= 1;
      }
    }
    return result;
  }

  function formatRocMonth(year, month) {
    const roc = year - 1911;
    if (!Number.isFinite(roc) || roc <= 0) {
      return `${year}/${String(month)}`;
    }
    return `${String(roc).padStart(3, '0')}/${String(month)}`;
  }

  function handleDocumentClick(event) {
    if (!monthMenuOpen) return;
    if (monthMenu && monthMenu.contains(event.target)) {
      return;
    }
    if (monthMenuTrigger && monthMenuTrigger.contains(event.target)) {
      return;
    }
    hideMonthMenu();
  }

  function handleDocumentKeydown(event) {
    if (event.key === 'Escape' && monthMenuOpen) {
      hideMonthMenu();
    }
  }

  function hideMonthMenu() {
    if (!monthMenu) return;
    monthMenu.hidden = true;
    monthMenuOpen = false;
    monthMenuTrigger = null;
  }

  function positionMonthMenu(trigger) {
    if (!monthMenu) return;
    const rect = trigger.getBoundingClientRect();
    monthMenu.style.minWidth = `${rect.width}px`;
    monthMenu.style.left = '0';
    monthMenu.style.top = 'calc(100% + 4px)';
  }

  function clearMonthSelection() {
    tradeMonthState.manual = false;
    state.selectedMonthNormalized = '';
    state.selectedMonthIso = '';
    if (tradeMonthInput) {
      tradeMonthInput.value = '';
      tradeMonthInput.dataset.autoValue = tradeMonthInput.dataset.defaultValue || '';
      tradeMonthInput.dataset.autoIso = tradeMonthInput.dataset.defaultIso || '';
    }
    if (tradeMonthDisplay) {
      tradeMonthDisplay.value = '';
    }
  }
function handleMonthSelection(isoValue) {
    if (!isoValue) return;
    const normalized = normalizedFromIsoMonth(isoValue);
    if (!normalized) {
      showMessage('error', '實際交易年月格式錯誤');
      return;
    }
    applyManualTradeMonth(normalized);
  }

  function buildIsoMonth(year, month) {
    return `${year}-${String(month).padStart(2, '0')}`;
  }

  function normalizedFromIsoMonth(iso) {
    const match = /^\s*(\d{4})-(\d{2})\s*$/.exec(iso);
    if (!match) {
      return '';
    }
    const year = parseInt(match[1], 10);
    const month = parseInt(match[2], 10);
    const roc = year - 1911;
    if (!Number.isFinite(roc) || roc <= 0 || month < 1 || month > 12) {
      return '';
    }
    return `${String(roc).padStart(3, '0')}${String(month).padStart(2, '0')}`;
  }

  function normalizedToIsoMonth(normalized) {
    if (!/^[0-9]{5}$/.test(normalized)) {
      return '';
    }
    const roc = parseInt(normalized.slice(0, 3), 10);
    const month = parseInt(normalized.slice(3, 5), 10);
    const year = roc + 1911;
    return buildIsoMonth(year, month);
  }

  function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function formatTransactionMonth(value) {
    if (!value) return '';
    if (/^\d{5}$/.test(value)) {
      const roc = parseInt(value.slice(0, 3), 10);
      const month = parseInt(value.slice(3, 5), 10);
      if (Number.isFinite(roc) && Number.isFinite(month)) {
        return `${roc}/${month}`;
      }
    }
    if (/^\d{4}-\d{2}$/.test(value)) {
      const [yearStr, monthStr] = value.split('-');
      const year = parseInt(yearStr, 10) - 1911;
      const month = parseInt(monthStr, 10);
      if (Number.isFinite(year) && Number.isFinite(month)) {
        return `${year}/${month}`;
      }
    }
    if (/^\d{3,4}$/.test(value)) {
      const numeric = parseInt(value, 10);
      if (numeric > 1000) {
        const roc = Math.floor(numeric / 100);
        const month = numeric % 100;
        return `${roc}/${month}`;
      }
      return `${numeric}`;
    }
    return escapeHtml(value);
  }

  function normalizeTradeMonthValue(value) {
    if (!value) return '';
    const trimmed = String(value).trim();
    if (/^\d{4,5}$/.test(trimmed)) {
      return trimmed.padStart(5, '0');
    }
    const match = trimmed.match(/(\d{2,3})\D*(\d{1,2})/);
    if (!match) {
      return '';
    }
    const roc = parseInt(match[1], 10);
    const month = parseInt(match[2], 10);
    if (!Number.isFinite(roc) || roc <= 0 || !Number.isFinite(month) || month < 1 || month > 12) {
      return '';
    }
    return `${String(roc).padStart(3, '0')}${String(month).padStart(2, '0')}`;
  }

  function dateFromNormalizedMonth(normalized, entryDate) {
    if (!normalized) {
      return null;
    }
    const isoMonth = normalizedToIsoMonth(normalized);
    if (!isoMonth) {
      return null;
    }
    const [yearStr, monthStr] = isoMonth.split('-');
    const year = parseInt(yearStr, 10);
    const month = parseInt(monthStr, 10);
    if (!Number.isFinite(year) || !Number.isFinite(month)) {
      return null;
    }
    if (entryDate instanceof Date && !Number.isNaN(entryDate.getTime())) {
      if (entryDate.getFullYear() === year && entryDate.getMonth() + 1 === month) {
        return new Date(entryDate);
      }
    }
    return new Date(year, month - 1, 1);
  }

  function isoDateFromNormalizedMonth(normalized, entryDate) {
    const derived = dateFromNormalizedMonth(normalized, entryDate);
    return derived ? toIsoDate(derived) : '';
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

  function isFiniteNumber(value) {
    return typeof value === 'number' && Number.isFinite(value);
  }
})();
