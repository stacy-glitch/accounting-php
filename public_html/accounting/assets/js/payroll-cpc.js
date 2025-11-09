(function () {
  'use strict';

  const periodEl = document.querySelector('[data-cpc-period]');
  const prevBtn = document.querySelector('[data-cpc-nav="prev"]');
  const nextBtn = document.querySelector('[data-cpc-nav="next"]');
  const summaryEl = document.querySelector('[data-cpc-summary]');
  const uploadBtn = document.querySelector('[data-cpc-upload]');
  const uploadInput = document.querySelector('[data-cpc-upload-input]');
  const uploadMessage = document.querySelector('[data-cpc-upload-message]');
  const tableBody = document.querySelector('[data-cpc-table-body]');

  if (!periodEl || !tableBody) return;

  const dataset = document.body.dataset || {};
  const state = {
    year: Number(dataset.initialRocYear) || new Date().getFullYear() - 1911,
    month: Number(dataset.initialMonth) || new Date().getMonth(),
    records: [],
    loading: false,
  };
  if (state.month <= 0) {
    state.month += 12;
    state.year -= 1;
  }

  init();

  function init() {
    renderPeriod();
    fetchRecords();
    prevBtn?.addEventListener('click', () => adjustMonth(-1));
    nextBtn?.addEventListener('click', () => adjustMonth(1));
    tableBody.addEventListener('click', handleTableClick);
    if (uploadBtn && uploadInput) {
      uploadBtn.addEventListener('click', () => uploadInput.click());
      uploadInput.addEventListener('change', () => {
        const file = uploadInput.files && uploadInput.files[0];
        if (file) handleUpload(file);
      });
    }
  }

  function adjustMonth(step) {
    state.month += step;
    while (state.month <= 0) {
      state.month += 12;
      state.year -= 1;
    }
    while (state.month > 12) {
      state.month -= 12;
      state.year += 1;
    }
    editingState.id = null;
    renderPeriod();
    fetchRecords();
  }

  const editingState = { id: null };

  function fetchRecords() {
    state.loading = true;
    renderPlaceholder('載入中…');
    const params = new URLSearchParams({ roc_year: state.year, month: state.month });
    fetch(`../api/payroll/cpc_records.php?${params.toString()}`, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            state.records = Array.isArray(data.records) ? data.records : [];
            editingState.id = null;
            renderRows();
          })
      )
      .catch((error) => {
        renderPlaceholder(error?.message || '載入失敗');
      })
      .finally(() => {
        state.loading = false;
      });
  }

  function renderPeriod() {
    periodEl.textContent = `${state.year}年${String(state.month).padStart(2, '0')}月中油表`;
  }

  function renderPlaceholder(text) {
    tableBody.innerHTML = `<tr><td colspan="7" class="table-empty">${escapeHtml(text)}</td></tr>`;
    if (summaryEl) summaryEl.textContent = '已載入 0 筆資料';
  }

  function renderRows() {
    tableBody.innerHTML = '';
    if (summaryEl) summaryEl.textContent = `已載入 ${state.records.length} 筆資料`;
    if (!state.records.length) {
      renderPlaceholder(`目前沒有 ${state.year} 年 ${state.month} 月的中油資料`);
      return;
    }
    state.records.sort((a, b) => {
      const plateCompare = String(a.license_plate || '').localeCompare(String(b.license_plate || ''));
      if (plateCompare !== 0) return plateCompare;
      return String(a.trade_date || '').localeCompare(String(b.trade_date || ''));
    });

    state.records.forEach((record) => {
      const row = document.createElement('tr');
      row.dataset.cpcRow = String(record.id);
      const isEditing = editingState.id === record.id;
      row.classList.toggle('labor-row--editing', isEditing);
      row.innerHTML = isEditing ? buildEditingRow(record) : buildDisplayRow(record);
      tableBody.appendChild(row);
    });
  }

  function buildDisplayRow(record) {
    return `
      <td>${escapeHtml(record.license_plate || '')}</td>
      <td>${escapeHtml(record.driver || '')}</td>
      <td>${escapeHtml(record.trade_date || '')}</td>
      <td>${escapeHtml(record.station || '')}</td>
      <td class="cpc-amount">$ ${formatNumber(record.amount)}</td>
      <td class="cpc-note">${escapeHtml(record.note || '') || '&nbsp;'}</td>
      <td class="table__ops">
        <button type="button" class="btn btn--ghost btn--small" data-cpc-action="edit" data-cpc-id="${record.id}">編輯</button>
        <button type="button" class="btn btn--danger-soft btn--small" data-cpc-action="delete" data-cpc-id="${record.id}">刪除</button>
      </td>
    `;
  }

  function buildEditingRow(record) {
    return `
      <td><input type="text" class="cpc-input" data-cpc-input="license_plate" value="${escapeAttr(record.license_plate || '')}"></td>
      <td><input type="text" class="cpc-input" data-cpc-input="driver" value="${escapeAttr(record.driver || '')}"></td>
      <td><input type="text" class="cpc-input" data-cpc-input="trade_date" value="${escapeAttr(record.trade_date || '')}"></td>
      <td><input type="text" class="cpc-input" data-cpc-input="station" value="${escapeAttr(record.station || '')}"></td>
      <td><input type="number" class="cpc-input cpc-input--amount" data-cpc-input="amount" value="${escapeAttr(record.amount)}"></td>
      <td><input type="text" class="cpc-input" data-cpc-input="note" value="${escapeAttr(record.note || '')}"></td>
      <td>
        <div class="labor-edit-actions">
          <button type="button" class="btn btn--small" data-cpc-save>儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-cpc-cancel>取消</button>
        </div>
      </td>
    `;
  }

  function handleTableClick(event) {
    const saveBtn = event.target.closest('[data-cpc-save]');
    if (saveBtn) {
      const row = saveBtn.closest('[data-cpc-row]');
      handleSave(row);
      return;
    }
    const cancelBtn = event.target.closest('[data-cpc-cancel]');
    if (cancelBtn) {
      editingState.id = null;
      renderRows();
      return;
    }
    const actionBtn = event.target.closest('[data-cpc-action]');
    if (!actionBtn) return;
    const recordId = Number(actionBtn.dataset.cpcId);
    if (!recordId) return;
    if (actionBtn.dataset.cpcAction === 'edit') {
      editingState.id = recordId;
      renderRows();
    } else if (actionBtn.dataset.cpcAction === 'delete') {
      deleteRecord(recordId);
    }
  }

  function handleSave(row) {
    if (!row) return;
    const recordId = Number(row.dataset.cpcRow);
    if (!recordId) return;
    const payload = collectInputs(row);
    if (!payload.license_plate) {
      window.alert('請輸入車牌號碼');
      return;
    }
    setUploadMessage('info', '儲存中…');
    fetch('../api/payroll/cpc_records.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ id: recordId, ...payload }),
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            setUploadMessage('success', '已儲存變更');
            editingState.id = null;
            fetchRecords();
          })
      )
      .catch((error) => setUploadMessage('error', error?.message || '儲存失敗'));
  }

  function deleteRecord(recordId) {
    if (!window.confirm('確定要刪除這筆資料嗎？')) return;
    setUploadMessage('info', '刪除中…');
    fetch(`../api/payroll/cpc_records.php?id=${recordId}`, {
      method: 'DELETE',
      credentials: 'same-origin',
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            setUploadMessage('success', '已刪除資料');
            editingState.id = null;
            fetchRecords();
          })
      )
      .catch((error) => setUploadMessage('error', error?.message || '刪除失敗'));
  }

  function handleUpload(file) {
    const name = file.name || '檔案';
    if (!/\.csv$/i.test(name)) {
      setUploadMessage('error', '僅支援上傳 CSV 檔案');
      uploadInput.value = '';
      return;
    }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('roc_year', String(state.year));
    formData.append('month', String(state.month));

    setUploadState(true, '上傳中…');
    setUploadMessage('info', `正在上傳 ${name}`);

    fetch('../api/payroll/cpc_upload.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            const count = Number(data?.data?.count) || 0;
            setUploadMessage('success', count ? `已載入 ${count} 筆資料` : '已完成上傳');
            fetchRecords();
          })
      )
      .catch((error) => setUploadMessage('error', error?.message || '上傳失敗，請稍後再試'))
      .finally(() => {
        setUploadState(false);
        uploadInput.value = '';
      });
  }

  function collectInputs(row) {
    const payload = {};
    row.querySelectorAll('[data-cpc-input]').forEach((input) => {
      const key = input.dataset.cpcInput;
      if (key) payload[key] = input.value.trim();
    });
    payload.amount = parseAmount(payload.amount);
    return payload;
  }

  function setUploadState(isUploading, label) {
    if (!uploadBtn) return;
    uploadBtn.disabled = isUploading;
    uploadBtn.textContent = isUploading && label ? label : '上傳';
  }

  function setUploadMessage(status, text) {
    if (!uploadMessage) return;
    if (!text) {
      uploadMessage.textContent = '\u00a0';
      uploadMessage.removeAttribute('data-status');
      return;
    }
    uploadMessage.textContent = text;
    uploadMessage.dataset.status = status;
  }

  function formatNumber(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) return '0';
    return num.toLocaleString('zh-TW');
  }

  function parseAmount(value) {
    const num = Number(String(value || '0').replace(/,/g, ''));
    return Number.isFinite(num) ? num : 0;
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function escapeAttr(value) {
    return escapeHtml(value ?? '');
  }
})();
