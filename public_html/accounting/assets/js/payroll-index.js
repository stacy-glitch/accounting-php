(function () {
  'use strict';

  const ROW_COUNT = 5;
  const EMPLOYEE_ENDPOINT = '../api/master-data/master_employees.php';
  const EMPLOYEE_FORMULAS = {
    E0001: 'standard',
    E0002: 'driverA',
    E0003: 'driverB',
  };

  const FORMULAS = {
    standard() {
      return {
        expenses: [
          { label: '健保', amount: 852 },
          { label: '勞保', amount: 659 },
          { label: '預支', amount: 10000 },
        ],
        incomes: [
          { label: '薪資', amount: 26400 },
          { label: '電話補助', amount: 1000 },
          { label: '薪資補助', amount: 12600 },
        ],
        note: '',
      };
    },
    driverA() {
      return {
        expenses: [
          { label: '健保', amount: 1200 },
          { label: '勞保', amount: 980 },
          { label: '油資', amount: 3000 },
        ],
        incomes: [
          { label: '薪資', amount: 30000 },
          { label: '油資補貼', amount: 3500 },
        ],
        note: '',
      };
    },
    driverB() {
      return {
        expenses: [
          { label: '健保', amount: 950 },
          { label: '勞保', amount: 780 },
        ],
        incomes: [
          { label: '薪資', amount: 28000 },
          { label: '考核獎金', amount: 5000 },
        ],
        note: '',
      };
    },
  };

  const expenseLabelEls = document.querySelectorAll('[data-expense-label]');
  const expenseAmountEls = document.querySelectorAll('[data-expense-amount]');
  const incomeLabelEls = document.querySelectorAll('[data-income-label]');
  const incomeAmountEls = document.querySelectorAll('[data-income-amount]');
  const noteEl = document.querySelector('[data-payroll-note]');
  const employeeSelect = document.querySelector('[data-payroll-select="employee"]');
  const employeeDisplayEl = document.querySelector('[data-payroll-employee-display]');
  const yearSelect = document.querySelector('[data-payroll-select="year"]');
  const monthSelect = document.querySelector('[data-payroll-select="month"]');
  const periodTextEl = document.querySelector('[data-payroll-period-text]');
  const expenseTotalEl = document.querySelector('[data-expense-total]');
  const incomeTotalEl = document.querySelector('[data-income-total]');
  const netAmountEl = document.querySelector('[data-net-amount]');
  const printBtn = document.querySelector('[data-payroll-action="print"]');
  const printInlineSelect = document.querySelector('[data-payroll-print-inline]');
  const printSelectedBtn = document.querySelector('[data-payroll-print-selected]');
  const printStackEl = document.querySelector('[data-payroll-print-stack]');
  const templateSelect = document.querySelector('[data-template-select]');
  const templateEmployeeDisplay = document.querySelector('[data-template-employee-display]');
  const templateExpenseLabelEls = document.querySelectorAll('[data-template-expense-label]');
  const templateExpenseAmountEls = document.querySelectorAll('[data-template-expense-amount]');
  const templateIncomeLabelEls = document.querySelectorAll('[data-template-income-label]');
  const templateIncomeAmountEls = document.querySelectorAll('[data-template-income-amount]');
  const templateNoteEl = document.querySelector('[data-template-note]');
  const templateExpenseTotalEl = document.querySelector('[data-template-expense-total]');
  const templateIncomeTotalEl = document.querySelector('[data-template-income-total]');
  const templateNetEl = document.querySelector('[data-template-net]');

  if (!employeeSelect) {
    return;
  }

  const state = {
    employees: [],
    periodText: '',
    expenses: Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' })),
    incomes: Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' })),
    note: '',
  };

  const templateStore = Object.create(null);
  const templateState = {
    currentEmployee: '',
  };

  init();

  function init() {
    populateYearMonth();
    bindEvents();
    renderRows();
    recalcTotals();
    loadEmployees();
  }

  function populateYearMonth() {
    const now = new Date();
    const currentRocYear = Number(document.body.dataset.rocYear) || now.getFullYear() - 1911;
    const currentMonth = Number(document.body.dataset.month) || now.getMonth() + 1;
    for (let offset = -1; offset <= 1; offset += 1) {
      const year = currentRocYear + offset;
      const option = document.createElement('option');
      option.value = String(year);
      option.textContent = `${year} 年`;
      if (year === currentRocYear) {
        option.selected = true;
      }
      yearSelect.appendChild(option);
    }
    for (let m = 1; m <= 12; m += 1) {
      const option = document.createElement('option');
      option.value = String(m);
      option.textContent = `${m} 月`;
      if (m === currentMonth) {
        option.selected = true;
      }
      monthSelect.appendChild(option);
    }
    updatePeriodText();
  }

  function loadEmployees() {
    fetch(EMPLOYEE_ENDPOINT, { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        const rows = Array.isArray(payload?.data) ? payload.data : [];
        state.employees = rows.map((row, index) => ({
          id: row.code || `E${1000 + index}`,
          name: row.name || row.code || `員工${index + 1}`,
        }));
        if (!state.employees.length) {
          throw new Error('no employees');
        }
        populateEmployeeSelect();
        applyFormula();
      })
      .catch((error) => {
        console.error('load employees failed', error);
        state.employees = [
          { id: 'E0001', name: '員工一' },
          { id: 'E0002', name: '員工二' },
        ];
        populateEmployeeSelect();
        applyFormula();
      });
  }

  function populateEmployeeSelect() {
    employeeSelect.innerHTML = '';
    if (printInlineSelect) {
      printInlineSelect.innerHTML = '';
    }
    if (templateSelect) {
      templateSelect.innerHTML = '';
    }
    state.employees.forEach((employee, index) => {
      const option = document.createElement('option');
      option.value = employee.id;
      option.textContent = `${employee.id} — ${employee.name}`;
      if (index === 0) {
        option.selected = true;
      }
      employeeSelect.appendChild(option);
      if (printInlineSelect) {
        const printOption = option.cloneNode(true);
        printInlineSelect.appendChild(printOption);
      }
      if (templateSelect) {
        const templateOption = option.cloneNode(true);
        templateSelect.appendChild(templateOption);
      }
    });
    updateEmployeeDisplay(getSelectedEmployee());
    if (templateSelect) {
      templateSelect.dispatchEvent(new Event('change'));
    }
  }

  function getSelectedEmployee() {
    return state.employees.find((emp) => emp.id === employeeSelect.value) || state.employees[0];
  }

  function bindEvents() {
    employeeSelect.addEventListener('change', () => {
      updateEmployeeDisplay(getSelectedEmployee());
      applyFormula();
    });
    yearSelect.addEventListener('change', () => {
      updatePeriodText();
      applyFormula();
    });
    monthSelect.addEventListener('change', () => {
      updatePeriodText();
      applyFormula();
    });
    printBtn.addEventListener('click', () => window.print());
    if (printSelectedBtn) {
      printSelectedBtn.addEventListener('click', handlePrintSelected);
    }
    expenseLabelEls.forEach((input, index) => {
      input.addEventListener('input', () => {
        state.expenses[index].label = input.value;
      });
    });
    expenseAmountEls.forEach((input, index) => {
      input.addEventListener('input', () => {
        state.expenses[index].amount = input.value;
        recalcTotals();
      });
    });
    incomeLabelEls.forEach((input, index) => {
      input.addEventListener('input', () => {
        state.incomes[index].label = input.value;
      });
    });
    incomeAmountEls.forEach((input, index) => {
      input.addEventListener('input', () => {
        state.incomes[index].amount = input.value;
        recalcTotals();
      });
    });
    if (noteEl) {
      noteEl.addEventListener('input', () => {
        state.note = noteEl.value;
      });
    }

    if (templateSelect) {
      templateSelect.addEventListener('change', handleTemplateSelectChange);
    }
    templateExpenseAmountEls.forEach((input) => {
      input.addEventListener('input', updateTemplatePreviewTotals);
    });
    templateIncomeAmountEls.forEach((input) => {
      input.addEventListener('input', updateTemplatePreviewTotals);
    });
    const templateSaveBtn = document.querySelector('[data-template-action="save"]');
    if (templateSaveBtn) {
      templateSaveBtn.addEventListener('click', handleTemplateSave);
    }
    const templateCreateBtn = document.querySelector('[data-template-action="create"]');
    if (templateCreateBtn) {
      templateCreateBtn.addEventListener('click', handleTemplateCreate);
    }
  }

  function applyFormula() {
    const employee = getSelectedEmployee();
    if (!employee) {
      return;
    }
    updateEmployeeDisplay(employee);
    const template = getTemplateConfig(employee.id);
    resetRows();
    template.expenses.forEach((item, index) => {
      if (index < ROW_COUNT) {
        state.expenses[index].label = item.label || '';
        state.expenses[index].amount = item.amount || '';
      }
    });
    template.incomes.forEach((item, index) => {
      if (index < ROW_COUNT) {
        state.incomes[index].label = item.label || '';
        state.incomes[index].amount = item.amount || '';
      }
    });
    state.note = template.note || '';
    renderRows();
    recalcTotals();
  }

  function resetRows() {
    state.expenses = Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' }));
    state.incomes = Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' }));
  }

  function renderRows() {
    state.expenses.forEach((item, index) => {
      if (expenseLabelEls[index]) {
        expenseLabelEls[index].value = item.label || '';
      }
      if (expenseAmountEls[index]) {
        expenseAmountEls[index].value = item.amount || '';
      }
    });
    state.incomes.forEach((item, index) => {
      if (incomeLabelEls[index]) {
        incomeLabelEls[index].value = item.label || '';
      }
      if (incomeAmountEls[index]) {
        incomeAmountEls[index].value = item.amount || '';
      }
    });
    if (noteEl) {
      noteEl.value = state.note || '';
    }
  }

  function recalcTotals() {
    const expenseTotal = state.expenses.reduce((sum, item) => sum + toNumber(item.amount), 0);
    const incomeTotal = state.incomes.reduce((sum, item) => sum + toNumber(item.amount), 0);
    if (expenseTotalEl) {
      expenseTotalEl.textContent = `$ ${formatNumber(expenseTotal)}`;
    }
    if (incomeTotalEl) {
      incomeTotalEl.textContent = `$ ${formatNumber(incomeTotal)}`;
    }
    if (netAmountEl) {
      netAmountEl.textContent = `$ ${formatNumber(incomeTotal - expenseTotal)}`;
    }
  }

  function toNumber(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
  }

  function updatePeriodText() {
    if (!periodTextEl) return;
    const year = yearSelect ? yearSelect.value : '';
    const month = monthSelect ? monthSelect.value : '';
    if (!year || !month) {
      periodTextEl.textContent = '';
      state.periodText = '';
      return;
    }
    const text = `${year}年${month}月份薪資表`;
    periodTextEl.textContent = text;
    state.periodText = text;
  }

  function handlePrintSelected() {
    if (!printInlineSelect) {
      return;
    }
    const selectedIds = Array.from(printInlineSelect.selectedOptions).map((opt) => opt.value);
    if (!selectedIds.length) {
      window.alert('請先在清單中勾選要列印的員工。');
      return;
    }
    const list = state.employees.filter((emp) => selectedIds.includes(emp.id));
    if (!list.length) {
      window.alert('找不到對應的員工資料');
      return;
    }
    preparePrintSheets(list);
    triggerBatchPrint();
  }

  function preparePrintSheets(employees) {
    if (!printStackEl) {
      return;
    }
    printStackEl.innerHTML = '';
    const period = state.periodText || buildPeriodText();
    employees.forEach((employee) => {
      const sheetData = buildSheetData(employee);
      printStackEl.appendChild(buildSheetElement(employee, sheetData, period));
    });
  }

  function buildSheetData(employee) {
    const template = getTemplateConfig(employee.id);
    const expenses = padRows(template.expenses);
    const incomes = padRows(template.incomes);
    const expenseTotal = expenses.reduce((sum, item) => sum + toNumber(item.amount), 0);
    const incomeTotal = incomes.reduce((sum, item) => sum + toNumber(item.amount), 0);
    return {
      expenses,
      incomes,
      note: template.note || '',
      expenseTotal,
      incomeTotal,
      net: incomeTotal - expenseTotal,
    };
  }

  function padRows(items = []) {
    const rows = Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' }));
    items.forEach((item, index) => {
      if (index < ROW_COUNT) {
        const amountValue =
          item.amount !== undefined && item.amount !== null && item.amount !== ''
            ? Number(item.amount)
            : '';
        rows[index] = {
          label: item.label || '',
          amount: amountValue,
        };
      }
    });
    return rows;
  }

  function getTemplateConfig(employeeId) {
    if (!employeeId) {
      return normalizeTemplate();
    }
    if (templateStore[employeeId]) {
      return cloneTemplate(templateStore[employeeId]);
    }
    const formulaKey = EMPLOYEE_FORMULAS[employeeId] || 'standard';
    const builder = FORMULAS[formulaKey] || FORMULAS.standard;
    const fallback = builder ? builder() : {};
    return normalizeTemplate(fallback);
  }

  function normalizeTemplate(config = {}) {
    return {
      expenses: padTemplateEntries(config.expenses),
      incomes: padTemplateEntries(config.incomes),
      note: config.note || '',
    };
  }

  function padTemplateEntries(list = []) {
    return Array.from({ length: ROW_COUNT }, (_, index) => {
      const item = Array.isArray(list) && list[index] ? list[index] : {};
      return {
        label: item.label || '',
        amount: item.amount !== undefined && item.amount !== null ? String(item.amount) : '',
      };
    });
  }

  function cloneTemplate(data) {
    return normalizeTemplate({
      expenses: data.expenses ? data.expenses.map((item) => ({ ...item })) : [],
      incomes: data.incomes ? data.incomes.map((item) => ({ ...item })) : [],
      note: data.note || '',
    });
  }

  function buildSheetElement(employee, data, periodText) {
    const section = document.createElement('section');
    section.className = 'payroll-sheet';
    const expenseRows = data.expenses
      .map((row, index) => {
        const incomeRow = data.incomes[index] || { label: '', amount: '' };
        const noteCell =
          index === 0
            ? `<td class="payroll-note-cell" rowspan="${ROW_COUNT + 2}"><div class="payroll-note-static">${formatNote(data.note)}</div></td>`
            : '';
        return `
          <tr>
            <td class="payroll-cell-label">${escapeHtml(row.label || '')}</td>
            <td class="payroll-cell-amount"><span class="payroll-currency">$</span><span>${formatNumber(
              row.amount
            )}</span></td>
            <td class="payroll-cell-label">${escapeHtml(incomeRow.label || '')}</td>
            <td class="payroll-cell-amount"><span class="payroll-currency">$</span><span>${formatNumber(
              incomeRow.amount
            )}</span></td>
            ${noteCell}
          </tr>
        `;
      })
      .join('');
    section.innerHTML = `
      <div class="payroll-sheet__header">
        <div class="payroll-sheet__company">足達貨運公司</div>
        <div class="payroll-sheet__period">${escapeHtml(periodText)}</div>
        <div class="payroll-sheet__employee">${escapeHtml(employee.name || employee.id)}</div>
      </div>
      <table class="payroll-table payroll-table--print">
        <thead>
          <tr>
            <th colspan="2">支出項目</th>
            <th colspan="2">收入項目</th>
            <th>備註</th>
          </tr>
        </thead>
        <tbody>
          ${expenseRows}
          <tr class="payroll-total-row">
            <td class="payroll-total-label">合計</td>
            <td class="payroll-total-value">
              <span class="payroll-currency">$</span>
              <span>${formatNumber(data.expenseTotal)}</span>
            </td>
            <td class="payroll-total-label">合計</td>
            <td class="payroll-total-value">
              <span class="payroll-currency">$</span>
              <span>${formatNumber(data.incomeTotal)}</span>
            </td>
          </tr>
          <tr class="payroll-net-row">
            <td class="payroll-total-label" colspan="2">收入－支出</td>
            <td class="payroll-net-total" colspan="2">$ ${formatNumber(data.net)}</td>
          </tr>
        </tbody>
      </table>
    `;
    return section;
  }

  function handleTemplateSelectChange() {
    if (!templateSelect) return;
    const employeeId = templateSelect.value;
    if (!employeeId) return;
    renderTemplateForm(employeeId);
  }

  function renderTemplateForm(employeeId) {
    templateState.currentEmployee = employeeId;
    if (templateSelect && templateSelect.value !== employeeId) {
      templateSelect.value = employeeId;
    }
    const employee = state.employees.find((emp) => emp.id === employeeId);
    if (templateEmployeeDisplay) {
      templateEmployeeDisplay.textContent = employee ? employee.name : '';
    }
    const template = getTemplateConfig(employeeId);
    templateExpenseLabelEls.forEach((input, index) => {
      input.value = template.expenses[index]?.label || '';
    });
    templateExpenseAmountEls.forEach((input, index) => {
      input.value = template.expenses[index]?.amount || '';
    });
    templateIncomeLabelEls.forEach((input, index) => {
      input.value = template.incomes[index]?.label || '';
    });
    templateIncomeAmountEls.forEach((input, index) => {
      input.value = template.incomes[index]?.amount || '';
    });
    if (templateNoteEl) {
      templateNoteEl.value = template.note || '';
    }
    updateTemplatePreviewTotals();
  }

  function collectTemplateValues() {
    const expenses = Array.from({ length: ROW_COUNT }, (_, index) => ({
      label: templateExpenseLabelEls[index] ? templateExpenseLabelEls[index].value : '',
      amount: templateExpenseAmountEls[index] ? templateExpenseAmountEls[index].value : '',
    }));
    const incomes = Array.from({ length: ROW_COUNT }, (_, index) => ({
      label: templateIncomeLabelEls[index] ? templateIncomeLabelEls[index].value : '',
      amount: templateIncomeAmountEls[index] ? templateIncomeAmountEls[index].value : '',
    }));
    const note = templateNoteEl ? templateNoteEl.value : '';
    return normalizeTemplate({ expenses, incomes, note });
  }

  function updateTemplatePreviewTotals() {
    const values = collectTemplateValues();
    const expenseTotal = values.expenses.reduce((sum, item) => sum + toNumber(item.amount), 0);
    const incomeTotal = values.incomes.reduce((sum, item) => sum + toNumber(item.amount), 0);
    if (templateExpenseTotalEl) {
      templateExpenseTotalEl.textContent = `$ ${formatNumber(expenseTotal)}`;
    }
    if (templateIncomeTotalEl) {
      templateIncomeTotalEl.textContent = `$ ${formatNumber(incomeTotal)}`;
    }
    if (templateNetEl) {
      templateNetEl.textContent = `$ ${formatNumber(incomeTotal - expenseTotal)}`;
    }
  }

  function handleTemplateSave() {
    if (!templateSelect) return;
    const employeeId = templateSelect.value;
    if (!employeeId) {
      window.alert('請先選擇員工');
      return;
    }
    templateStore[employeeId] = collectTemplateValues();
    showMessage('success', '模板已儲存');
    if (employeeSelect && employeeSelect.value === employeeId) {
      applyFormula();
    }
  }

  function handleTemplateCreate() {
    if (!templateSelect) return;
    const employeeId = templateSelect.value;
    if (!employeeId) {
      window.alert('請先選擇員工');
      return;
    }
    templateStore[employeeId] = normalizeTemplate();
    renderTemplateForm(employeeId);
    showMessage('info', '已建立空白模板，請輸入內容後儲存。');
  }

  function triggerBatchPrint() {
    if (!printStackEl || !printStackEl.children.length) {
      window.alert('沒有可列印的薪資表');
      return;
    }
    document.body.classList.add('payroll-batch-print');
    window.print();
    let cleaned = false;
    let cleanupTimer = null;
    const cleanup = () => {
      if (cleaned) return;
      cleaned = true;
      document.body.classList.remove('payroll-batch-print');
      if (printStackEl) {
        printStackEl.innerHTML = '';
      }
      window.removeEventListener('afterprint', cleanup);
      if (cleanupTimer) {
        clearTimeout(cleanupTimer);
      }
    };
    window.addEventListener('afterprint', cleanup);
    cleanupTimer = window.setTimeout(() => {
      cleanup();
    }, 1000);
  }

  function formatNumber(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) {
      return '';
    }
    return num === 0 ? '0' : num.toLocaleString('en-US');
  }

  function formatNote(note) {
    const safe = escapeHtml(note || '');
    return `<div class="payroll-note-text">${safe.replace(/\n/g, '<br>') || '&nbsp;'}</div>`;
  }

  function buildPeriodText() {
    const year = yearSelect ? yearSelect.value : '';
    const month = monthSelect ? monthSelect.value : '';
    if (!year || !month) {
      return '';
    }
    return `${year}年${month}月份薪資表`;
  }

  function updateEmployeeDisplay(employee) {
    if (!employeeDisplayEl) return;
    employeeDisplayEl.textContent = employee && employee.name ? employee.name : '';
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
})();
