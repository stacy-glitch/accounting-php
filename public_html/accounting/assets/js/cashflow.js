(function () {
  'use strict';

  const monthTitleEl = document.querySelector('[data-cashflow-month-title]');
  const navButtons = document.querySelectorAll('[data-cashflow-nav]');
  const tableRows = document.querySelector('[data-cashflow-rows]');
  const expenseTotalEl = document.querySelector('[data-cashflow-expense-total]');
  const incomeTotalEl = document.querySelector('[data-cashflow-income-total]');
  const messageEl = document.querySelector('[data-cashflow-message]');

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
    loadCashflow();
  }

  function bindEvents() {
    navButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.cashflowNav;
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
    loadCashflow();
  }

  function updateMonthTitle() {
    const rocYear = state.year - 1911;
    monthTitleEl.textContent = `${rocYear}年${state.month}月收支表`;
  }

  function loadCashflow() {
    state.loading = true;
    state.records = [];
    renderRows(state.records);
    showMessage('尚未串接資料來源，請設定 API 後重新載入。', 'info', true);
    state.loading = false;
  }

  function renderRows(records) {
    if (!records.length) {
      tableRows.innerHTML = '<tr><td colspan="4" class="table-empty">目前沒有收支資料</td></tr>';
      updateTotals(0, 0);
      return;
    }

    let expenseTotal = 0;
    let incomeTotal = 0;

    const rows = records
      .map((record) => {
        const expenseAmount = Number(record.expense_amount) || 0;
        const incomeAmount = Number(record.income_amount) || 0;
        expenseTotal += expenseAmount;
        incomeTotal += incomeAmount;
        return `<tr>
          <td>${escapeHtml(record.expense_item || '')}</td>
          <td style="text-align:right;">${formatAmount(expenseAmount)}</td>
          <td>${escapeHtml(record.income_item || '')}</td>
          <td style="text-align:right;">${formatAmount(incomeAmount)}</td>
        </tr>`;
      })
      .join('');

    tableRows.innerHTML = rows;
    updateTotals(expenseTotal, incomeTotal);
  }

  function updateTotals(expense, income) {
    if (expenseTotalEl) {
      expenseTotalEl.textContent = formatAmount(expense);
    }
    if (incomeTotalEl) {
      incomeTotalEl.textContent = formatAmount(income);
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
