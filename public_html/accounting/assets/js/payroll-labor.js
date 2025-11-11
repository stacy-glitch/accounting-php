(function () {
  'use strict';

  const periodEl = document.querySelector('[data-labor-period]');
  const prevBtn = document.querySelector('[data-labor-nav="prev"]');
  const nextBtn = document.querySelector('[data-labor-nav="next"]');
  const tableBody = document.querySelector('[data-labor-table-body]');
  const uploadBtn = document.querySelector('[data-labor-upload]');
  const uploadInput = document.querySelector('[data-labor-upload-input]');
  const uploadLabelEl = uploadBtn ? uploadBtn.querySelector('[data-labor-upload-label]') : null;
  const uploadMessageEl = document.querySelector('[data-labor-upload-message]');
  const uploadDefaultText = uploadLabelEl ? uploadLabelEl.textContent.trim() : '上傳';

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
    renderTablePlaceholder('載入中…');
    const params = new URLSearchParams({
      roc_year: state.year,
      month: state.month,
    });
    fetch(`../api/payroll/labor_records.php?${params.toString()}`, {
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
        renderTablePlaceholder(error?.message || '載入失敗');
      })
      .finally(() => {
        state.loading = false;
      });
  }

  function renderPeriod() {
    periodEl.textContent = `${state.year}年${String(state.month).padStart(2, '0')}月勞保名冊`;
  }

  function renderRows() {
    tableBody.innerHTML = '';
    if (!state.records.length) {
      renderTablePlaceholder(`目前沒有 ${state.year} 年 ${state.month} 月的勞保名冊資料`);
      return;
    }
    const totals = state.records.reduce(
      (acc, record) => {
        acc.personal += Number(record.personal_share) || 0;
        acc.company += Number(record.company_share) || 0;
        return acc;
      },
      { personal: 0, company: 0 }
    );
    state.records.forEach((record) => {
      const row = document.createElement('tr');
      row.dataset.laborRow = String(record.id);
      const isEditing = editingState.id === record.id;
      row.classList.toggle('labor-row--editing', isEditing);
      row.innerHTML = isEditing ? buildEditingRow(record) : buildDisplayRow(record);
      tableBody.appendChild(row);
    });
    const totalsRow = document.createElement('tr');
    totalsRow.className = 'labor-total-row';
    const combined = totals.personal + totals.company;
    totalsRow.innerHTML = `
      <td colspan="6" class="labor-total-label">合計</td>
      <td class="labor-amount">$ ${formatNumber(totals.personal)}</td>
      <td class="labor-amount">$ ${formatNumber(totals.company)}</td>
      <td class="labor-note-cell labor-total-note">$ ${formatNumber(combined)}</td>
      <td></td>
    `;
    tableBody.appendChild(totalsRow);
  }

  function renderTablePlaceholder(text) {
    tableBody.innerHTML = `
      <tr>
        <td colspan="10" class="table-empty">${escapeHtml(text)}</td>
      </tr>
    `;
  }

  function buildDisplayRow(record) {
    return `
      <td>${escapeHtml(record.employee_name || '')}</td>
      <td>${escapeHtml(record.birth || '')}</td>
      <td class="labor-amount">$ ${formatNumber(record.labor_salary)}</td>
      <td class="labor-amount">$ ${formatNumber(record.health_salary)}</td>
      <td>${escapeHtml(record.change_type || '')}</td>
      <td>${escapeHtml(record.change_date || '')}</td>
      <td class="labor-amount">$ ${formatNumber(record.personal_share)}</td>
      <td class="labor-amount">$ ${formatNumber(record.company_share)}</td>
      <td class="labor-note-cell">${escapeHtml(record.note || '') || '&nbsp;'}</td>
      <td class="table__ops">
        <button type="button" class="btn btn--ghost btn--small" data-labor-action="edit" data-labor-id="${record.id}">編輯</button>
        <button type="button" class="btn btn--danger-soft btn--small" data-labor-action="delete" data-labor-id="${record.id}">刪除</button>
      </td>
    `;
  }

  function buildEditingRow(record) {
    return `
      <td><input type="text" class="labor-input" data-labor-input="employee_name" value="${escapeAttr(record.employee_name || '')}" placeholder="姓名"></td>
      <td><input type="text" class="labor-input" data-labor-input="birth" value="${escapeAttr(record.birth || '')}" placeholder="出生日期"></td>
      <td><input type="number" class="labor-input labor-input--amount" data-labor-input="labor_salary" value="${escapeAttr(record.labor_salary)}"></td>
      <td><input type="number" class="labor-input labor-input--amount" data-labor-input="health_salary" value="${escapeAttr(record.health_salary)}"></td>
      <td><input type="text" class="labor-input" data-labor-input="change_type" value="${escapeAttr(record.change_type || '')}" placeholder="異動別"></td>
      <td><input type="text" class="labor-input" data-labor-input="change_date" value="${escapeAttr(record.change_date || '')}" placeholder="異動日期"></td>
      <td><input type="number" class="labor-input labor-input--amount" data-labor-input="personal_share" value="${escapeAttr(record.personal_share)}"></td>
      <td><input type="number" class="labor-input labor-input--amount" data-labor-input="company_share" value="${escapeAttr(record.company_share)}"></td>
      <td><input type="text" class="labor-input" data-labor-input="note" value="${escapeAttr(record.note || '')}" placeholder="備註"></td>
      <td>
        <div class="labor-edit-actions">
          <button type="button" class="btn btn--small" data-labor-save>儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-labor-cancel>取消</button>
        </div>
      </td>
    `;
  }

  function handleTableClick(event) {
    const saveBtn = event.target.closest('[data-labor-save]');
    if (saveBtn) {
      const row = saveBtn.closest('[data-labor-row]');
      handleSave(row);
      return;
    }
    const cancelBtn = event.target.closest('[data-labor-cancel]');
    if (cancelBtn) {
      editingState.id = null;
      renderRows();
      return;
    }
    const actionBtn = event.target.closest('[data-labor-action]');
    if (!actionBtn) return;
    const recordId = Number(actionBtn.dataset.laborId);
    if (!recordId) return;
    if (actionBtn.dataset.laborAction === 'edit') {
      editingState.id = recordId;
      renderRows();
    } else if (actionBtn.dataset.laborAction === 'delete') {
      deleteRecord(recordId);
    }
  }

  function handleSave(row) {
    if (!row) return;
    const recordId = Number(row.dataset.laborRow);
    if (!recordId) return;
    const payload = collectInputValues(row);
    if (!payload.employee_name) {
      window.alert('請輸入姓名');
      return;
    }
    setUploadMessage('info', '儲存中…');
    fetch('../api/payroll/labor_records.php', {
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
    fetch(`../api/payroll/labor_records.php?id=${recordId}`, {
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
    if (!/\.pdf$/i.test(name)) {
      setUploadMessage('error', '僅支援上傳 PDF 檔案');
      uploadInput.value = '';
      return;
    }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('roc_year', String(state.year));
    formData.append('month', String(state.month));

    setUploadState(true, '上傳中…');
    setUploadMessage('info', `正在上傳 ${name}`);

    fetch('../api/payroll/labor_upload.php', {
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

  function collectInputValues(row) {
    const values = {};
    row.querySelectorAll('[data-labor-input]').forEach((input) => {
      const key = input.dataset.laborInput;
      if (key) {
        values[key] = input.value.trim();
      }
    });
    values.labor_salary = parseAmount(values.labor_salary);
    values.health_salary = parseAmount(values.health_salary);
    values.personal_share = parseAmount(values.personal_share);
    values.company_share = parseAmount(values.company_share);
    return values;
  }

  function setUploadState(isUploading, label) {
    if (!uploadBtn) {
      return;
    }
    uploadBtn.disabled = isUploading;
    if (uploadLabelEl) {
      uploadLabelEl.textContent = isUploading && label ? label : uploadDefaultText;
    } else {
      uploadBtn.textContent = isUploading && label ? label : uploadDefaultText;
    }
  }

  function setUploadMessage(status, text) {
    if (!uploadMessageEl) {
      return;
    }
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
