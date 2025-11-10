(function () {
  'use strict';

  const periodEl = document.querySelector('[data-health-period]');
  const prevBtn = document.querySelector('[data-health-nav="prev"]');
  const nextBtn = document.querySelector('[data-health-nav="next"]');
  const tableBody = document.querySelector('[data-health-table-body]');
  const summaryEl = document.querySelector('[data-health-summary]');
  const uploadBtn = document.querySelector('[data-health-upload]');
  const uploadInput = document.querySelector('[data-health-upload-input]');
  const uploadMessage = document.querySelector('[data-health-upload-message]');

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

  const editingState = { id: null };

  function fetchRecords() {
    state.loading = true;
    renderPlaceholder('載入中…');
    const params = new URLSearchParams({
      roc_year: state.year,
      month: state.month,
    });
    fetch(`../api/payroll/health_records.php?${params.toString()}`, {
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

  function renderPeriod() {
    periodEl.textContent = `${state.year}年${String(state.month).padStart(2, '0')}月健保名冊`;
  }

  function renderPlaceholder(text) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="10" class="table-empty">${escapeHtml(text)}</td>
      </tr>
    `;
    if (summaryEl) {
      summaryEl.textContent = '已載入 0 筆資料';
    }
  }

  function renderRows() {
    tableBody.innerHTML = '';
    if (summaryEl) {
      summaryEl.textContent = `已載入 ${state.records.length} 筆資料`;
    }
    if (!state.records.length) {
      renderPlaceholder(`目前沒有 ${state.year} 年 ${state.month} 月的健保名冊資料`);
      return;
    }
    state.records.forEach((record) => {
      const row = document.createElement('tr');
      row.dataset.healthRow = String(record.id);
      const isEditing = editingState.id === record.id;
      row.classList.toggle('labor-row--editing', isEditing);
      row.innerHTML = isEditing ? buildEditingRow(record) : buildDisplayRow(record);
      tableBody.appendChild(row);
    });
  }

  function buildDisplayRow(record) {
    return `
      <td class="health-cell health-cell--amount">$ ${formatNumber(record.insurance_fee)}</td>
      <td>${escapeHtml(record.dependent_name || '')}</td>
      <td>${escapeHtml(record.id_number || '')}</td>
      <td>${escapeHtml(record.birth || '')}</td>
      <td>${escapeHtml(record.billing_note || '')}</td>
      <td class="health-cell health-cell--amount">$ ${formatNumber(record.self_payment)}</td>
      <td class="health-cell health-cell--amount">$ ${formatNumber(record.company_payment)}</td>
      <td class="health-cell health-cell--amount">$ ${formatNumber(record.self_total)}</td>
      <td class="health-note-cell">${escapeHtml(record.note || '') || '&nbsp;'}</td>
      <td class="table__ops">
        <button type="button" class="btn btn--ghost btn--small" data-health-action="edit" data-health-id="${record.id}">編輯</button>
        <button type="button" class="btn btn--danger-soft btn--small" data-health-action="delete" data-health-id="${record.id}">刪除</button>
      </td>
    `;
  }

  function buildEditingRow(record) {
    return `
      <td><input type="number" class="health-input health-input--amount" data-health-input="insurance_fee" value="${escapeAttr(record.insurance_fee)}"></td>
      <td><input type="text" class="health-input" data-health-input="dependent_name" value="${escapeAttr(record.dependent_name || '')}"></td>
      <td><input type="text" class="health-input" data-health-input="id_number" value="${escapeAttr(record.id_number || '')}"></td>
      <td><input type="text" class="health-input" data-health-input="birth" value="${escapeAttr(record.birth || '')}"></td>
      <td><input type="text" class="health-input" data-health-input="billing_note" value="${escapeAttr(record.billing_note || '')}"></td>
      <td><input type="number" class="health-input health-input--amount" data-health-input="self_payment" value="${escapeAttr(record.self_payment)}"></td>
      <td><input type="number" class="health-input health-input--amount" data-health-input="company_payment" value="${escapeAttr(record.company_payment)}"></td>
      <td><input type="number" class="health-input health-input--amount" data-health-input="self_total" value="${escapeAttr(record.self_total)}"></td>
      <td><input type="text" class="health-input" data-health-input="note" value="${escapeAttr(record.note || '')}"></td>
      <td>
        <div class="labor-edit-actions">
          <button type="button" class="btn btn--small" data-health-save>儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-health-cancel>取消</button>
        </div>
      </td>
    `;
  }

  function handleTableClick(event) {
    const saveBtn = event.target.closest('[data-health-save]');
    if (saveBtn) {
      const row = saveBtn.closest('[data-health-row]');
      handleSave(row);
      return;
    }
    const cancelBtn = event.target.closest('[data-health-cancel]');
    if (cancelBtn) {
      editingState.id = null;
      renderRows();
      return;
    }
    const actionBtn = event.target.closest('[data-health-action]');
    if (!actionBtn) return;
    const recordId = Number(actionBtn.dataset.healthId);
    if (!recordId) return;
    if (actionBtn.dataset.healthAction === 'edit') {
      editingState.id = recordId;
      renderRows();
    } else if (actionBtn.dataset.healthAction === 'delete') {
      deleteRecord(recordId);
    }
  }

  function handleSave(row) {
    if (!row) return;
    const recordId = Number(row.dataset.healthRow);
    if (!recordId) return;
    const payload = collectInputs(row);
    if (!payload.dependent_name) {
      window.alert('請輸入眷屬姓名');
      return;
    }
    setUploadMessage('info', '儲存中…');
    fetch('../api/payroll/health_records.php', {
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
    fetch(`../api/payroll/health_records.php?id=${recordId}`, {
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
    if (!/\.(csv|xlsx|pdf)$/i.test(name)) {
      setUploadMessage('error', '僅支援上傳 CSV、XLSX 或 PDF 檔案');
      uploadInput.value = '';
      return;
    }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('roc_year', String(state.year));
    formData.append('month', String(state.month));

    setUploadState(true, '上傳中…');
    setUploadMessage('info', `正在上傳 ${name}`);

    fetch('../api/payroll/health_upload.php', {
      method: 'POST',
      body: formData,
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
    row.querySelectorAll('[data-health-input]').forEach((input) => {
      const key = input.dataset.healthInput;
      if (key) {
        payload[key] = input.value.trim();
      }
    });
    payload.insurance_fee = parseAmount(payload.insurance_fee);
    payload.self_payment = parseAmount(payload.self_payment);
    payload.company_payment = parseAmount(payload.company_payment);
    payload.self_total = parseAmount(payload.self_total);
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
