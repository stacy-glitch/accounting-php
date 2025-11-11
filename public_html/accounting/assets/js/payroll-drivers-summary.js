/* global fetch */
(function () {
  'use strict';

  const periodEl = document.querySelector('[data-drivers-period]');
  const prevBtn = document.querySelector('[data-drivers-nav="prev"]');
  const nextBtn = document.querySelector('[data-drivers-nav="next"]');
  const tableBody = document.querySelector('[data-drivers-table-body]');
  const uploadBtn = document.querySelector('[data-drivers-upload]');
  const uploadInput = document.querySelector('[data-drivers-upload-input]');
  const uploadMessageEl = document.querySelector('[data-drivers-upload-message]');

  if (!periodEl || !tableBody) {
    return;
  }

  const dataset = document.body.dataset || {};
  const state = {
    year: Number(dataset.initialRocYear) || new Date().getFullYear() - 1911,
    month: Number(dataset.initialMonth) || new Date().getMonth() + 1,
    records: [],
    loading: false,
  };

  const editingState = { id: null };

  init();

  function init() {
    renderPeriod();
    fetchRecords();
    prevBtn?.addEventListener('click', () => adjustMonth(-1));
    nextBtn?.addEventListener('click', () => adjustMonth(1));
    tableBody.addEventListener('click', handleTableClick);
    if (uploadBtn && uploadInput) {
      uploadBtn.addEventListener('click', () => {
        if (!uploadBtn.disabled) {
          uploadInput.click();
        }
      });
      uploadInput.addEventListener('change', () => {
        const file = uploadInput.files && uploadInput.files[0];
        if (file) {
          handleUpload(file);
        }
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

  function renderPeriod() {
    periodEl.textContent = `${state.year}年${String(state.month).padStart(2, '0')}月司機金額總匯表`;
  }

  function fetchRecords() {
    state.loading = true;
    renderPlaceholder('載入中…');
    const params = new URLSearchParams({
      roc_year: state.year,
      month: state.month,
    });
    fetch(`../api/payroll/drivers_summary_records.php?${params.toString()}`, {
      credentials: 'same-origin',
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) {
              throw new Error(data?.error || `HTTP ${response.status}`);
            }
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

  function renderPlaceholder(text) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="5" class="table-empty">${escapeHtml(text)}</td>
      </tr>
    `;
  }

  function renderRows() {
    tableBody.innerHTML = '';
    if (!state.records.length) {
      renderPlaceholder(`目前沒有 ${state.year} 年 ${state.month} 月的司機金額資料`);
      return;
    }
    state.records.forEach((record) => {
      const row = document.createElement('tr');
      row.dataset.driversRow = String(record.id);
      const isEditing = editingState.id === record.id;
      row.classList.toggle('drivers-row--editing', isEditing);
      row.innerHTML = isEditing ? buildEditingRow(record) : buildDisplayRow(record);
      tableBody.appendChild(row);
    });
  }

  function buildDisplayRow(record) {
    return `
      <td class="drivers-code-cell">${escapeHtml(record.driver_code || '') || '&nbsp;'}</td>
      <td class="drivers-name-cell">
        <div class="drivers-name">${escapeHtml(record.driver_name || '')}</div>
      </td>
      <td class="drivers-cell-amount">$ ${formatNumber(record.freight)}</td>
      <td class="drivers-note-cell">${escapeHtml(record.note || '') || '&nbsp;'}</td>
      <td class="table__ops">
        <button type="button" class="btn btn--ghost btn--small" data-drivers-action="edit" data-drivers-id="${record.id}">編輯</button>
        <button type="button" class="btn btn--danger-soft btn--small" data-drivers-action="delete" data-drivers-id="${record.id}">刪除</button>
      </td>
    `;
  }

  function buildEditingRow(record) {
    return `
      <td><input type="text" class="drivers-input" data-drivers-input="driver_code" value="${escapeAttr(record.driver_code || '')}" placeholder="代號"></td>
      <td><input type="text" class="drivers-input" data-drivers-input="driver_name" value="${escapeAttr(record.driver_name || '')}" placeholder="司機"></td>
      <td><input type="number" class="drivers-input drivers-input--amount" data-drivers-input="freight" value="${escapeAttr(record.freight)}" placeholder="0"></td>
      <td><input type="text" class="drivers-input" data-drivers-input="note" value="${escapeAttr(record.note || '')}" placeholder="備註"></td>
      <td>
        <div class="drivers-edit-actions">
          <button type="button" class="btn btn--small" data-drivers-save>儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-drivers-cancel>取消</button>
        </div>
      </td>
    `;
  }

  function handleTableClick(event) {
    const saveBtn = event.target.closest('[data-drivers-save]');
    if (saveBtn) {
      const row = saveBtn.closest('[data-drivers-row]');
      handleSave(row);
      return;
    }
    const cancelBtn = event.target.closest('[data-drivers-cancel]');
    if (cancelBtn) {
      editingState.id = null;
      renderRows();
      return;
    }
    const actionBtn = event.target.closest('[data-drivers-action]');
    if (!actionBtn) return;
    const recordId = Number(actionBtn.dataset.driversId);
    if (!recordId) return;
    if (actionBtn.dataset.driversAction === 'edit') {
      editingState.id = recordId;
      renderRows();
    } else if (actionBtn.dataset.driversAction === 'delete') {
      deleteRecord(recordId);
    }
  }

  function handleSave(row) {
    if (!row) return;
    const recordId = Number(row.dataset.driversRow);
    if (!recordId) return;
    const payload = collectInputs(row);
    if (!payload.driver_name) {
      window.alert('請輸入司機名稱');
      return;
    }
    setUploadMessage('info', '儲存中…');
    fetch('../api/payroll/drivers_summary_records.php', {
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
            if (!response.ok || !data?.ok) {
              throw new Error(data?.error || `HTTP ${response.status}`);
            }
            setUploadMessage('success', '已儲存變更');
            editingState.id = null;
            fetchRecords();
          })
      )
      .catch((error) => {
        setUploadMessage('error', error?.message || '儲存失敗');
      });
  }

  function deleteRecord(recordId) {
    if (!window.confirm('確定要刪除這筆資料嗎？')) {
      return;
    }
    setUploadMessage('info', '刪除中…');
    fetch(`../api/payroll/drivers_summary_records.php?id=${recordId}`, {
      method: 'DELETE',
      credentials: 'same-origin',
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) {
              throw new Error(data?.error || `HTTP ${response.status}`);
            }
            setUploadMessage('success', '已刪除資料');
            editingState.id = null;
            fetchRecords();
          })
      )
      .catch((error) => {
        setUploadMessage('error', error?.message || '刪除失敗');
      });
  }

  function handleUpload(file) {
    const name = file.name || '檔案';
    if (!/\.xlsx$/i.test(name)) {
      setUploadMessage('error', '僅支援上傳 XLSX 檔案');
      uploadInput.value = '';
      return;
    }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('roc_year', String(state.year));
    formData.append('month', String(state.month));

    setUploadState(true, '上傳中…');
    setUploadMessage('info', `正在上傳 ${name}`);

    fetch('../api/payroll/drivers_summary_upload.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) {
              throw new Error(data?.error || `HTTP ${response.status}`);
            }
            const count = Number(data?.data?.count) || 0;
            setUploadMessage('success', count ? `已載入 ${count} 筆資料` : '已完成上傳');
            fetchRecords();
          })
      )
      .catch((error) => {
        setUploadMessage('error', error?.message || '上傳失敗，請稍後再試');
      })
      .finally(() => {
        setUploadState(false);
        uploadInput.value = '';
      });
  }

  function collectInputs(row) {
    const payload = {};
    row.querySelectorAll('[data-drivers-input]').forEach((input) => {
      const key = input.dataset.driversInput;
      if (key) {
        payload[key] = input.value.trim();
      }
    });
    payload.freight = parseAmount(payload.freight);
    return payload;
  }

  function setUploadState(isUploading, label) {
    if (!uploadBtn) return;
    uploadBtn.disabled = isUploading;
    if (isUploading && label) {
      uploadBtn.textContent = label;
    } else {
      uploadBtn.innerHTML = '<span class="labor-action-icon" aria-hidden="true">📁</span>上傳.xlsx';
    }
  }

  function setUploadMessage(status, text) {
    if (!uploadMessageEl) return;
    if (!text) {
      uploadMessageEl.textContent = '\u00a0';
      uploadMessageEl.removeAttribute('data-status');
      return;
    }
    uploadMessageEl.textContent = text;
    uploadMessageEl.dataset.status = status;
  }

  function formatNumber(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) {
      return '0';
    }
    return num.toLocaleString('zh-TW');
  }

  function parseAmount(value) {
    const num = Number(String(value || '0').replace(/,/g, ''));
    return Number.isFinite(num) ? num : 0;
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
    return escapeHtml(value ?? '');
  }
})();
