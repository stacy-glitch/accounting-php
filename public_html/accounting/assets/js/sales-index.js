(function () {
  'use strict';

  const LIST_ENDPOINT = '../api/sales/revenue_list.php';
  const UPLOAD_ENDPOINT = '../api/sales/upload.php';
  const DOWNLOAD_ENDPOINT = '../api/sales/download.php';
  const UPDATE_ENDPOINT = '../api/sales/revenue_update.php';
  const DELETE_ENDPOINT = '../api/sales/revenue_delete.php';
  const CREATE_ENDPOINT = '../api/sales/revenue_create.php';
  const CUSTOMERS_ENDPOINT = '../api/master-data/master_customers.php';

  const EDITABLE_FIELDS = [
    'customer_name',
    'freight',
    'invoice_amount',
    'tax',
    'warehouse_fee',
    'total',
    'actual_received',
    'received_date',
    'received_method',
    'note',
  ];
  const NUMERIC_FIELDS = ['freight', 'invoice_amount', 'tax', 'warehouse_fee', 'total', 'actual_received'];

  const root = document.body;
  if (!root) {
    return;
  }

  const monthTitleEls = document.querySelectorAll('[data-sales-month-title]');
  const navButtons = document.querySelectorAll('[data-sales-nav]');
  const tableRows = document.querySelector('[data-sales-rows]');
  const detailDrawer = document.querySelector('[data-advance-detail-drawer]');
  const detailBody = document.querySelector('[data-advance-detail-body]');
  const detailClose = document.querySelector('[data-advance-detail-close]');
  const uploadButton = document.querySelector('[data-action="upload"]');
  const downloadButton = document.querySelector('[data-action="download"]');
  const uploadInput = document.querySelector('[data-sales-upload]');
  const createForm = document.querySelector('[data-sales-create-form]');
  const createCustomerInput = document.querySelector('[data-create-customer]');
  const customerListEl = document.querySelector('[data-customer-list]');
  const CREATE_FIELD_KEYS = [
    'freight',
    'invoice_amount',
    'tax',
    'warehouse_fee',
    'total',
    'actual_received',
    'received_date',
    'received_method',
    'note',
  ];
  const createFieldEls = CREATE_FIELD_KEYS.reduce((acc, key) => {
    acc[key] = createForm ? createForm.querySelector(`[data-create-field="${key}"]`) : null;
    return acc;
  }, {});

  const state = {
    year: parseInt(root.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(root.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    loading: false,
    uploading: false,
    saving: false,
    creating: false,
    editingId: null,
    records: [],
    totals: null,
    customers: [],
    customerMap: new Map(),
    customerNameMap: new Map(),
    createCustomerCode: '',
    createCustomerName: '',
  };

  init();

  async function init() {
    bindEvents();
    updateMonthTitle();
    renderPlaceholder('資料載入中…');
    await loadCustomers();
    await loadRevenue();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.salesNav;
        if (dir === 'prev') {
          goToPreviousMonth();
        } else if (dir === 'next') {
          goToNextMonth();
        }
      });
    });

    if (uploadButton && uploadInput) {
      uploadButton.addEventListener('click', () => {
        if (state.uploading) {
          return;
        }
        uploadInput.value = '';
        uploadInput.click();
      });

      uploadInput.addEventListener('change', handleUploadChange);
    }

    if (downloadButton) {
      downloadButton.addEventListener('click', handleDownload);
    }

    if (tableRows) {
      tableRows.addEventListener('click', handleTableClick);
    }

    if (detailClose && detailDrawer) {
      detailClose.addEventListener('click', () => {
        detailDrawer.hidden = true;
      });
    }

    if (createForm) {
      createForm.addEventListener('submit', handleCreateSubmit);
    }

    if (createCustomerInput) {
      createCustomerInput.addEventListener('focus', handleCustomerInputFocus);
      createCustomerInput.addEventListener('input', () => resolveCustomerInput({ strict: false, silent: true }));
      createCustomerInput.addEventListener('change', () => resolveCustomerInput({ strict: false }));
      createCustomerInput.addEventListener('blur', () => resolveCustomerInput({ strict: true }));
    }
  }

  function loadRevenue() {
    if (state.loading) {
      return Promise.resolve();
    }
    state.loading = true;
    renderPlaceholder('資料載入中…');

    const params = new URLSearchParams({
      year: String(state.year),
      month: String(state.month),
    });

    return fetch(`${LIST_ENDPOINT}?${params.toString()}`, { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`載入失敗 (${response.status})`);
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || payload.ok === false) {
          throw new Error(payload && payload.error ? payload.error : '載入失敗');
        }
        state.records = Array.isArray(payload.data) ? payload.data : [];
        state.totals = payload.totals || {};
        state.editingId = null;
        renderRows();
        if (detailDrawer) {
          detailDrawer.hidden = true;
        }
      })
      .catch((error) => {
        renderPlaceholder(error.message || '載入失敗');
      })
      .finally(() => {
        state.loading = false;
        state.saving = false;
        updateMonthTitle();
      });
  }

  function loadCustomers() {
    if (!createCustomerInput || !customerListEl) {
      return Promise.resolve();
    }

    const previousValue = createCustomerInput.value || '';
    createCustomerInput.setAttribute('placeholder', '載入中…');
    customerListEl.innerHTML = '';
    state.customerMap = new Map();
    state.customerNameMap = new Map();
    state.customers = [];

    return fetch(CUSTOMERS_ENDPOINT, { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`載入客戶失敗 (${response.status})`);
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || payload.ok === false) {
          throw new Error(payload && payload.error ? payload.error : '載入客戶清單失敗');
        }
        const rows = Array.isArray(payload.data) ? payload.data : [];
        rows.sort((a, b) => {
          const codeA = String(a.code || '').trim();
          const codeB = String(b.code || '').trim();
          return codeA.localeCompare(codeB, 'zh-Hant', { sensitivity: 'base', numeric: true });
        });
        state.customers = rows;
        const fragment = document.createDocumentFragment();
        rows.forEach((row) => {
          const code = String(row.code || '').trim();
          if (code === '') {
            return;
          }
          const name = String(row.name || '').trim();
          state.customerMap.set(code, name);
          if (name !== '') {
            const nameKey = normalizeCustomerNameKey(name);
            if (!state.customerNameMap.has(nameKey)) {
              state.customerNameMap.set(nameKey, code);
            }
          }
          const option = document.createElement('option');
          option.value = code;
          option.label = name;
          option.textContent = name ? `${code} ${name}` : code;
          fragment.appendChild(option);
        });
        customerListEl.innerHTML = '';
        customerListEl.appendChild(fragment);
        createCustomerInput.value = previousValue;
        createCustomerInput.setAttribute('placeholder', '輸入客戶代號');
        resolveCustomerInput({ strict: false });
      })
      .catch(() => {
        customerListEl.innerHTML = '';
        state.customerMap = new Map();
        state.customerNameMap = new Map();
        createCustomerInput.setAttribute('placeholder', '客戶清單載入失敗');
        resolveCustomerInput({ strict: false });
      });
  }

  async function handleUploadChange() {
    if (!uploadInput || !uploadInput.files || !uploadInput.files.length) {
      return;
    }
    const [file] = uploadInput.files;
    if (!file) {
      return;
    }

    state.uploading = true;
    if (uploadButton) {
      uploadButton.disabled = true;
    }

    try {
      const formData = new FormData();
      formData.append('year', String(state.year));
      formData.append('month', String(state.month));
      formData.append('file', file);

      const response = await fetch(UPLOAD_ENDPOINT, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (!response.ok || !result || result.ok === false) {
        const message = result && result.error ? result.error : `上傳失敗 (${response.status})`;
        throw new Error(message);
      }

      showMessage(result.message || '匯入完成');
      await loadRevenue();
    } catch (error) {
      showMessage(error.message || '上傳失敗');
    } finally {
      state.uploading = false;
      if (uploadButton) {
        uploadButton.disabled = false;
      }
      if (uploadInput) {
        uploadInput.value = '';
      }
    }
  }

  async function handleCreateSubmit(event) {
    event.preventDefault();
    if (!createForm || state.creating) {
      return;
    }
    if (!state.createCustomerCode) {
      resolveCustomerInput({ strict: true });
    }
    const customerCode = state.createCustomerCode;
    if (!customerCode) {
      showMessage('請輸入有效的客戶代號');
      return;
    }
    const customerName =
      state.createCustomerName !== ''
        ? state.createCustomerName
        : getCustomerName(customerCode);

    const payload = {
      year: state.year,
      month: state.month,
      customer: customerCode,
      customer_name: customerName,
      freight: getCreateNumber('freight'),
      invoice_amount: getCreateNumber('invoice_amount'),
      tax: getCreateNumber('tax'),
      warehouse_fee: getCreateNumber('warehouse_fee'),
      total: getCreateNumber('total'),
      actual_received: getCreateNumber('actual_received'),
      received_date: getCreateText('received_date'),
      received_method: getCreateText('received_method'),
      note: getCreateText('note'),
    };

    state.creating = true;
    setCreateFormDisabled(true);

    try {
      const response = await fetch(CREATE_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok || !result || result.ok === false) {
        const message = result && result.error ? result.error : `新增失敗 (${response.status})`;
        throw new Error(message);
      }

      showMessage(result.message || '新增完成');
      resetCreateForm();
      await loadRevenue();
    } catch (error) {
      showMessage(error.message || '新增失敗');
    } finally {
      state.creating = false;
      setCreateFormDisabled(false);
    }
  }

  function handleDownload() {
    const defaultType = 'csv';
    let type = window.prompt('請輸入下載格式（csv / xlsx / pdf）', defaultType);
    if (type === null) {
      return;
    }
    type = type.trim().toLowerCase();
    if (!['csv', 'xlsx', 'pdf'].includes(type)) {
      showMessage('不支援的下載格式');
      return;
    }

    const url = `${DOWNLOAD_ENDPOINT}?year=${encodeURIComponent(state.year)}&month=${encodeURIComponent(state.month)}&type=${encodeURIComponent(type)}`;
    window.open(url, '_blank');
  }

  function handleTableClick(event) {
    const button = event.target.closest('[data-action]');
    if (button) {
      const action = button.dataset.action;
      const id = parseInt(button.dataset.id || '', 10);

      switch (action) {
        case 'edit':
          event.preventDefault();
          startEdit(id);
          return;
        case 'cancel':
          event.preventDefault();
          cancelEdit();
          return;
        case 'save':
          event.preventDefault();
          saveEdit(id);
          return;
        case 'delete':
          event.preventDefault();
          deleteRecord(id);
          return;
        default:
          break;
      }
    }

    const detailTrigger = event.target.closest('[data-action="toggle-advance-detail"]');
    if (detailTrigger) {
      const revenueId = parseInt(detailTrigger.dataset.id || '', 10);
      if (Number.isInteger(revenueId)) {
        event.preventDefault();
        showAdvanceDetail(revenueId);
      }
    }
  }

  function startEdit(id) {
    if (!Number.isInteger(id)) {
      return;
    }
    state.editingId = id;
    renderRows();
  }

  function cancelEdit() {
    state.editingId = null;
    renderRows();
  }

  async function saveEdit(id) {
    if (!Number.isInteger(id) || id <= 0 || state.saving) {
      return;
    }
    const rowEl = tableRows ? tableRows.querySelector(`[data-row-id="${id}"]`) : null;
    if (!rowEl) {
      return;
    }

    const payload = { id };
    EDITABLE_FIELDS.forEach((field) => {
      const input = rowEl.querySelector(`[data-field="${field}"]`);
      if (input) {
        payload[field] = input.value;
      }
    });

    NUMERIC_FIELDS.forEach((field) => {
      if (!Object.prototype.hasOwnProperty.call(payload, field)) {
        return;
      }
      const text = String(payload[field]).trim();
      if (text === '') {
        payload[field] = 0;
        return;
      }
      const normalized = text.replace(/[,\\s$]/g, '');
      payload[field] = Number.isFinite(Number(normalized)) ? parseInt(normalized, 10) : 0;
    });

    state.saving = true;
    renderRows();

    try {
      const response = await fetch(UPDATE_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok || !result || result.ok === false) {
        const message = result && result.error ? result.error : `儲存失敗 (${response.status})`;
        throw new Error(message);
      }

      showMessage(result.message || '已更新');
      state.editingId = null;
      await loadRevenue();
    } catch (error) {
      showMessage(error.message || '儲存失敗');
    } finally {
      state.saving = false;
      renderRows();
    }
  }

  async function deleteRecord(id) {
    if (!Number.isInteger(id) || id <= 0 || state.saving) {
      return;
    }
    if (!window.confirm('確定要刪除此筆營收資料？')) {
      return;
    }

    state.saving = true;
    renderRows();

    try {
      const response = await fetch(DELETE_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id }),
      });
      const result = await response.json();
      if (!response.ok || !result || result.ok === false) {
        const message = result && result.error ? result.error : `刪除失敗 (${response.status})`;
        throw new Error(message);
      }

      showMessage(result.message || '已刪除');
      if (state.editingId === id) {
        state.editingId = null;
      }
      await loadRevenue();
    } catch (error) {
      showMessage(error.message || '刪除失敗');
    } finally {
      state.saving = false;
      renderRows();
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
    loadRevenue();
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
    loadRevenue();
  }

  function updateMonthTitle() {
    if (!monthTitleEls || monthTitleEls.length === 0) return;
    const text = formatRocMonthTitle(state.year, state.month);
    monthTitleEls.forEach((el) => {
      el.textContent = text;
    });
  }

  function renderPlaceholder(message) {
    if (!tableRows) {
      return;
    }
    tableRows.innerHTML = `<tr><td colspan="11" class="table-empty">${escapeHtml(message)}</td></tr>`;
  }

  function renderRows() {
    if (!tableRows) {
      return;
    }

    const rows = Array.isArray(state.records) ? state.records : [];
    if (!rows.length) {
      tableRows.innerHTML = '<tr><td colspan="11" class="table-empty">尚無資料</td></tr>';
      return;
    }

    const rowHtml = rows.map(renderRow).join('');
    const totalsHtml = renderTotalsRow();
    tableRows.innerHTML = rowHtml + totalsHtml;
  }

  function renderRow(row) {
    const isEditing = state.editingId === row.id;
    const displayName = row.customer_name && row.customer_name !== '' ? row.customer_name : row.customer;
    if (!isEditing) {
      const editDisabled = state.saving ? ' disabled' : '';
      const deleteDisabled = state.saving ? ' disabled' : '';
      return `<tr data-row-id="${row.id || ''}">
        <td>${escapeHtml(displayName || '')}</td>
        <td style="text-align:right;">${formatAmount(row.freight)}</td>
        <td style="text-align:right;">${formatAmount(row.invoice_amount)}</td>
        <td style="text-align:right;">${formatAmount(row.tax)}</td>
        <td style="text-align:right;">${renderWarehouseCell(row)}</td>
        <td style="text-align:right;">${formatAmount(row.total)}</td>
        <td style="text-align:right;">${formatAmount(row.actual_received)}</td>
        <td>${escapeHtml(formatReceivedDate(row.received_date))}</td>
        <td>${escapeHtml(row.received_method || '')}</td>
        <td>${escapeHtml(row.note || '')}</td>
        <td class="table__ops">
          <button type="button" class="btn btn--ghost btn--small" data-action="edit" data-id="${row.id}"${editDisabled}>編輯</button>
          <button type="button" class="btn btn--secondary btn--small" data-action="delete" data-id="${row.id}"${deleteDisabled}>刪除</button>
        </td>
      </tr>`;
    }

    const disabledAttr = state.saving ? ' disabled' : '';
    return `<tr data-row-id="${row.id || ''}" class="sales-row sales-row--editing">
      <td>
        <div class="sales-customer-edit">
          <div class="sales-customer-edit__code">代號：${escapeHtml(row.customer || '')}</div>
          <input type="text" class="sales-table__input" data-field="customer_name" value="${escapeAttr(row.customer_name || '')}" placeholder="客戶名稱">
        </div>
      </td>
      ${renderNumberInput('freight', row.freight)}
      ${renderNumberInput('invoice_amount', row.invoice_amount)}
      ${renderNumberInput('tax', row.tax)}
      ${renderNumberInput('warehouse_fee', row.warehouse_fee)}
      ${renderNumberInput('total', row.total)}
      ${renderNumberInput('actual_received', row.actual_received)}
      <td><input type="text" class="sales-table__input" data-field="received_date" value="${escapeAttr(row.received_date || '')}" placeholder="收款日期"></td>
      <td><input type="text" class="sales-table__input" data-field="received_method" value="${escapeAttr(row.received_method || '')}" placeholder="收款方式"></td>
      <td><textarea class="sales-table__input sales-table__input--textarea" data-field="note" rows="1">${escapeHtml(row.note || '')}</textarea></td>
      <td class="table__ops table__ops--inline">
        <button type="button" class="btn btn--success btn--small" data-action="save" data-id="${row.id}"${disabledAttr}>儲存</button>
        <button type="button" class="btn btn--secondary btn--small" data-action="cancel">取消</button>
      </td>
    </tr>`;
  }

  function renderNumberInput(field, value) {
    const display = Number.isFinite(Number(value)) ? String(value) : '';
    return `<td><input type="number" class="sales-table__input sales-table__input--number" data-field="${field}" value="${escapeAttr(display)}" placeholder="0"></td>`;
  }

  function renderTotalsRow() {
    const totals = state.totals || {};
    return `<tr class="sales-row sales-row--totals">
      <td>合計</td>
      <td style="text-align:right;font-weight:600;">${formatAmount(totals.freight)}</td>
      <td style="text-align:right;font-weight:600;">${formatAmount(totals.invoice_amount)}</td>
      <td style="text-align:right;font-weight:600;">${formatAmount(totals.tax)}</td>
      <td style="text-align:right;font-weight:600;">${formatAmount(totals.warehouse_fee)}</td>
      <td style="text-align:right;font-weight:600;">${formatAmount(totals.total)}</td>
      <td style="text-align:right;font-weight:600;">${formatAmount(totals.actual_received)}</td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
    </tr>`;
  }

  function formatAmount(value) {
    const num = Number(value || 0);
    if (!Number.isFinite(num)) {
      return '0';
    }
    return num.toLocaleString();
  }

  function renderWarehouseCell(row) {
    const amount = formatAmount(row.warehouse_fee);
    const details = Array.isArray(row.advance_details) ? row.advance_details : [];
    if (!details.length) {
      return escapeHtml(amount);
    }
    return `
      <span>${escapeHtml(amount)}</span>
      <button type="button" class="btn btn--ghost btn--small advance-detail-toggle" data-action="toggle-advance-detail" data-id="${escapeHtml(
        String(row.id || '')
      )}">明細</button>
    `;
  }

  function showAdvanceDetail(revenueId) {
    if (!detailDrawer || !detailBody) {
      return;
    }
    const record = state.records.find((item) => item.id === revenueId);
    if (!record || !Array.isArray(record.advance_details) || !record.advance_details.length) {
      detailBody.innerHTML = '<tr><td colspan="4" class="table-empty">目前沒有銷帳明細</td></tr>';
      detailDrawer.hidden = false;
      return;
    }
    detailBody.innerHTML = record.advance_details
      .map((item) => {
        return `
          <tr>
            <td>${escapeHtml(formatReceivedDate(item.entry_date))}</td>
            <td>${escapeHtml(formatReceivedDate(item.transaction_date))}</td>
            <td style="text-align:right;">${escapeHtml(formatAmount(item.amount))}</td>
            <td>${escapeHtml(item.note || '')}</td>
          </tr>
        `;
      })
      .join('');
    const title = detailDrawer.querySelector('[data-advance-detail-title]');
    if (title) {
      title.textContent = `${record.customer_name || record.customer || ''} 的代墊支出明細`;
    }
    detailDrawer.hidden = false;
  }

  function formatReceivedDate(value) {
    const text = String(value || '').trim();
    if (text === '') {
      return '';
    }

    const iso = text.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (iso) {
      const year = parseInt(iso[1], 10);
      const month = parseInt(iso[2], 10);
      const day = parseInt(iso[3], 10);
      if (year >= 1911) {
        return `${year - 1911}/${padZero(month)}/${padZero(day)}`;
      }
      return `${padZero(year)}/${padZero(month)}/${padZero(day)}`;
    }

    const roc = text.match(/^(\d{3})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (roc) {
      const year = parseInt(roc[1], 10);
      const month = parseInt(roc[2], 10);
      const day = parseInt(roc[3], 10);
      return `${padZero(year)}/${padZero(month)}/${padZero(day)}`;
    }

    const compact = text.match(/^(\d{4})(\d{2})(\d{2})$/);
    if (compact) {
      const year = parseInt(compact[1], 10);
      const month = parseInt(compact[2], 10);
      const day = parseInt(compact[3], 10);
      if (year >= 1911) {
        return `${year - 1911}/${padZero(month)}/${padZero(day)}`;
      }
      return `${padZero(year)}/${padZero(month)}/${padZero(day)}`;
    }

    return text;
  }

  function padZero(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) {
      return String(value);
    }
    return num < 10 ? `0${num}` : String(num);
  }

  function formatRocMonthTitle(year, month) {
    const roc = year - 1911;
    if (Number.isFinite(roc) && roc > 0) {
      return `${roc}年${month}月營收報表`;
    }
    return `${year}年${month}月營收報表`;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showMessage(message) {
    if (!message) {
      return;
    }
    if (typeof console !== 'undefined' && typeof console.info === 'function') {
      console.info('[sales]', message);
    }
  }

  function handleCustomerInputFocus() {
    if (!createCustomerInput) {
      return;
    }
    if (
      state.createCustomerCode &&
      state.createCustomerName &&
      createCustomerInput.value.trim() === state.createCustomerName
    ) {
      createCustomerInput.value = state.createCustomerCode;
    }
  }

  function resolveCustomerInput(options = {}) {
    const { strict = true, silent = false } = options || {};
    if (!createCustomerInput) {
      state.createCustomerCode = '';
      state.createCustomerName = '';
      return '';
    }

    const isFocused = document.activeElement === createCustomerInput;
    const rawValue = createCustomerInput.value ? createCustomerInput.value.trim() : '';

    if (rawValue === '') {
      state.createCustomerCode = '';
      state.createCustomerName = '';
      if (createCustomerInput && typeof createCustomerInput.setCustomValidity === 'function') {
        createCustomerInput.setCustomValidity('');
      }
      if (!silent && !isFocused) {
        createCustomerInput.value = '';
      }
      return '';
    }

    const matchedCode = findCustomerCode(rawValue);
    if (!matchedCode) {
      state.createCustomerCode = '';
      state.createCustomerName = '';
      if (createCustomerInput && typeof createCustomerInput.setCustomValidity === 'function') {
        createCustomerInput.setCustomValidity(strict ? '找不到對應的客戶，請確認代號。' : '');
        if (strict && !silent) {
          createCustomerInput.reportValidity();
        }
      }
      return '';
    }

    const name = getCustomerName(matchedCode);
    state.createCustomerCode = matchedCode;
    state.createCustomerName = name;
    if (createCustomerInput && typeof createCustomerInput.setCustomValidity === 'function') {
      createCustomerInput.setCustomValidity('');
    }
    if (!silent && !isFocused) {
      createCustomerInput.value = name || matchedCode;
    }
    return matchedCode;
  }

  function findCustomerCode(value) {
    const trimmed = String(value || '').trim();
    if (trimmed === '') {
      return '';
    }
    if (state.customerMap instanceof Map && state.customerMap.has(trimmed)) {
      return trimmed;
    }
    if (state.customerMap instanceof Map) {
      const lower = trimmed.toLowerCase();
      for (const key of state.customerMap.keys()) {
        if (key.toLowerCase() === lower) {
          return key;
        }
      }
    }
    if (state.customerNameMap instanceof Map) {
      const code = state.customerNameMap.get(normalizeCustomerNameKey(trimmed));
      if (code) {
        return code;
      }
    }
    return '';
  }

  function getCustomerName(code) {
    if (!code || !(state.customerMap instanceof Map)) {
      return '';
    }
    return state.customerMap.get(code) || '';
  }

  function getCreateNumber(key) {
    const input = createFieldEls[key];
    if (!input) {
      return 0;
    }
    const value = typeof input.value === 'string' ? input.value.trim() : '';
    if (value === '') {
      return 0;
    }
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
      return 0;
    }
    return Math.round(parsed);
  }

  function getCreateText(key) {
    const input = createFieldEls[key];
    if (!input) {
      return '';
    }
    return typeof input.value === 'string' ? input.value.trim() : '';
  }

  function setCreateFormDisabled(disabled) {
    if (!createForm) {
      return;
    }
    const elements = createForm.querySelectorAll('input, select, textarea, button');
    elements.forEach((element) => {
      if (element.dataset && element.dataset.action === 'create-revenue') {
        if (disabled) {
          element.dataset.originalText = element.dataset.originalText || element.textContent;
          element.textContent = '新增中…';
        } else if (element.dataset.originalText) {
          element.textContent = element.dataset.originalText;
        }
      }
      element.disabled = disabled;
    });
  }

  function resetCreateForm() {
    if (!createForm) {
      return;
    }
    createForm.reset();
    state.createCustomerCode = '';
    state.createCustomerName = '';
    if (createCustomerInput) {
      createCustomerInput.value = '';
    }
    resolveCustomerInput({ strict: false, silent: true });
  }

  function normalizeCustomerNameKey(value) {
    return String(value || '')
      .trim()
      .toLowerCase();
  }
})();
