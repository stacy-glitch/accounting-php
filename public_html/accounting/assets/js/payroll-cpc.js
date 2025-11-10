(function () {
  'use strict';

  const periodEl = document.querySelector('[data-cpc-period]');
  const prevBtn = document.querySelector('[data-cpc-nav="prev"]');
  const nextBtn = document.querySelector('[data-cpc-nav="next"]');
  const summaryPeriodEl = document.querySelector('[data-cpc-summary-period]');
  const summaryPrevBtn = document.querySelector('[data-cpc-summary-nav="prev"]');
  const summaryNextBtn = document.querySelector('[data-cpc-summary-nav="next"]');
  const summaryEl = document.querySelector('[data-cpc-summary]');
  const uploadBtn = document.querySelector('[data-cpc-upload]');
  const uploadInput = document.querySelector('[data-cpc-upload-input]');
  const uploadMessage = document.querySelector('[data-cpc-upload-message]');
  const tableBody = document.querySelector('[data-cpc-table-body]');
  const summaryTableBody = document.querySelector('[data-cpc-summary-body]');
  const summaryMessage = document.querySelector('[data-cpc-summary-message]');
  const summaryCountEl = document.querySelector('[data-cpc-summary-count]');
  const printBtn = document.querySelector('[data-cpc-summary-print]');

  if (!periodEl || !tableBody) return;

  const dataset = document.body.dataset || {};
  const state = {
    year: Number(dataset.initialRocYear) || new Date().getFullYear() - 1911,
    month: Number(dataset.initialMonth) || new Date().getMonth(),
    records: [],
    loading: false,
  };
  const summaryState = {
    rows: [],
    loading: false,
    editingCode: null,
  };
  const PRINT_EXCLUDE_CODES = new Set(['028-2B', '830-W6', 'BKK-0233', 'KLA-5096', 'KLA-5632', 'KLK-0270']);
  if (state.month <= 0) {
    state.month += 12;
    state.year -= 1;
  }

  init();

  function init() {
    renderPeriod();
    fetchRecords();
    fetchSummary();
    prevBtn?.addEventListener('click', () => adjustMonth(-1));
    nextBtn?.addEventListener('click', () => adjustMonth(1));
    summaryPrevBtn?.addEventListener('click', () => adjustMonth(-1));
    summaryNextBtn?.addEventListener('click', () => adjustMonth(1));
    tableBody.addEventListener('click', handleTableClick);
    summaryTableBody?.addEventListener('click', handleSummaryTableClick);
    printBtn?.addEventListener('click', handleSummaryPrint);
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
    fetchSummary();
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
    if (summaryPeriodEl) {
      summaryPeriodEl.textContent = `${state.year}年${String(state.month).padStart(2, '0')}月中油統計表`;
    }
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
    appendRecordsTotalRow();
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
      <td><input type="text" class="cpc-input" data-cpc-input="trade_date" value="${escapeAttr(record.trade_date || '')}" placeholder="YYYY-MM-DD 或 YYYY/MM/DD HH:mm:ss"></td>
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
            fetchSummary();
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
            fetchSummary();
          })
      )
      .catch((error) => setUploadMessage('error', error?.message || '刪除失敗'));
  }

  function handleUpload(file) {
    const name = file.name || '檔案';
    if (!/\.(csv|xlsx)$/i.test(name)) {
      setUploadMessage('error', '僅支援上傳 CSV 或 XLSX 檔案');
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
            fetchSummary();
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

  function appendRecordsTotalRow() {
    const total = state.records.reduce((sum, record) => sum + (Number(record.amount) || 0), 0);
    const row = document.createElement('tr');
    row.className = 'cpc-total-row';
    row.innerHTML = `
      <td colspan="4" class="cpc-total-label">總計</td>
      <td class="cpc-amount">$ ${formatNumber(total)}</td>
      <td colspan="2"></td>
    `;
    tableBody.appendChild(row);
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

  function renderSummaryPlaceholder(text) {
    if (!summaryTableBody) return;
    summaryTableBody.innerHTML = `<tr><td colspan="6" class="table-empty">${escapeHtml(text)}</td></tr>`;
    if (summaryCountEl) summaryCountEl.textContent = '\u00a0';
  }

  function fetchSummary() {
    if (!summaryTableBody) return;
    summaryState.loading = true;
    renderSummaryPlaceholder('載入中…');
    const params = new URLSearchParams({ roc_year: state.year, month: state.month });
    fetch(`../api/payroll/cpc_summary.php?${params.toString()}`, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            summaryState.rows = Array.isArray(data.records) ? data.records : [];
            summaryState.editingCode = null;
            renderSummaryRows();
          })
      )
      .catch((error) => renderSummaryPlaceholder(error?.message || '載入失敗'))
      .finally(() => {
        summaryState.loading = false;
      });
  }

  function renderSummaryRows() {
    if (!summaryTableBody) return;
    if (!summaryState.rows.length) {
      renderSummaryPlaceholder('目前沒有統計資料');
      return;
    }
    const rows = [...summaryState.rows].sort((a, b) => String(a.code || '').localeCompare(String(b.code || '')));
    if (summaryCountEl) summaryCountEl.textContent = '\u00a0';
    summaryTableBody.innerHTML = '';
    let oilTotal = 0;
    let paperTotal = 0;
    rows.forEach((row) => {
      oilTotal += Number(row.oil_amount) || 0;
      paperTotal += Number(row.paper_amount) || 0;
      const tr = document.createElement('tr');
      tr.dataset.summaryRow = 'true';
      tr.dataset.summaryCode = row.code || '';
      const isEditing = summaryState.editingCode === row.code;
      tr.innerHTML = isEditing ? buildSummaryEditingRow(row) : buildSummaryDisplayRow(row);
      summaryTableBody.appendChild(tr);
    });
    const totalRow = document.createElement('tr');
    totalRow.className = 'cpc-summary-total-row';
    totalRow.innerHTML = `
      <td colspan="2" class="cpc-total-label">總計</td>
      <td class="cpc-summary-amount">$ ${formatNumber(oilTotal)}</td>
      <td class="cpc-summary-amount">$ ${formatNumber(paperTotal)}</td>
      <td colspan="2"></td>
    `;
    summaryTableBody.appendChild(totalRow);
  }

  function buildSummaryDisplayRow(row) {
    return `
      <td>${escapeHtml(row.code || '')}</td>
      <td>${escapeHtml(row.driver || '')}</td>
      <td class="cpc-summary-amount">$ ${formatNumber(row.oil_amount)}</td>
      <td class="cpc-summary-amount">$ ${formatNumber(row.paper_amount)}</td>
      <td class="cpc-summary-note">${escapeHtml(row.note || '') || '&nbsp;'}</td>
      <td class="table__ops">
        <button type="button" class="btn btn--ghost btn--small" data-summary-action="edit" data-summary-code="${escapeAttr(row.code || '')}">編輯</button>
        <button type="button" class="btn btn--danger-soft btn--small" data-summary-action="delete" data-summary-code="${escapeAttr(row.code || '')}">刪除</button>
      </td>
    `;
  }

  function buildSummaryEditingRow(row) {
    return `
      <td>${escapeHtml(row.code || '')}</td>
      <td>
        <input type="text" class="cpc-summary-edit-input" data-summary-input="driver" value="${escapeAttr(row.driver || '')}" placeholder="司機">
      </td>
      <td class="cpc-summary-amount">$ ${formatNumber(row.oil_amount)}</td>
      <td>
        <input type="number" class="cpc-summary-edit-input" data-summary-input="paper_amount" value="${escapeAttr(row.paper_amount)}" placeholder="0">
      </td>
      <td>
        <input type="text" class="cpc-summary-edit-input cpc-summary-note-input" data-summary-input="note" value="${escapeAttr(row.note || '')}" placeholder="備註">
      </td>
      <td>
        <div class="labor-edit-actions">
          <button type="button" class="btn btn--small" data-summary-save>儲存</button>
          <button type="button" class="btn btn--secondary btn--small" data-summary-cancel>取消</button>
        </div>
      </td>
    `;
  }

  function handleSummaryTableClick(event) {
    const row = event.target.closest('[data-summary-row]');
    const code = row?.dataset.summaryCode || event.target.closest('[data-summary-action]')?.dataset.summaryCode || '';
    if (event.target.closest('[data-summary-save]')) {
      saveSummaryRow(row);
      return;
    }
    if (event.target.closest('[data-summary-cancel]')) {
      summaryState.editingCode = null;
      renderSummaryRows();
      return;
    }
    const actionBtn = event.target.closest('[data-summary-action]');
    if (!actionBtn || !code) return;
    if (actionBtn.dataset.summaryAction === 'edit') {
      summaryState.editingCode = code;
      renderSummaryRows();
    } else if (actionBtn.dataset.summaryAction === 'delete') {
      deleteSummaryRow(code);
    }
  }

  function saveSummaryRow(row) {
    if (!row) return;
    const code = row.dataset.summaryCode || '';
    if (!code) return;
    const payload = collectSummaryInputs(row);
    setSummaryMessage('info', '儲存中…');
    fetch('../api/payroll/cpc_summary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        code,
        paper_amount: payload.paper_amount,
        note: payload.note,
        driver: payload.driver,
        roc_year: state.year,
        month: state.month,
      }),
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            summaryState.rows = Array.isArray(data.records) ? data.records : [];
            summaryState.editingCode = null;
            renderSummaryRows();
            setSummaryMessage('success', '已儲存統計資料');
          })
      )
      .catch((error) => setSummaryMessage('error', error?.message || '儲存失敗'));
  }

  function deleteSummaryRow(code) {
    if (!code) return;
    if (!window.confirm('確定要清除這筆統計資料嗎？')) return;
    setSummaryMessage('info', '刪除中…');
    const params = new URLSearchParams({ roc_year: state.year, month: state.month, code });
    fetch(`../api/payroll/cpc_summary.php?${params.toString()}`, {
      method: 'DELETE',
      credentials: 'same-origin',
    })
      .then((response) =>
        response
          .json()
          .catch(() => ({ ok: false, error: `HTTP ${response.status}` }))
          .then((data) => {
            if (!response.ok || !data?.ok) throw new Error(data?.error || `HTTP ${response.status}`);
            summaryState.rows = Array.isArray(data.records) ? data.records : [];
            summaryState.editingCode = null;
            renderSummaryRows();
            setSummaryMessage('success', '已清除紙本金額');
          })
      )
      .catch((error) => setSummaryMessage('error', error?.message || '刪除失敗'));
  }

  function collectSummaryInputs(row) {
    const payload = { paper_amount: 0, note: '', driver: '' };
    row.querySelectorAll('[data-summary-input]').forEach((input) => {
      const key = input.dataset.summaryInput;
      if (!key) return;
      payload[key] = input.value.trim();
    });
    payload.paper_amount = parseAmount(payload.paper_amount);
    const code = row.dataset.summaryCode || '';
    if (!payload.driver) {
      const matched = summaryState.rows.find((item) => item.code === code);
      payload.driver = matched?.driver || '';
    }
    return payload;
  }

  function setSummaryMessage(status, text) {
    if (!summaryMessage) return;
    if (!text) {
      summaryMessage.textContent = '\u00a0';
      summaryMessage.removeAttribute('data-status');
      return;
    }
    summaryMessage.textContent = text;
    summaryMessage.dataset.status = status;
  }

  function handleSummaryPrint() {
    if (!state.records.length) {
      window.alert('目前沒有可列印的明細');
      return;
    }
    const groups = groupRecordsByCode();
    if (!groups.length) {
      window.alert('目前沒有可列印的明細');
      return;
    }
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
      window.alert('瀏覽器已阻擋彈出視窗，請允許後再試一次');
      return;
    }
    const title = `${state.year}年${String(state.month).padStart(2, '0')}月中油統計表`;
    const styles = `
      @media print {
        @page {
          size: A4 portrait;
          margin: 15mm;
        }
      }
      body { font-family: 'Noto Sans TC', 'PingFang TC', 'Microsoft JhengHei', sans-serif; padding: 24px; color: #0f172a; }
      h1 { text-align: center; margin: 0 0 20px; font-size: 20px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
      th, td { border: 1px solid #d1d5db; padding: 6px 8px; font-size: 13px; }
      th { background: #f3f4f6; }
      tfoot td { font-weight: 600; }
      .group-title { margin: 0 0 6px; font-size: 15px; font-weight: 600; }
      .cpc-print-group { page-break-inside: avoid; margin-bottom: 24px; }
      @media print {
        body { padding: 0; }
        table { page-break-inside: avoid; }
      }
    `;
    let html = `<html><head><title>${title}</title><style>${styles}</style></head><body>`;
    groups.forEach((group, index) => {
      html += `<div class="cpc-print-group${index > 0 ? ' cpc-print-break' : ''}"><p class="group-title">${escapeHtml(group.code)}${group.driver ? ' - ' + escapeHtml(group.driver) : ''}</p>`;
      html += '<table><thead><tr><th>交易日期時間</th><th>油站</th><th>車號</th><th>參考金額</th></tr></thead><tbody>';
      group.rows.forEach((row) => {
        html += `<tr><td>${escapeHtml(row.trade_date || '')}</td><td>${escapeHtml(row.station || '')}</td><td>${escapeHtml(row.license_plate || '')}</td><td style="text-align:right;">$ ${formatNumber(row.amount)}</td></tr>`;
      });
      html += `</tbody><tfoot><tr><td colspan="3">總額</td><td style="text-align:right;">$ ${formatNumber(group.total)}</td></tr></tfoot></table></div>`;
    });
    html += '</body></html>';
    printWindow.document.write(html);
    printWindow.document.close();
    const triggerPrint = () => {
      try {
        printWindow.focus();
        printWindow.print();
      } catch (error) {
        console.error(error);
      }
    };
    if (printWindow.document.readyState === 'complete') {
      setTimeout(triggerPrint, 50);
    } else {
      printWindow.onload = triggerPrint;
    }
  }

  function groupRecordsByCode() {
    const groups = {};
    state.records.forEach((record) => {
      let code = record.license_plate || record.driver || `row-${record.id}`;
      code = String(code || '').trim();
      if (!code) return;
      if (PRINT_EXCLUDE_CODES.has(code.toUpperCase())) {
        return;
      }
      if (!groups[code]) {
        groups[code] = {
          code,
          driver: record.driver || '',
          rows: [],
          total: 0,
        };
      }
      groups[code].rows.push(record);
      groups[code].total += Number(record.amount) || 0;
      if (!groups[code].driver && record.driver) {
        groups[code].driver = record.driver;
      }
    });
    return Object.values(groups).map((group) => {
      group.rows.sort((a, b) => String(a.trade_date || '').localeCompare(String(b.trade_date || '')));
      return group;
    }).sort((a, b) => String(a.code || '').localeCompare(String(b.code || '')));
  }
})();
