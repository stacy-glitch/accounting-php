(function () {
  'use strict';

  const monthTitleEl = document.querySelector('[data-vehicle-month-title]');
  const navButtons = document.querySelectorAll('[data-vehicle-nav]');
  const tableRows = document.querySelector('[data-vehicle-rows]');
  const incomeTotalEl = document.querySelector('[data-vehicle-income-total]');
  const expenseTotalEl = document.querySelector('[data-vehicle-expense-total]');
  const netTotalEl = document.querySelector('[data-vehicle-net-total]');
  const messageEl = document.querySelector('[data-vehicle-message]');

  if (!monthTitleEl || !tableRows) {
    return;
  }

  const state = {
    year: parseInt(document.body.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(document.body.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    plate: document.body.dataset.initialPlate || '',
    loading: false,
    records: [],
  };

  init();

  function init() {
    bindEvents();
    updateMonthTitle();
    loadVehicleCosts();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.vehicleNav;
        if (dir === 'prev') {
          shiftMonth(-1);
        } else if (dir === 'next') {
          shiftMonth(1);
        }
      });
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
    loadVehicleCosts();
  }

  function updateMonthTitle() {
    const rocYear = state.year - 1911;
    monthTitleEl.textContent = `${rocYear}年${state.month}月${state.plate}成本表`;
  }

  function loadVehicleCosts() {
    state.loading = true;
    renderRows([]);
    showMessage(`尚未串接資料來源（車號 ${state.plate}），請設定 API 後重新載入。`, 'info', true);
    state.records = [];
    state.loading = false;
  }

  function renderRows(records) {
    if (!records.length) {
      tableRows.innerHTML = '<tr><td colspan="7" class="table-empty">目前沒有成本資料</td></tr>';
      updateTotals(0, 0);
      return;
    }

    let incomeTotal = 0;
    let expenseTotal = 0;

    const rows = records
      .map((record) => {
        const income = Number(record.income) || 0;
        const expense = Number(record.expense) || 0;
        incomeTotal += income;
        expenseTotal += expense;
        const net = income - expense;
        return `<tr>
          <td>${escapeHtml(formatRocDate(record.transaction_date))}</td>
          <td>${escapeHtml(record.subject || '')}</td>
          <td style="text-align:right;">${formatAmount(income)}</td>
          <td style="text-align:right;">${formatAmount(expense)}</td>
          <td style="text-align:right;">${formatAmount(net)}</td>
          <td>${escapeHtml(record.note || '')}</td>
          <td class="table__ops">
            <button type="button" class="btn btn--ghost btn--small" data-action="edit" data-id="${record.id || ''}">編輯</button>
            <button type="button" class="btn btn--secondary btn--small" data-action="delete" data-id="${record.id || ''}">刪除</button>
          </td>
        </tr>`;
      })
      .join('');

    tableRows.innerHTML = rows;
    updateTotals(incomeTotal, expenseTotal);
  }

  function updateTotals(income, expense) {
    const net = income - expense;
    if (incomeTotalEl) {
      incomeTotalEl.textContent = formatAmount(income);
    }
    if (expenseTotalEl) {
      expenseTotalEl.textContent = formatAmount(expense);
    }
    if (netTotalEl) {
      netTotalEl.textContent = formatAmount(net);
    }
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

  function formatAmount(value) {
    const num = Number(value) || 0;
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
