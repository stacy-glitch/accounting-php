(function () {
  'use strict';

  const CUSTOMERS_ENDPOINT = '../api/master-data/master_customers.php';
  const REMITTANCE_LATEST_ENDPOINT = '../api/sales/remittance_latest.php';
  const REMITTANCE_UPLOAD_ENDPOINT = '../api/sales/remittance_upload.php';
  const form = document.querySelector('[data-remittance-form]');
  const tableBody = document.querySelector('[data-remittance-rows]');
  const customerInput = form ? form.querySelector('[data-remittance-customer]') : null;
  const customerListEl = document.querySelector('[data-remittance-customer-list]');
  const uploadTriggerBtn = document.querySelector('[data-remittance-upload-trigger]');
  const uploadInput = document.querySelector('[data-remittance-upload]');
  const fields = ['customer', 'account', 'bank', 'remark'];
  const inputs = fields.reduce((acc, key) => {
    acc[key] = form ? form.querySelector(`[data-remittance-field="${key}"]`) : null;
    return acc;
  }, {});

  const state = {
    records: [],
    editingIndex: null,
    editDraft: null,
    customers: [],
    customerMap: new Map(), // code -> name
    customerNameMap: new Map(), // normalized name -> { code, name }
    uploading: false,
    loading: false,
  };

  init();

  function init() {
    if (form) {
      form.addEventListener('submit', handleSubmit);
    }
    if (tableBody) {
      tableBody.addEventListener('click', handleTableClick);
    }
    if (uploadTriggerBtn && uploadInput) {
      uploadTriggerBtn.dataset.originalText = uploadTriggerBtn.textContent || '上傳 .xlsx';
      uploadTriggerBtn.addEventListener('click', () => {
        if (state.uploading) {
          return;
        }
        uploadInput.value = '';
        uploadInput.click();
      });
      uploadInput.addEventListener('change', handleUploadChange);
    }
    loadCustomers();
    loadRemittanceRecords();
  }

  function handleSubmit(event) {
    event.preventDefault();
    if (!form) {
      return;
    }
    const record = {};
    let hasValue = false;
    fields.forEach((key) => {
      const value = inputs[key] ? inputs[key].value.trim() : '';
      record[key] = value;
      if (value !== '') {
        hasValue = true;
      }
    });

    const customerInfo = resolveCustomerInputValue(customerInput ? customerInput.value : '');
    record.customer_code = customerInfo.code;
    record.customer_name = customerInfo.name;

    if (!hasValue && !customerInfo.code && !customerInfo.name) {
      alert('請至少填寫客戶或其他欄位');
      return;
    }

    state.records.push(normalizeRecord(record));
    form.reset();
    if (customerInput) {
      customerInput.dataset.code = '';
      customerInput.dataset.name = '';
    }
    renderTable();
  }

  function handleUploadChange(event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.files || !input.files.length) {
      return;
    }
    const file = input.files[0];
    if (!file) {
      return;
    }
    uploadRemittanceFile(file);
  }

  function uploadRemittanceFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    setUploading(true);
    fetch(REMITTANCE_UPLOAD_ENDPOINT, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            if (!response.ok || payload.ok === false) {
              const message = payload && payload.error ? payload.error : `上傳失敗 (${response.status})`;
              throw new Error(message);
            }
            return payload;
          })
      )
      .then((payload) => {
        state.records = Array.isArray(payload.records) ? payload.records.map(normalizeRecord) : [];
        state.editingIndex = null;
        state.editDraft = null;
        renderTable();
        if (payload.message) {
          alert(payload.message);
        }
        if (payload.parse_error) {
          console.warn('[remittance] parse warning:', payload.parse_error);
        }
      })
      .catch((error) => {
        console.error('[remittance] upload failed', error);
        alert(error.message || '上傳失敗，請稍後再試');
      })
      .finally(() => {
        setUploading(false);
      });
  }

  function setUploading(flag) {
    state.uploading = !!flag;
    if (uploadTriggerBtn) {
      uploadTriggerBtn.disabled = state.uploading;
      const baseText = uploadTriggerBtn.dataset.originalText || '上傳 .xlsx';
      uploadTriggerBtn.textContent = state.uploading ? '上傳中…' : baseText;
    }
  }

  function handleTableClick(event) {
    const button = event.target.closest('[data-action]');
    if (!button) {
      return;
    }
    const row = button.closest('tr');
    const index = row ? Number(row.dataset.index) : -1;
    if (!Number.isFinite(index) || index < 0 || index >= state.records.length) {
      return;
    }
    const action = button.dataset.action;
    if (action === 'edit') {
      beginEdit(index);
    } else if (action === 'delete') {
      deleteRecord(index);
    } else if (action === 'save') {
      saveRecord(index, row);
    } else if (action === 'cancel') {
      cancelEdit();
    }
  }

  function beginEdit(index) {
    state.editingIndex = index;
    state.editDraft = { ...state.records[index] };
    renderTable();
  }

  function cancelEdit() {
    state.editingIndex = null;
    state.editDraft = null;
    renderTable();
  }

  function saveRecord(index, row) {
    const inputs = row.querySelectorAll('[data-edit-field]');
    inputs.forEach((input) => {
      if (!(input instanceof HTMLInputElement)) {
        return;
      }
      if (input.dataset.editField === 'customer') {
        const info = resolveCustomerInputValue(input.value);
        state.records[index].customer_code = info.code;
        state.records[index].customer_name = info.name;
      } else {
        state.records[index][input.dataset.editField] = input.value.trim();
      }
    });
    cancelEdit();
  }

  function deleteRecord(index) {
    if (!window.confirm('確定要刪除此筆匯款帳號嗎？')) {
      return;
    }
    state.records.splice(index, 1);
    cancelEdit();
  }

  function renderTable() {
    if (!tableBody) {
      return;
    }
    if (!state.records.length) {
      showTableMessage('尚無匯款帳號，請新增一筆');
      return;
    }
    const rows = state.records
      .map((record, index) => {
        const customerDisplay = record.customer_name || record.customer_code || record.customer || '';
        if (state.editingIndex === index) {
          return renderEditableRow(record, index, customerDisplay);
        }
        return `
          <tr data-index="${index}">
            <td>${escapeHtml(customerDisplay)}</td>
            <td>${escapeHtml(record.account)}</td>
            <td>${escapeHtml(record.bank)}</td>
            <td>${escapeHtml(record.remark)}</td>
            <td class="table__ops">
              <button type="button" class="btn btn--ghost btn--small" data-action="edit">編輯</button>
              <button type="button" class="btn btn--secondary btn--small" data-action="delete">刪除</button>
            </td>
          </tr>
        `;
      })
      .join('');
    tableBody.innerHTML = rows;
  }

  function renderEditableRow(record, index, customerDisplay) {
    return `
      <tr data-index="${index}" class="sales-row--editing">
        <td>
          <input
            type="text"
            class="sales-table__input"
            data-edit-field="customer"
            list="remittance-customer-list"
            value="${escapeAttr(customerDisplay)}"
          >
        </td>
        <td><input type="text" class="sales-table__input" data-edit-field="account" value="${escapeAttr(record.account)}"></td>
        <td><input type="text" class="sales-table__input" data-edit-field="bank" value="${escapeAttr(record.bank)}"></td>
        <td><input type="text" class="sales-table__input" data-edit-field="remark" value="${escapeAttr(record.remark)}"></td>
        <td class="table__ops table__ops--inline">
          <button type="button" class="btn btn--success btn--small" data-action="save">儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-action="cancel">取消</button>
        </td>
      </tr>
    `;
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
    return escapeHtml(value).replace(/\n/g, '&#10;');
  }

  async function loadCustomers() {
    if (!customerInput) {
      return;
    }
    try {
      const response = await fetch(CUSTOMERS_ENDPOINT, { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error(`載入客戶失敗 (${response.status})`);
      }
      const payload = await response.json();
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      const fragment = document.createDocumentFragment();
      rows.forEach((row) => {
        const code = String(row.code || '').trim();
        const name = String(row.name || '').trim();
        if (!code && !name) {
          return;
        }
        const option = document.createElement('option');
        option.value = code && name ? `${code} — ${name}` : code || name;
        fragment.appendChild(option);
        if (code) {
          state.customerMap.set(code, name);
        }
        if (name) {
          state.customerNameMap.set(normalizeCustomerNameKey(name), { code, name });
        }
      });
      if (customerListEl) {
        customerListEl.innerHTML = '';
        customerListEl.appendChild(fragment);
      }
      customerInput.addEventListener('focus', handleCustomerFocus);
      customerInput.addEventListener('input', () =>
        resolveCustomerInput({ strict: false, silent: true, updateValue: false })
      );
      customerInput.addEventListener('change', () => resolveCustomerInput({ strict: false }));
      customerInput.addEventListener('blur', () => resolveCustomerInput({ strict: true }));
    } catch (error) {
      console.error('[remittance] load customers failed', error);
    }
  }

  function loadRemittanceRecords() {
    if (!tableBody) {
      return;
    }
    state.loading = true;
    showTableMessage('資料載入中…');
    fetch(REMITTANCE_LATEST_ENDPOINT, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            if (!response.ok || payload.ok === false) {
              const message = payload && payload.error ? payload.error : `載入失敗 (${response.status})`;
              throw new Error(message);
            }
            return payload;
          })
      )
      .then((payload) => {
        state.records = Array.isArray(payload.records) ? payload.records.map(normalizeRecord) : [];
        state.editingIndex = null;
        state.editDraft = null;
        renderTable();
        if (payload.parse_error) {
          console.warn('[remittance] parse warning:', payload.parse_error);
        }
      })
      .catch((error) => {
        console.error('[remittance] load records failed', error);
        showTableMessage('載入失敗，請重新整理。');
      })
      .finally(() => {
        state.loading = false;
      });
  }

  function normalizeRecord(record = {}) {
    return {
      customer: (record.customer || '').trim(),
      customer_name: (record.customer_name || '').trim(),
      customer_code: (record.customer_code || '').trim(),
      account: (record.account || '').trim(),
      bank: (record.bank || '').trim(),
      remark: (record.remark || '').trim(),
    };
  }

  function showTableMessage(message) {
    if (!tableBody) {
      return;
    }
    tableBody.innerHTML = `<tr><td colspan="5" class="table-empty">${message}</td></tr>`;
  }

  function handleCustomerFocus() {
    if (!customerInput) {
      return;
    }
  }

  function resolveCustomerInput(options) {
    if (!customerInput) {
      return;
    }
    const { strict = false, silent = false, updateValue = true } = options || {};
    const info = resolveCustomerInputValue(customerInput.value);
    if (info.code) {
      customerInput.dataset.code = info.code;
      customerInput.dataset.name = info.name;
      if (updateValue) {
        customerInput.value = info.name || info.code;
      }
      return;
    }
    customerInput.dataset.code = '';
    customerInput.dataset.name = '';
    if (strict) {
      const trimmed = String(customerInput.value || '').trim();
      if (!trimmed) {
        customerInput.value = '';
        return;
      }
      if (!silent) {
        alert('找不到相符的客戶，請重新輸入。');
      }
      customerInput.value = '';
    }
  }

  function resolveCustomerInputValue(text) {
    const trimmed = String(text || '').trim();
    if (!trimmed) {
      return { code: '', name: '' };
    }
    if (state.customerMap.has(trimmed)) {
      const name = state.customerMap.get(trimmed) || '';
      return { code: trimmed, name };
    }
    const normalizedName = normalizeCustomerNameKey(trimmed);
    if (state.customerNameMap.has(normalizedName)) {
      const match = state.customerNameMap.get(normalizedName);
      if (match) {
        return { code: match.code || '', name: match.name || '' };
      }
    }
    const tokens = splitCustomerTokens(trimmed);
    for (const token of tokens) {
      if (state.customerMap.has(token)) {
        const name = state.customerMap.get(token) || '';
        return { code: token, name };
      }
      const normalizedToken = normalizeCustomerNameKey(token);
      if (state.customerNameMap.has(normalizedToken)) {
        const match = state.customerNameMap.get(normalizedToken);
        if (match) {
          return { code: match.code || '', name: match.name || '' };
        }
      }
    }
    const codePattern = trimmed.match(/([A-Za-z0-9]{2,})/g);
    if (codePattern) {
      for (const code of codePattern) {
        if (state.customerMap.has(code)) {
          return { code, name: state.customerMap.get(code) || '' };
        }
      }
    }
    return { code: '', name: trimmed };
  }

  function normalizeCustomerNameKey(value) {
    return String(value || '')
      .replace(/[（）()]/g, '')
      .replace(/\s+/gu, '')
      .toLowerCase();
  }

  function splitCustomerTokens(value) {
    return String(value || '')
      .replace(/[（）()]/g, ' ')
      .split(/[\s,，、／\/\-－﹣–—~～]+/u)
      .map((token) => token.trim())
      .filter(Boolean);
  }
})();
