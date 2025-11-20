(function () {
  'use strict';

  const monthTitleEl = document.querySelector('[data-expenses-month-title]');
  const navButtons = document.querySelectorAll('[data-expenses-nav]');
  const tableRows = document.querySelector('[data-expenses-rows]');
  const messageEl = document.querySelector('[data-expenses-message]');

  if (!monthTitleEl || !tableRows) {
    return;
  }

  const state = {
    year: parseInt(document.body.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(document.body.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    loading: false,
    records: [],
  };

  init();

  function init() {
    bindEvents();
    updateMonthTitle();
    loadExpenses();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.expensesNav;
        if (dir === 'prev') {
          shiftMonth(-1);
        } else if (dir === 'next') {
          shiftMonth(1);
        }
      });
    });

    tableRows.addEventListener('click', (event) => {
      const action = event.target.closest('[data-expense-action]');
      if (!action) {
        return;
      }
      const id = action.dataset.id;
      const type = action.dataset.expenseAction;
      event.preventDefault();
      if (type === 'edit') {
        showMessage(`準備編輯 #${id}`, 'info');
      } else if (type === 'delete') {
        showMessage(`準備刪除 #${id}（尚未串接 API）`, 'warning');
      }
    });
  }

  function shiftMonth(delta) {
    if (state.loading) {
      return;
    }
    const date = new Date(state.year, state.month - 1 + delta, 1);
    state.year = date.getFullYear();
    state.month = date.getMonth() + 1;
    updateMonthTitle();
    loadExpenses();
  }

  function updateMonthTitle() {
    const rocYear = state.year - 1911;
    const title = `${rocYear}年${state.month}月各項費用表`;
    monthTitleEl.textContent = title;
  }

  function loadExpenses() {
    state.loading = true;
    renderRows([]);
    showMessage('尚未串接資料來源，請設定 API 後重新載入。', 'info', true);
    state.records = [];
    state.loading = false;
    renderRows(state.records);
  }

  function renderRows(records) {
    if (!records.length) {
      tableRows.innerHTML = '<tr><td colspan="5" class="table-empty">目前沒有費用資料</td></tr>';
      return;
    }

    const rows = records
      .map((record) => {
        return `<tr>
          <td>${escapeHtml(formatRocDate(record.entry_date))}</td>
          <td>${escapeHtml(record.subject)}</td>
          <td style="text-align:right;">${formatAmount(record.amount)}</td>
          <td>${escapeHtml(record.note || '')}</td>
          <td class="table__ops">
            <button type="button" class="btn btn--ghost btn--small" data-expense-action="edit" data-id="${record.id}">編輯</button>
            <button type="button" class="btn btn--secondary btn--small" data-expense-action="delete" data-id="${record.id}">刪除</button>
          </td>
        </tr>`;
      })
      .join('');

    tableRows.innerHTML = rows;
  }

  function showMessage(text, type = 'info', persist = false) {
    if (!messageEl) {
      return;
    }
    messageEl.textContent = text;
    messageEl.dataset.type = type;
    messageEl.hidden = false;
    if (!persist) {
      setTimeout(() => {
        hideMessage();
      }, 3000);
    }
  }

  function hideMessage() {
    if (messageEl) {
      messageEl.hidden = true;
      messageEl.textContent = '';
    }
  }

  function formatRocDate(value) {
    const date = toLocalDate(value);
    if (!date) {
      return '';
    }
    const rocYear = date.getFullYear() - 1911;
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${rocYear}/${month}/${day}`;
  }

  function toLocalDate(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function formatAmount(amount) {
    const num = Number(amount) || 0;
    return num.toLocaleString('zh-TW', { minimumFractionDigits: 0 });
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

})();
