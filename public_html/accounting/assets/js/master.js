(function () {
  'use strict';

  const API_BASE = '../api/master-data/';
const UPLOAD_ENDPOINT = '../api/master-data/upload.php';

  const TABS = [
    {
      id: 'customers',
      label: '客戶資料',
      endpoint: `${API_BASE}master_customers.php`,
      columns: [
        { key: 'code', label: '代號' },
        { key: 'name', label: '客戶名稱' },
        { key: 'tax_id', label: '統一編號' },
        { key: 'contact', label: '聯絡人' },
        { key: 'phone', label: '電話' },
        { key: 'tax_formula', label: '稅務公式' },
        { key: '_ops', label: '操作' },
      ],
      formFields: [
        { key: 'code', label: '代號', required: true, placeholder: '例：C0001', maxLength: 50 },
        { key: 'name', label: '客戶名稱', required: true, maxLength: 150 },
        { key: 'tax_id', label: '統一編號', maxLength: 50 },
        { key: 'contact', label: '聯絡人', maxLength: 100 },
        { key: 'phone', label: '電話', maxLength: 50 },
        { key: 'tax_formula', label: '稅務公式', maxLength: 100 },
      ],
    },
    {
      id: 'vehicles',
      label: '車輛資料',
      endpoint: `${API_BASE}master_vehicles.php`,
      columns: [
        { key: 'code', label: '代號' },
        { key: 'license', label: '車牌號碼' },
        { key: 'model', label: '車型' },
        { key: 'brand', label: '廠牌' },
        { key: 'driver', label: '司機' },
        { key: '_ops', label: '操作' },
      ],
      formFields: [
        { key: 'code', label: '代號', required: true, placeholder: '例：V0001', maxLength: 50 },
        { key: 'license', label: '車牌號碼', required: true, placeholder: '例：ABC-1234', maxLength: 100 },
        { key: 'model', label: '車型', maxLength: 100 },
        { key: 'brand', label: '廠牌', maxLength: 100 },
        { key: 'driver', label: '司機', maxLength: 100 },
      ],
    },
    {
      id: 'employees',
      label: '員工資料',
      endpoint: `${API_BASE}master_employees.php`,
      columns: [
        { key: 'code', label: '代號' },
        { key: 'name', label: '員工姓名' },
        { key: 'phone', label: '電話' },
        { key: '_ops', label: '操作' },
      ],
      formFields: [
        { key: 'code', label: '代號', required: true, placeholder: '例：E0001', maxLength: 50 },
        { key: 'name', label: '員工姓名', required: true, maxLength: 100 },
        { key: 'phone', label: '電話', maxLength: 50 },
      ],
    },
    {
      id: 'accounts',
      label: '會計科目',
      endpoint: `${API_BASE}account_mappings.php`,
      columns: [
        { key: 'name', label: '對應表單' },
        { key: 'mapping', label: '會計科目' },
        { key: '_ops', label: '操作' },
      ],
      formFields: [
        { key: 'name', label: '對應表單', required: true, maxLength: 150 },
        { key: 'mapping', label: '會計科目', required: true, maxLength: 100 },
      ],
    },
  ];

  const TAB_PARAM = 'tab';
  const TAB_IDS = TABS.map((tab) => tab.id);
  const DEFAULT_TAB = TABS[0].id;
  const initialTabParam = new URLSearchParams(window.location.search).get(TAB_PARAM);
  const initialTab = TAB_IDS.includes(initialTabParam) ? initialTabParam : DEFAULT_TAB;

  const state = {
    activeTab: initialTab,
    cache: Object.create(null),
    editingId: null,
  };

  const tabListEl = document.querySelector('[data-tab-list]');
  const tableContainerEl = document.querySelector('[data-table-container]');
  const statusEl = document.querySelector('[data-status]');
  const messageEl = document.querySelector('[data-message]');
  const formContainerEl = document.querySelector('[data-form-container]');
  const cardActionsEl = document.querySelector('[data-card-actions]');
  const tabLinkEls = document.querySelectorAll('[data-tab-link]');
  let uploadInput = null;

  if (!tabListEl || !tableContainerEl || !statusEl || !formContainerEl) {
    return;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    renderTabs();
    updateActiveTabStyles();
    syncTabLinks();
    setupUploadInput();
    bindEvents();
    loadActiveTab({ force: true });
    updateUrlWithTab(state.activeTab);
  }

  function renderTabs() {
    const fragment = document.createDocumentFragment();
    TABS.forEach((tab) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'tab' + (tab.id === state.activeTab ? ' tab--active' : '');
      button.textContent = tab.label;
      button.dataset.tabId = tab.id;
      fragment.appendChild(button);
    });
    tabListEl.innerHTML = '';
    tabListEl.appendChild(fragment);
  }

  
  function updateActiveTabStyles() {
    tabListEl.querySelectorAll('.tab').forEach((tabEl) => {
      const isActive = tabEl.dataset.tabId === state.activeTab;
      tabEl.classList.toggle('tab--active', isActive);
    });
  }

  function syncTabLinks() {
    tabLinkEls.forEach((link) => {
      const target = link.dataset.tabLink;
      const isActive = target === state.activeTab;
      link.classList.toggle('sidebar__subnav-item--active', isActive);
    });
  }

function bindEvents() {
    tabListEl.addEventListener('click', (event) => {
      const button = event.target.closest('.tab');
      if (!button) return;
      const tabId = button.dataset.tabId;
      if (!tabId) return;
      changeTab(tabId);
    });

    tabLinkEls.forEach((link) => {
      link.addEventListener('click', (event) => {
        const target = link.dataset.tabLink;
        if (!target) {
          return;
        }
        event.preventDefault();
        changeTab(target, { force: true });
      });
    });

    if (cardActionsEl) {
      cardActionsEl.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const action = button.dataset.action;
        if (action === 'create') {
          exitEditMode({ silent: true });
          focusFirstField();
          showMessage('info', '請填寫表單後送出新增。');
        } else if (action === 'upload') {
          event.preventDefault();
          setupUploadInput();
          if (uploadInput) {
            uploadInput.value = '';
            uploadInput.click();
          } else {
            showMessage('error', '無法啟動上傳功能，請重新整理頁面。');
          }
        }
      });
    }

    formContainerEl.addEventListener('submit', (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.dataset.entryForm) return;
      event.preventDefault();
      handleFormSubmit(form);
    });

    tableContainerEl.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action]');
      if (!button) return;
      const action = button.dataset.action;
      const rowId = button.dataset.id;
      if (!rowId && action !== 'cancel-inline') return;
      if (action === 'edit') {
        event.preventDefault();
        enterEditMode(rowId);
      } else if (action === 'delete') {
        event.preventDefault();
        const label = button.dataset.label || rowId;
        if (window.confirm(`確定要刪除「${label}」嗎？`)) {
          handleDelete(rowId);
        }
      } else if (action === 'save-inline') {
        event.preventDefault();
        const rowEl = button.closest('tr');
        handleInlineSave(rowId, rowEl);
      } else if (action === 'cancel-inline') {
        event.preventDefault();
        exitEditMode();
      }
    });
  }

  function changeTab(tabId, options = {}) {
    if (!TAB_IDS.includes(tabId)) {
      return;
    }
    const { force = false } = options;
    if (!force && tabId === state.activeTab) {
      return;
    }

    state.activeTab = tabId;
    state.editingId = null;
    clearMessage();
    updateActiveTabStyles();
    syncTabLinks();
    loadActiveTab({ force: true });
    updateUrlWithTab(tabId);
    loadPendingUploads();
  }

  function getActiveTab() {
    return TABS.find((tab) => tab.id === state.activeTab) || TABS[0];
  }

  function loadActiveTab(options = {}) {
    const { force = false } = options;
    const tab = getActiveTab();
    if (!tab) return;

    if (force) {
      state.cache[tab.id] = undefined;
    }

    renderForm(tab);
    showLoading();

    const cached = state.cache[tab.id];
    if (Array.isArray(cached) && !force) {
      renderTable(tab, cached);
      return;
    }

    apiFetch(tab.endpoint)
      .then((payload) => {
        const rows = Array.isArray(payload.data) ? payload.data : [];
        state.cache[tab.id] = rows;
        renderTable(tab, rows);
      })
      .catch((error) => {
        renderError(error.message);
      });
  }

  function renderForm(tab) {
    const form = document.createElement('form');
    form.className = 'entry-form';
    form.dataset.entryForm = 'true';

    const header = document.createElement('div');
    header.className = 'entry-form__header';

    const title = document.createElement('h3');
    title.className = 'entry-form__title';
    title.textContent = `新增 ${tab.label}`;
    header.appendChild(title);

    form.appendChild(header);

    const grid = document.createElement('div');
    grid.className = 'entry-form__grid';

    tab.formFields.forEach((field) => {
      const fieldEl = document.createElement('div');
      fieldEl.className = 'entry-form__field';

      const labelEl = document.createElement('label');
      labelEl.className = 'entry-form__label';
      labelEl.setAttribute('for', `field-${tab.id}-${field.key}`);
      labelEl.textContent = field.label + (field.required ? ' *' : '');

      const inputEl = document.createElement('input');
      inputEl.className = 'entry-form__input';
      inputEl.type = 'text';
      inputEl.name = field.key;
      inputEl.id = `field-${tab.id}-${field.key}`;
      if (field.placeholder) inputEl.placeholder = field.placeholder;
      if (field.maxLength) inputEl.maxLength = field.maxLength;
      if (field.required) inputEl.required = true;

      fieldEl.appendChild(labelEl);
      fieldEl.appendChild(inputEl);
      grid.appendChild(fieldEl);
    });

    form.appendChild(grid);

    const actions = document.createElement('div');
    actions.className = 'entry-form__actions';

    const submitBtn = document.createElement('button');
    submitBtn.type = 'submit';
    submitBtn.className = 'btn';
    submitBtn.textContent = '新增';
    actions.appendChild(submitBtn);

    form.appendChild(actions);

    formContainerEl.innerHTML = '';
    formContainerEl.appendChild(form);
  }

  function collectFormData(form, fields) {
    const errors = [];
    const values = {};

    fields.forEach((field) => {
      const input = form.querySelector(`[name="${field.key}"]`);
      let value = '';
      if (input) {
        value = typeof input.value === 'string' ? input.value.trim() : '';
      }
      if (field.required && value === '') {
        errors.push(`${field.label}為必填`);
      }
      values[field.key] = value;
    });

    return { values, errors };
  }

  function handleFormSubmit(form) {
    const tab = getActiveTab();
    if (!tab) return;

    const { values, errors } = collectFormData(form, tab.formFields);
    if (errors.length > 0) {
      showMessage('error', errors.join('、'));
      return;
    }

    const payload = Object.assign({}, values, {
      action: 'create',
    });

    showMessage('info', '新增中…');

    apiFetch(tab.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(() => {
        form.reset();
        showMessage('success', '新增完成');
        state.cache[tab.id] = undefined;
        loadActiveTab({ force: true });
      })
      .catch((error) => {
        showMessage('error', error.message);
      });
  }

  function enterEditMode(rowId) {
    const tab = getActiveTab();
    const rows = state.cache[tab.id] || [];
    const row = rows.find((item) => String(item.id) === String(rowId));
    if (!row) {
      showMessage('error', '找不到要編輯的資料。');
      return;
    }
    state.editingId = row.id;
    renderTable(tab, rows);
    const label = row.code || row.name || row.mapping || row.license || String(row.id);
    showMessage('info', `正在編輯「${label}」`);
  }

  function exitEditMode(options = {}) {
    const { silent = false } = options;
    if (state.editingId === null) {
      return;
    }
    state.editingId = null;
    const tab = getActiveTab();
    const rows = state.cache[tab.id] || [];
    renderTable(tab, rows);
    if (!silent) {
      showMessage('info', '已取消行內編輯。');
    }
  }

  function handleInlineSave(rowId, rowEl) {
    const tab = getActiveTab();
    if (!tab || !rowEl) {
      return;
    }
    const rows = state.cache[tab.id] || [];
    const existing = rows.find((item) => String(item.id) === String(rowId)) || {};
    const values = {};
    const errors = [];

    tab.formFields.forEach((field) => {
      const input = rowEl.querySelector(`[data-inline-field="${field.key}"]`);
      let value = '';
      if (input && typeof input.value === 'string') {
        value = input.value.trim();
      } else if (existing[field.key] !== undefined && existing[field.key] !== null) {
        value = String(existing[field.key]);
      }
      if (field.required && value === '') {
        errors.push(`${field.label}為必填`);
      }
      if (field.maxLength && value.length > field.maxLength) {
        errors.push(`${field.label} 最多 ${field.maxLength} 字`);
      }
      values[field.key] = value;
    });

    if (errors.length) {
      showMessage('error', errors.join('；'));
      return;
    }

    const payload = Object.assign(
      {
        action: 'update',
        id: rowId,
      },
      values
    );

    showMessage('info', '更新中…');

    apiFetch(tab.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(() => {
        showMessage('success', '更新完成');
        state.editingId = null;
        state.cache[tab.id] = undefined;
        loadActiveTab({ force: true });
      })
      .catch((error) => {
        showMessage('error', error.message);
      });
  }

  function handleDelete(rowId) {
    const tab = getActiveTab();
    if (!tab) return;
    showMessage('info', '刪除中…');
    apiFetch(tab.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id: rowId }),
    })
      .then(() => {
        showMessage('success', '刪除完成');
        if (state.editingId !== null && String(state.editingId) === String(rowId)) {
          exitEditMode({ silent: true });
        }
        state.cache[tab.id] = undefined;
        loadActiveTab({ force: true });
      })
      .catch((error) => {
        showMessage('error', error.message);
      });
  }

  function showLoading() {
    statusEl.textContent = '資料讀取中…';
    statusEl.className = 'loading-state';
    tableContainerEl.innerHTML = '';
  }

  function renderError(message) {
    statusEl.textContent = `讀取失敗：${message}`;
    statusEl.className = 'error-state';
    tableContainerEl.innerHTML = '';
  }

  function renderEmpty() {
    statusEl.textContent = '暫無資料，請點右上角「新增」或「上傳舊檔」。';
    statusEl.className = 'empty-state';
    tableContainerEl.innerHTML = '';
  }

  function renderTable(tab, rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      renderEmpty();
      return;
    }

    const headers = tab.columns
      .map((column) => `<th scope="col">${escapeHtml(column.label)}</th>`)
      .join('');

    const editingId = state.editingId !== null ? String(state.editingId) : null;
    const body = rows
      .map((row) => {
        const rowId = row.id !== undefined && row.id !== null ? String(row.id) : '';
        if (editingId && rowId && editingId === rowId) {
          return renderEditableRow(row, tab);
        }
        return renderStaticRow(row, tab);
      })
      .join('');

    tableContainerEl.innerHTML = `
      <table>
        <thead>
          <tr>${headers}</tr>
        </thead>
        <tbody>
          ${body}
        </tbody>
      </table>
    `;

    tableContainerEl.scrollLeft = 0;
    statusEl.textContent = '';
    statusEl.className = '';
  }

  function renderStaticRow(row, tab) {
    const rowId = row.id !== undefined && row.id !== null ? String(row.id) : '';
    const cells = tab.columns
      .map((column) => {
        if (column.key === '_ops') {
          return `<td class="table__ops">${renderOpsButtons(row)}</td>`;
        }
        const value = row[column.key];
        return `<td>${escapeHtml(formatValue(value))}</td>`;
      })
      .join('');
    const attr = rowId ? ` data-row-id="${escapeHtml(rowId)}"` : '';
    return `<tr${attr}>${cells}</tr>`;
  }

  function renderEditableRow(row, tab) {
    const rowId = row.id !== undefined && row.id !== null ? String(row.id) : '';
    const cells = tab.columns
      .map((column) => {
        if (column.key === '_ops') {
          const idAttr = rowId ? ` data-id="${escapeHtml(rowId)}"` : '';
          return `
            <td class="table__ops table__ops--inline">
              <button type="button" class="btn btn--success btn--small" data-action="save-inline"${idAttr}>儲存</button>
              <button type="button" class="btn btn--secondary btn--small" data-action="cancel-inline">取消</button>
            </td>
          `;
        }
        const value = row[column.key];
        return `<td>${renderInlineField(tab, column.key, value)}</td>`;
      })
      .join('');
    const attr = rowId ? ` data-row-id="${escapeHtml(rowId)}"` : '';
    return `<tr class="master-row--editing"${attr}>${cells}</tr>`;
  }

  function renderInlineField(tab, key, value) {
    const field =
      tab.formFields.find((item) => item.key === key) || {
        key,
        label: key,
      };
    const maxLength = field.maxLength ? ` maxlength="${field.maxLength}"` : '';
    const required = field.required ? ' required' : '';
    return `<input type="text" class="table-inline-input" data-inline-field="${escapeHtml(
      key
    )}" value="${escapeAttr(value)}"${maxLength}${required}>`;
  }

  function renderOpsButtons(row) {
    if (row.id === undefined || row.id === null) return '';
    const id = String(row.id);
    const label = row.code || row.name || row.mapping || row.license || id;
    const idAttr = escapeHtml(id);
    const labelAttr = escapeHtml(label);
    return [
      `<button type="button" class="btn btn--ghost" data-action="edit" data-id="${idAttr}">編輯</button>`,
      `<button type="button" class="btn btn--secondary" data-action="delete" data-id="${idAttr}" data-label="${labelAttr}">刪除</button>`
    ].join('');
  }

  function formatValue(value) {
    if (value === null || value === undefined) return '';
    return String(value);
  }

  function showMessage(type, text) {
    if (!messageEl) return;
    if (!text) {
      clearMessage();
      return;
    }
    const allowed = ['success', 'error', 'info'];
    const modifier = allowed.includes(type) ? type : 'info';
    messageEl.hidden = false;
    messageEl.className = `notice notice--${modifier}`;
    messageEl.textContent = text;
  }

  function clearMessage() {
    if (!messageEl) return;
    messageEl.hidden = true;
    messageEl.className = 'notice';
    messageEl.textContent = '';
  }

  function focusFirstField() {
    window.requestAnimationFrame(() => {
      const input = formContainerEl.querySelector('.entry-form__input');
      if (input && typeof input.focus === 'function') {
        input.focus();
        if (typeof input.select === 'function') {
          input.select();
        }
      }
    });
  }

  function setupUploadInput() {
    if (uploadInput || !cardActionsEl) {
      return;
    }
    uploadInput = document.createElement('input');
    uploadInput.type = 'file';
    uploadInput.multiple = true;
    uploadInput.accept = '.xls,.xlsx,.csv,.pdf,.jpg,.jpeg';
    uploadInput.hidden = true;
    uploadInput.setAttribute('data-master-upload', 'true');
    cardActionsEl.appendChild(uploadInput);
    uploadInput.addEventListener('change', handleUploadChange);
  }

  function handleUploadChange() {
    const files = Array.from(uploadInput.files || []);
    if (!files.length) {
      return;
    }

    const formData = new FormData();
    formData.append('tab', state.activeTab);
    files.forEach((file) => formData.append('files[]', file));

    showMessage('info', '檔案上傳中…');

    apiFetch(UPLOAD_ENDPOINT, {
      method: 'POST',
      body: formData,
    })
      .then((payload) => {
        uploadInput.value = '';
        const message = payload && payload.message ? payload.message : '上傳完成';
        showMessage('success', message);
        if (payload && Array.isArray(payload.errors) && payload.errors.length) {
          console.warn('部分檔案未成功上傳', payload.errors);
        }
        loadActiveTab({ force: true });
      })
      .catch((error) => {
        uploadInput.value = '';
        showMessage('error', error.message);
      });
  }


  function apiFetch(url, options = {}) {
    const config = Object.assign(
      {
        credentials: 'same-origin',
      },
      options,
      {
        headers: Object.assign(
          {
            Accept: 'application/json',
          },
          options.headers || {}
        ),
      }
    );

    return fetch(url, config).then(async (response) => {
      let payload = null;
      try {
        payload = await response.json();
      } catch (error) {
        payload = null;
      }

      if (!response.ok) {
        const message = payload && typeof payload === 'object' ? payload.error : null;
        throw new Error(message || `HTTP ${response.status}`);
      }

      if (!payload || payload.ok !== true) {
        const message = payload && typeof payload === 'object' ? payload.error : null;
        throw new Error(message || '未知錯誤');
      }

      return payload;
    });
  }

  function updateUrlWithTab(tabId) {
    if (!window.history || typeof window.history.replaceState !== 'function') {
      return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set(TAB_PARAM, tabId);
    window.history.replaceState({}, '', url);
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

  function escapeAttr(value) {
    return escapeHtml(value).replace(/\n/g, '&#10;');
  }
})();
