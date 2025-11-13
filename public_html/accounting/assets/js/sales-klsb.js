(function () {
  'use strict';

  const LATEST_ENDPOINT = '../api/sales/klsb_latest.php';
  const UPLOAD_ENDPOINT = '../api/sales/klsb_upload.php';
  const NOTES_ARCHIVE_ENDPOINT = '../api/sales/notes_archive.php';

  const root = document.body;
  if (!root) {
    return;
  }

  const titleEl = document.querySelector('[data-klsb-title]');
  const navButtons = document.querySelectorAll('[data-klsb-nav]');
  const tableBody = document.querySelector('[data-klsb-rows]');
  const uploadButton = document.querySelector('[data-action="upload-klsb"]');
  const downloadButton = document.querySelector('[data-action="download-klsb"]');
  let uploadInput = document.querySelector('[data-klsb-upload-input]');

  const state = {
    year: parseInt(root.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(root.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    records: [],
    uploading: false,
    downloading: false,
    editingIndex: null,
    editDraft: null,
    noteMonthMap: new Map(),
    noteMapLoaded: false,
    noteMapLoading: null,
  };

  init();

  function init() {
    updateTitle();
    bindEvents();
    loadRecords();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.klsbNav;
        if (dir === 'prev') {
          shiftMonth(-1);
        } else if (dir === 'next') {
          shiftMonth(1);
        }
      });
    });

    if (uploadButton) {
      uploadButton.addEventListener('click', () => {
        if (state.uploading) {
          return;
        }
        ensureUploadInput();
        uploadInput.value = '';
        uploadInput.click();
      });
    }

    ensureUploadInput();
    if (uploadInput) {
      uploadInput.addEventListener('change', handleUploadChange);
    }

    if (downloadButton) {
      downloadButton.addEventListener('click', () => {
        alert('下載功能尚未串接，請稍後再試。');
      });
    }

    if (tableBody) {
      tableBody.addEventListener('click', handleTableClick);
    }
  }

  function ensureUploadInput() {
    if (!(uploadInput instanceof HTMLInputElement)) {
      uploadInput = document.createElement('input');
      uploadInput.type = 'file';
      uploadInput.hidden = true;
      uploadInput.accept = '.xlsx,.xls,.csv,.pdf,.jpg,.jpeg,.png';
      uploadInput.setAttribute('data-klsb-upload-input', 'dynamic');
      document.body.appendChild(uploadInput);
    }
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
    uploadStatement(file);
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
    updateTitle();
    loadRecords();
  }

  function updateTitle() {
    if (!titleEl) {
      return;
    }
    const rocYear = state.year - 1911;
    titleEl.textContent = `${rocYear}年${state.month}月基隆二信明細`;
  }

  function loadRecords() {
    if (!tableBody) {
      return;
    }
    tableBody.innerHTML = '<tr><td colspan="9" class="table-empty">資料載入中…</td></tr>';
    const params = new URLSearchParams({
      year: String(state.year),
      month: String(state.month),
    });

    Promise.all([fetchKlsbRecords(params), ensureNoteMonthMap()])
      .then(([records]) => {
        state.records = records;
        applyReceivableMatching();
        renderTable();
      })
      .catch((error) => {
        console.error('[klsb] load failed', error);
        if (tableBody) {
          tableBody.innerHTML = '<tr><td colspan="9" class="table-empty">載入失敗，請嘗試重新整理。</td></tr>';
        }
      });
  }

  function fetchKlsbRecords(params) {
    return fetch(`${LATEST_ENDPOINT}?${params.toString()}`, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            if (!response.ok) {
              throw new Error('load failed');
            }
            if (payload.parse_error) {
              console.info('[klsb] parse info:', payload.parse_error);
            }
            return Array.isArray(payload.records) ? payload.records : [];
          })
      );
  }

  function ensureNoteMonthMap() {
    if (state.noteMapLoaded && state.noteMonthMap instanceof Map) {
      return Promise.resolve(state.noteMonthMap);
    }
    if (state.noteMapLoading) {
      return state.noteMapLoading;
    }
    state.noteMapLoading = fetch(NOTES_ARCHIVE_ENDPOINT, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            if (!response.ok) {
              throw new Error('notes archive load failed');
            }
            state.noteMonthMap = buildArchiveNoteMap(Array.isArray(payload.records) ? payload.records : []);
            state.noteMapLoaded = true;
            return state.noteMonthMap;
          })
      )
      .catch((error) => {
        console.warn('[klsb] failed to load receivable archive', error);
        state.noteMonthMap = new Map();
        state.noteMapLoaded = true;
        return state.noteMonthMap;
      })
      .finally(() => {
        state.noteMapLoading = null;
      });
    return state.noteMapLoading;
  }

  function renderTable() {
    if (!tableBody) {
      return;
    }
    if (!Array.isArray(state.records) || state.records.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="9" class="table-empty">尚無資料</td></tr>';
      return;
    }

    const rows = state.records
      .map((record, index) => {
        if (state.editingIndex === index) {
          return renderEditableRow(record, index);
        }
        return `
          <tr data-index="${index}">
            <td>${escapeHtml(record.transaction_date || '')}</td>
            <td>${escapeHtml(record.description || '')}</td>
            <td class="text-end">${formatCurrency(record.expense)}</td>
            <td class="text-end">${formatCurrency(record.income)}</td>
            <td class="text-end">${formatCurrency(record.balance)}</td>
            <td>${escapeHtml(record.note || '')}</td>
            <td>${escapeHtml(record.reconciliation || '')}</td>
            <td>${escapeHtml(record.form || '')}</td>
            <td class="table__ops">
              <button type="button" class="btn btn--ghost btn--small" data-action="edit-row">編輯</button>
            </td>
          </tr>
        `;
      })
      .join('');

    tableBody.innerHTML = rows;
  }

  function applyReceivableMatching() {
    if (!Array.isArray(state.records) || !(state.noteMonthMap instanceof Map)) {
      return;
    }
    state.records.forEach((record) => {
      if (!record || typeof record.description !== 'string') {
        return;
      }
      if (record.description.indexOf('管收他票') === -1) {
        return;
      }
      const lastFive = extractLastFiveDigits(`${record.note || ''}${record.description || ''}`);
      if (!lastFive) {
        return;
      }
      const monthsText = getMonthsBySuffix(lastFive);
      if (!monthsText) {
        return;
      }
      record.reconciliation = monthsText;
    });
  }

  function renderEditableRow(record, index) {
    const draft = state.editDraft || {};
    const reconciliation = draft.reconciliation ?? record.reconciliation ?? '';
    const form = draft.form ?? record.form ?? '';
    return `
      <tr data-index="${index}" class="sales-row--editing">
        <td>${escapeHtml(record.transaction_date || '')}</td>
        <td>${escapeHtml(record.description || '')}</td>
        <td class="text-end">${formatCurrency(record.expense)}</td>
        <td class="text-end">${formatCurrency(record.income)}</td>
        <td class="text-end">${formatCurrency(record.balance)}</td>
        <td>${escapeHtml(record.note || '')}</td>
        <td><input type="text" class="sales-table__input" data-edit-field="reconciliation" value="${escapeAttr(reconciliation)}" placeholder="對帳狀態"></td>
        <td><input type="text" class="sales-table__input" data-edit-field="form" value="${escapeAttr(form)}" placeholder="對應表單"></td>
        <td class="table__ops table__ops--inline">
          <button type="button" class="btn btn--success btn--small" data-action="save-row">儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-action="cancel-row">取消</button>
        </td>
      </tr>
    `;
  }

  function buildArchiveNoteMap(records) {
    const map = new Map();
    records.forEach((record) => {
      const suffix = String(record?.suffix || '').trim();
      if (!suffix) {
        return;
      }
      const months = Array.isArray(record?.months) ? record.months.map(normalizeMonthToken).filter(Boolean) : [];
      if (!months.length) {
        return;
      }
      const bucket = map.get(suffix) || new Set();
      months.forEach((item) => bucket.add(item));
      map.set(suffix, bucket);
    });
    return map;
  }

  function extractLastFiveDigits(text) {
    const digits = String(text || '').replace(/\D/g, '');
    if (digits.length < 5) {
      return '';
    }
    return digits.slice(-5);
  }

  function normalizeMonthToken(value) {
    const text = String(value || '').trim();
    if (!text) {
      return '';
    }
    let match = text.match(/^(\d{2,3})年(\d{1,2})月$/);
    if (match) {
      return `${match[1]}/${Number(match[2])}`;
    }
    match = text.match(/^(\d{2,3})[\/-](\d{1,2})$/);
    if (match) {
      return `${match[1]}/${Number(match[2])}`;
    }
    match = text.match(/^(\d{4})[\/-](\d{1,2})$/);
    if (match) {
      const roc = Number(match[1]) - 1911;
      return `${roc}/${Number(match[2])}`;
    }
    return '';
  }

  function getMonthsBySuffix(key) {
    if (!state.noteMonthMap || !(state.noteMonthMap instanceof Map)) {
      return '';
    }
    const bucket = state.noteMonthMap.get(key);
    if (!bucket || !bucket.size) {
      return '';
    }
    const list = Array.from(bucket);
    list.sort((a, b) => a.localeCompare(b, 'zh-Hant'));
    return list.join('、');
  }

  function uploadStatement(file) {
    const formData = new FormData();
    formData.append('year', String(state.year));
    formData.append('month', String(state.month));
    formData.append('file', file);

    state.uploading = true;
    fetch(UPLOAD_ENDPOINT, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then((response) => response.json())
      .then((payload) => {
        if (payload && payload.records) {
          state.records = payload.records;
          renderTable();
        }
        if (payload && payload.message) {
          alert(payload.message);
        }
        if (payload && payload.parse_error) {
          console.info('[klsb] parse info:', payload.parse_error);
        }
      })
      .catch((error) => {
        console.error('[klsb] upload failed', error);
        alert('上傳失敗，請稍後再試。');
      })
      .finally(() => {
        state.uploading = false;
        if (uploadInput) {
          uploadInput.value = '';
        }
      });
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
    if (action === 'edit-row') {
      state.editingIndex = index;
      state.editDraft = {
        reconciliation: state.records[index].reconciliation || '',
        form: state.records[index].form || '',
      };
      renderTable();
    } else if (action === 'cancel-row') {
      state.editingIndex = null;
      state.editDraft = null;
      renderTable();
    } else if (action === 'save-row') {
      const inputs = row.querySelectorAll('[data-edit-field]');
      const draft = { ...state.records[index] };
      inputs.forEach((input) => {
        if (input instanceof HTMLInputElement) {
          draft[input.dataset.editField] = input.value.trim();
        }
      });
      state.records[index] = draft;
      state.editingIndex = null;
      state.editDraft = null;
      renderTable();
    }
  }

  function formatCurrency(value) {
    const num = Number(value);
    if (!Number.isFinite(num) || num === 0) {
      return '';
    }
    return num.toLocaleString('zh-TW');
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
})();
