(function () {
  'use strict';

  const ROW_COUNT = 11;
  const AUTO_ENDPOINT = '../api/payroll/payroll_autofill.php';
  const EMPLOYEE_ENDPOINT = '../api/master-data/master_employees.php';
  const RECORDS_ENDPOINT = '../api/payroll/payroll_records.php';
  const EMPLOYEE_FORMULAS = {
    E0001: 'standard',
    E0002: 'driverA',
    E0003: 'driverB',
  };
  const DEFAULT_EMPLOYEES = buildDefaultEmployees();

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
  const printPickerEl = document.querySelector('[data-print-picker]');
  const printPickerToggle = document.querySelector('[data-print-picker-toggle]');
  const printPickerDropdown = document.querySelector('[data-print-picker-dropdown]');
  const printPickerSummary = document.querySelector('[data-print-picker-summary]');
  const printSelectedBtn = document.querySelector('[data-payroll-print-selected]');
  const printStackEl = document.querySelector('[data-payroll-print-stack]');
  const addDetailBtn = document.querySelector('[data-payroll-action="add-detail"]');
  const savedCardEl = document.querySelector('.payroll-saved-card');
  const toastRoot = initToast();
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
  const templateListEl = document.querySelector('[data-template-list]');
  const savedSelectEl = document.querySelector('[data-saved-select]');
  const savedPeriodEl = document.querySelector('[data-saved-period]');
  const savedPreviewEl = document.querySelector('[data-saved-preview]');
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

  let autoFields = { income: [], expense: [] };
  let autoSheets = {};
  let autoWarnings = [];
  let autoRequestId = 0;

  const templateStore = Object.create(null);
  const templateState = {
    currentEmployee: '',
  };
  const printSelection = new Set();
  let savedSheets = [];
  const savedState = (() => {
    const now = new Date();
    const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    return {
      year: prev.getFullYear() - 1911,
      month: prev.getMonth() + 1,
    };
  })();

  if (templateListEl) {
    templateListEl.addEventListener('click', (event) => {
      const button = event.target.closest('[data-template-preview]');
      if (!button) return;
      const id = button.dataset.templatePreview;
      if (!id) return;
      if (templateSelect) {
        templateSelect.value = id;
      }
      renderTemplateForm(id);
      showMessage('info', `已載入員工 ${id} 的模板`);
      const templateCard = document.querySelector('.payroll-template-card');
      if (templateCard) {
        const top = templateCard.getBoundingClientRect().top + window.scrollY - 40;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  }

  init();

  function init() {
    populateYearMonth();
    bindEvents();
    renderRows();
    recalcTotals();
    loadEmployees();
    renderTemplateList();
    refreshSavedRecords();
  }

  function populateYearMonth() {
    const now = new Date();
    const baseYear = Number(document.body.dataset.rocYear) || now.getFullYear() - 1911;
    const baseMonth = Number(document.body.dataset.month) || now.getMonth() + 1;
    let defaultYear = baseYear;
    let defaultMonth = baseMonth - 1;
    if (defaultMonth <= 0) {
      defaultMonth += 12;
      defaultYear -= 1;
    }
    for (let offset = -1; offset <= 1; offset += 1) {
      const year = baseYear + offset;
      const option = document.createElement('option');
      option.value = String(year);
      option.textContent = `${year} 年`;
      if (year === defaultYear) {
        option.selected = true;
      }
      yearSelect.appendChild(option);
    }
    for (let m = 1; m <= 12; m += 1) {
      const option = document.createElement('option');
      option.value = String(m);
      option.textContent = `${m} 月`;
      if (m === defaultMonth) {
        option.selected = true;
      }
      monthSelect.appendChild(option);
    }
    savedState.year = defaultYear;
    savedState.month = defaultMonth;
    updatePeriodText();
    renderSavedRecords();
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
        const normalized = rows
          .map((row, index) => {
            const code = typeof row?.code === 'string' ? row.code : '';
            const sourceId = code || (typeof row?.id === 'string' ? row.id : '');
            const id = normalizeEmployeeId(sourceId, index);
            const nameValue =
              typeof row?.name === 'string' && row.name.trim()
                ? row.name.trim()
                : code || `員工${index + 1}`;
            if (!id || !nameValue) {
              return null;
            }
            return { id, name: nameValue };
          })
          .filter(Boolean);
        const restricted = restrictEmployeeList(normalized);
        if (!restricted.length) {
          throw new Error('no employees');
        }
        applyEmployeeList(restricted);
        return loadAutoData({ reapply: true });
      })
      .catch((error) => {
        console.error('load employees failed', error);
        fallbackToDefaultEmployees();
      });
  }

  function loadAutoData(options = {}) {
    const { reapply = true } = options;
    if (!yearSelect || !monthSelect) {
      return Promise.resolve();
    }
    const rocYear = Number(yearSelect.value);
    const targetMonth = Number(monthSelect.value);
    if (!rocYear || !targetMonth) {
      autoSheets = {};
      return Promise.resolve();
    }
    const params = new URLSearchParams({
      roc_year: String(rocYear),
      month: String(targetMonth),
    });
    const requestId = ++autoRequestId;
    return fetch(`${AUTO_ENDPOINT}?${params.toString()}`, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => null)
          .then((data) => {
            if (!response.ok || !data?.ok) {
              throw new Error(data?.error || `HTTP ${response.status}`);
            }
            return data;
          })
      )
      .then((payload) => {
        if (requestId !== autoRequestId) {
          return;
        }
        autoFields = {
          income: Array.isArray(payload?.fields?.income) ? payload.fields.income : [],
          expense: Array.isArray(payload?.fields?.expense) ? payload.fields.expense : [],
        };
        autoSheets = payload?.employees && typeof payload.employees === 'object' ? payload.employees : {};
        autoWarnings = Array.isArray(payload?.warnings) ? payload.warnings : [];
        if (autoWarnings.length) {
          console.warn('[payroll] 自動帶入警示：', autoWarnings);
        }
        if (reapply) {
          applyFormula({ useAuto: true });
        }
      })
      .catch((error) => {
        if (requestId !== autoRequestId) {
          return;
        }
        console.error('load auto payroll data failed', error);
        autoSheets = {};
        if (reapply) {
          applyFormula({ useAuto: false });
        }
      });
  }

  function populateEmployeeSelect() {
    printSelection.clear();
    employeeSelect.innerHTML = '';
    if (templateSelect) {
      templateSelect.innerHTML = '';
    }
    state.employees.forEach((employee, index) => {
      const option = document.createElement('option');
      option.value = employee.id;
      option.textContent = employee.name || employee.id;
      if (index === 0) {
        option.selected = true;
      }
      employeeSelect.appendChild(option);
      if (templateSelect) {
        const templateOption = option.cloneNode(true);
        templateSelect.appendChild(templateOption);
      }
    });
    renderPrintPicker();
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
    yearSelect.addEventListener('change', handlePeriodChange);
    monthSelect.addEventListener('change', handlePeriodChange);
    printBtn.addEventListener('click', handlePrintCurrent);
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
    if (printPickerToggle && printPickerDropdown) {
      printPickerToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        const isHidden = printPickerDropdown.hasAttribute('hidden');
        closePrintPicker();
        if (isHidden) {
          renderPrintPicker();
          printPickerDropdown.removeAttribute('hidden');
          document.addEventListener('click', handlePrintPickerOutside, { once: true });
        }
      });
      printPickerDropdown.addEventListener('click', (event) => {
        event.stopPropagation();
      });
    }

    if (addDetailBtn) {
      addDetailBtn.addEventListener('click', handleAddDetail);
    }
    const saveCurrentBtn = document.querySelector('[data-saved-action="save-current"]');
    if (saveCurrentBtn) {
      saveCurrentBtn.addEventListener('click', handleSaveCurrentSheet);
    }
    const prevMonthBtn = document.querySelector('[data-saved-action="prev-month"]');
    const nextMonthBtn = document.querySelector('[data-saved-action="next-month"]');
    if (prevMonthBtn) {
      prevMonthBtn.addEventListener('click', () => adjustSavedMonth(-1));
    }
    if (nextMonthBtn) {
      nextMonthBtn.addEventListener('click', () => adjustSavedMonth(1));
    }
    if (savedSelectEl) {
      savedSelectEl.addEventListener('change', () => {
        const code = savedSelectEl.value;
        if (!code) {
          renderSavedPreview(null);
          return;
        }
        const record = savedSheets.find(
          (item) => item.employeeId === code && item.year === savedState.year && item.month === savedState.month
        );
        renderSavedPreview(record || null);
      });
    }
  }

  function applyFormula(options = {}) {
    const employee = getSelectedEmployee();
    if (!employee) {
      return;
    }
    updateEmployeeDisplay(employee);
    const preferAuto = options.useAuto !== false;
    const template = (preferAuto ? getAutoSheet(employee.id) : null) || getTemplateConfig(employee.id);
    resetRows();
    assignRows(state.expenses, template.expenses);
    assignRows(state.incomes, template.incomes);
    state.note = template.note || '';
    renderRows();
    recalcTotals();
  }

  function resetRows() {
    state.expenses = Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' }));
    state.incomes = Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' }));
  }

  function assignRows(targetRows, sourceRows = []) {
    sourceRows.forEach((item, index) => {
      if (index < ROW_COUNT) {
        targetRows[index].label = item.label || '';
        if (item.amount === '' || item.amount === null || item.amount === undefined) {
          targetRows[index].amount = '';
        } else {
          const num = Number(item.amount);
          targetRows[index].amount = Number.isFinite(num) ? String(num) : String(item.amount);
        }
      }
    });
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

  function renderPrintPicker() {
    if (!printPickerDropdown) {
      return;
    }
    printPickerDropdown.innerHTML = '';
    state.employees.forEach((employee) => {
      const label = document.createElement('label');
      label.className = 'payroll-print-option';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.value = employee.id;
      checkbox.checked = printSelection.has(employee.id);
      checkbox.addEventListener('change', () => {
        if (checkbox.checked) {
          printSelection.add(employee.id);
        } else {
          printSelection.delete(employee.id);
        }
        updatePrintPickerSummary();
      });
      const span = document.createElement('span');
      span.textContent = employee.name || employee.id;
      label.appendChild(checkbox);
      label.appendChild(span);
      printPickerDropdown.appendChild(label);
    });
    updatePrintPickerSummary();
  }

  function updatePrintPickerSummary() {
    if (!printPickerSummary) {
      return;
    }
    const count = printSelection.size;
    if (count === 0) {
      printPickerSummary.textContent = '未選擇';
      return;
    }
    if (count === 1) {
      const id = printSelection.values().next().value;
      const employee = state.employees.find((emp) => emp.id === id);
      printPickerSummary.textContent = employee ? employee.name || employee.id : id;
      return;
    }
    printPickerSummary.textContent = `已選 ${count} 位`;
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

  function handlePeriodChange() {
    updatePeriodText();
    autoSheets = {};
    applyFormula({ useAuto: false });
    loadAutoData({ reapply: true });
  }

  function handlePrintSelected() {
    const selectedIds = Array.from(printSelection);
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

  function handlePrintCurrent() {
    const employee = getSelectedEmployee();
    if (!employee) {
      window.alert('請先選擇員工。');
      return;
    }
    preparePrintSheets([employee]);
    triggerBatchPrint();
  }

  function preparePrintSheets(employees) {
    if (!printStackEl) {
      return;
    }
    printStackEl.innerHTML = '';
    const period = state.periodText || buildPeriodText();
    const list = (Array.isArray(employees) ? employees : []).filter(Boolean);
    if (!list.length) {
      return;
    }
    list.forEach((employee) => {
      const sheetData = buildSheetData(employee);
      if (sheetData) {
        printStackEl.appendChild(buildSheetElement(employee, sheetData, period));
      }
    });
  }

  function buildSheetData(employee) {
    if (!employee || !employee.id) {
      return null;
    }
    const template = getAutoSheet(employee.id) || getTemplateConfig(employee.id);
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

  function getAutoSheet(employeeId) {
    if (!employeeId || !autoSheets || !autoFields) {
      return null;
    }
    const entry = autoSheets[employeeId];
    if (!entry) {
      return null;
    }
    return {
      expenses: buildAutoRows(autoFields.expense, entry.expense),
      incomes: buildAutoRows(autoFields.income, entry.income),
      note: entry.note || '',
    };
  }

  function buildAutoRows(fieldDefs = [], values = {}) {
    const rows = Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' }));
    if (!Array.isArray(fieldDefs) || !fieldDefs.length) {
      return rows;
    }
    fieldDefs.forEach((field, index) => {
      if (index >= ROW_COUNT) {
        return;
      }
      const label = field && typeof field.label === 'string' ? field.label : '';
      const fieldId = field && typeof field.id === 'string' ? field.id : '';
      rows[index].label = label;
      if (fieldId && values && Object.prototype.hasOwnProperty.call(values, fieldId)) {
        const raw = values[fieldId];
        const num = Number(raw);
        if (Number.isFinite(num) && num !== 0) {
          rows[index].amount = String(num);
        } else {
          rows[index].amount = '';
        }
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
    section.className = 'payroll-sheet payslip';
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
    renderTemplateList();
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
    renderTemplateList();
    showMessage('info', '已建立空白模板，請輸入內容後儲存。');
  }

  function renderTemplateList() {
    if (!templateListEl) {
      return;
    }
    const entries = Object.keys(templateStore);
    if (!entries.length) {
      templateListEl.innerHTML = '<div class="payroll-template-empty">尚未建立模板</div>';
      return;
    }
    const rows = entries
      .map((id) => {
        const employee = state.employees.find((emp) => emp.id === id);
        const template = templateStore[id];
        const expenseSummary = summarizeTemplateItems(template.expenses);
        const incomeSummary = summarizeTemplateItems(template.incomes);
        return `
          <tr>
            <td>${escapeHtml(id)}</td>
            <td>${escapeHtml(employee?.name || '')}</td>
            <td>${escapeHtml(expenseSummary)}</td>
            <td>${escapeHtml(incomeSummary)}</td>
            <td>${escapeHtml(template.note || '')}</td>
            <td>
              <button type="button" class="btn btn--secondary btn--small" data-template-preview="${escapeHtml(id)}">預覽</button>
            </td>
          </tr>
        `;
      })
      .join('');
    templateListEl.innerHTML = `
      <table class="payroll-template-list">
        <thead>
          <tr>
            <th>員工代號</th>
            <th>員工姓名</th>
            <th>支出摘要</th>
            <th>收入摘要</th>
            <th>備註</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  }

  function summarizeTemplateItems(items = []) {
    const labels = (items || []).map((item) => item.label).filter(Boolean);
    if (!labels.length) {
      return '—';
    }
    if (labels.length <= 2) {
      return labels.join('、');
    }
    return `${labels.slice(0, 2).join('、')} 等 ${labels.length} 項`;
  }

  function handleAddDetail() {
    saveCurrentSheet()
      .then((record) => {
        if (!record) {
          return;
        }
        scrollToSavedCard();
        showMessage('success', `已新增 ${record.employeeName || record.employeeId} 的薪資明細`);
      })
      .catch((error) => {
        console.error('add payroll detail failed', error);
        showMessage('error', error.message || '新增明細失敗，請稍後再試');
      });
  }

  function handleSaveCurrentSheet() {
    saveCurrentSheet()
      .then((record) => {
        if (!record) {
          return;
        }
        showMessage('success', `已儲存 ${record.employeeName || record.employeeId} 的薪資表`);
      })
      .catch((error) => {
        console.error('save payroll sheet failed', error);
        showMessage('error', error.message || '儲存失敗，請稍後再試');
      });
  }

  function saveCurrentSheet() {
    syncSavedStateToCurrentPeriod();
    const employee = getSelectedEmployee();
    if (!employee) {
      window.alert('請先選擇員工');
      return Promise.resolve(null);
    }
    const expenses = serializeEntries(state.expenses);
    const incomes = serializeEntries(state.incomes);
    const expenseTotal = expenses.reduce((sum, item) => sum + toNumber(item.amount), 0);
    const incomeTotal = incomes.reduce((sum, item) => sum + toNumber(item.amount), 0);
    const record = {
      employeeId: employee.id,
      employeeName: employee.name || '',
      year: savedState.year,
      month: savedState.month,
      expenses,
      incomes,
      note: state.note || '',
      expenseTotal,
      incomeTotal,
      net: incomeTotal - expenseTotal,
      savedAt: new Date().toISOString(),
    };
    return persistPayrollRecord(record)
      .then(() => refreshSavedRecords(record.employeeId).catch(() => {}))
      .then(() => {
        selectPrintEmployee(record.employeeId);
        return record;
      });
  }

  function renderSavedRecords(preferredEmployeeId = '') {
    updateSavedPeriodLabel();
    const filtered = savedSheets.filter(
      (item) => item.year === savedState.year && item.month === savedState.month
    );
    const fallbackEmployeeId = preferredEmployeeId || (savedSelectEl ? savedSelectEl.value : '');
    populateSavedSelect(filtered, fallbackEmployeeId);
    if (!filtered.length) {
      renderSavedPreview(null);
      return;
    }
    let target = null;
    const selectionId = savedSelectEl ? savedSelectEl.value : '';
    if (selectionId) {
      target = filtered.find((item) => item.employeeId === selectionId) || null;
    }
    if (!target && fallbackEmployeeId) {
      target = filtered.find((item) => item.employeeId === fallbackEmployeeId) || null;
    }
    if (!target) {
      target = filtered[0];
      if (savedSelectEl) {
        savedSelectEl.value = target.employeeId;
      }
    }
    renderSavedPreview(target);
  }

  function refreshSavedRecords(preferredEmployeeId = '') {
    return fetchSavedRecords()
      .then(() => {
        renderSavedRecords(preferredEmployeeId);
      })
      .catch((error) => {
        console.error('load saved payroll records failed', error);
        showMessage('error', '無法載入薪資紀錄，請稍後再試');
        savedSheets = [];
        renderSavedRecords();
      });
  }

  function fetchSavedRecords() {
    const params = new URLSearchParams({
      roc_year: String(savedState.year),
      month: String(savedState.month),
    });
    return fetch(`${RECORDS_ENDPOINT}?${params.toString()}`, { credentials: 'same-origin' })
      .then((response) =>
        response
          .json()
          .catch(() => null)
          .then((data) => {
            if (!response.ok || !data?.ok) {
              throw new Error(data?.error || `HTTP ${response.status}`);
            }
            return data;
          })
      )
      .then((payload) => {
        savedSheets = Array.isArray(payload.records) ? payload.records : [];
      });
  }

  function populateSavedSelect(list, preferredEmployeeId = '') {
    if (!savedSelectEl) return;
    savedSelectEl.innerHTML = '<option value="">-- 未選擇 --</option>';
    list.forEach((record) => {
      const option = document.createElement('option');
      option.value = record.employeeId;
      const label = state.employees.find((emp) => emp.id === record.employeeId)?.name || record.employeeId;
      option.textContent = label || record.employeeId;
      savedSelectEl.appendChild(option);
    });
    if (!list.length) {
      savedSelectEl.value = '';
      return;
    }
    if (preferredEmployeeId) {
      const hasPreferred = list.some((item) => item.employeeId === preferredEmployeeId);
      if (hasPreferred) {
        savedSelectEl.value = preferredEmployeeId;
        return;
      }
    }
    savedSelectEl.value = list[0].employeeId;
  }

  function renderSavedPreview(record) {
    if (!savedPreviewEl) return;
    if (!record) {
      savedPreviewEl.innerHTML = '<div class="payroll-template-empty">尚未儲存薪資表</div>';
      return;
    }
    const rowsHtml = buildPreviewRows(record);
    savedPreviewEl.innerHTML = `
      <div class="payroll-sheet-preview">
        <div class="payroll-sheet__header">
          <div class="payroll-sheet__company">足達貨運公司</div>
          <div class="payroll-sheet__period">${escapeHtml(formatSavedPeriod(record))}</div>
          <div class="payroll-sheet__employee">${escapeHtml(record.employeeName || record.employeeId)}</div>
        </div>
        <table class="payroll-table">
          <colgroup>
            <col class="payroll-col-label">
            <col class="payroll-col-amount">
            <col class="payroll-col-label">
            <col class="payroll-col-amount">
            <col class="payroll-col-note">
          </colgroup>
          <thead>
            <tr>
              <th colspan="2">支出項目</th>
              <th colspan="2">收入項目</th>
              <th>備註</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
            <tr class="payroll-total-row">
              <td class="payroll-total-label">合計</td>
              <td class="payroll-total-value">$ ${formatNumber(record.expenseTotal)}</td>
              <td class="payroll-total-label">合計</td>
              <td class="payroll-total-value">$ ${formatNumber(record.incomeTotal)}</td>
            </tr>
            <tr class="payroll-net-row">
              <td class="payroll-total-label" colspan="2">收入－支出</td>
              <td class="payroll-net-total" colspan="2">$ ${formatNumber(record.net)}</td>
            </tr>
          </tbody>
        </table>
      </div>
    `;
  }

  function buildPreviewRows(record) {
    const rows = [];
    for (let i = 0; i < ROW_COUNT; i += 1) {
      const expense = record.expenses[i] || { label: '', amount: '' };
      const income = record.incomes[i] || { label: '', amount: '' };
      if (i === 0) {
        rows.push(`
          <tr>
            <td class="payroll-cell-label">${escapeHtml(expense.label || '')}</td>
            <td class="payroll-cell-amount"><span class="payroll-currency">$</span><span>${formatNumber(
              expense.amount
            )}</span></td>
            <td class="payroll-cell-label">${escapeHtml(income.label || '')}</td>
            <td class="payroll-cell-amount"><span class="payroll-currency">$</span><span>${formatNumber(
              income.amount
            )}</span></td>
            <td class="payroll-note-cell" rowspan="${ROW_COUNT + 2}">
              <div class="payroll-note-static">${formatNote(record.note)}</div>
            </td>
          </tr>
        `);
      } else {
        rows.push(`
          <tr>
            <td class="payroll-cell-label">${escapeHtml(expense.label || '')}</td>
            <td class="payroll-cell-amount"><span class="payroll-currency">$</span><span>${formatNumber(
              expense.amount
            )}</span></td>
            <td class="payroll-cell-label">${escapeHtml(income.label || '')}</td>
            <td class="payroll-cell-amount"><span class="payroll-currency">$</span><span>${formatNumber(
              income.amount
            )}</span></td>
          </tr>
        `);
      }
    }
    return rows.join('');
  }

  function formatSavedPeriod(record) {
    return `${record.year}年${record.month}月份薪資表`;
  }

  function applySavedRecord(record) {
    renderSavedPreview(record);
  }

  function adjustSavedMonth(step) {
    savedState.month += step;
    while (savedState.month <= 0) {
      savedState.month += 12;
      savedState.year -= 1;
    }
    while (savedState.month > 12) {
      savedState.month -= 12;
      savedState.year += 1;
    }
    refreshSavedRecords();
  }

  function updateSavedPeriodLabel() {
    if (!savedPeriodEl) return;
    savedPeriodEl.textContent = `${savedState.year}年${savedState.month}月薪資紀錄`;
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

  function buildDefaultEmployees() {
    if (!Array.isArray(window.PAYROLL_EMPLOYEE_LIST)) {
      return [];
    }
    return window.PAYROLL_EMPLOYEE_LIST
      .map((item, index) => {
        if (!item || typeof item !== 'object') {
          return null;
        }
        const id = normalizeEmployeeId(item.id ?? item.code ?? '', index);
        const name = typeof item.name === 'string' ? item.name.trim() : '';
        if (!id || !name) {
          return null;
        }
        return { id, name };
      })
      .filter(Boolean);
  }

  function normalizeEmployeeId(value, index = 0) {
    if (typeof value === 'string') {
      const trimmed = value.trim();
      if (trimmed) {
        return trimmed;
      }
    } else if (typeof value === 'number' && Number.isFinite(value)) {
      return String(value);
    }
    return `E${1000 + index}`;
  }

  function cloneEmployeeList(list) {
    if (!Array.isArray(list)) {
      return [];
    }
    return list.map((item) => ({
      id: item.id,
      name: item.name,
    }));
  }

  function applyEmployeeList(list) {
    state.employees = list && list.length ? list.slice() : [];
    if (!state.employees.length) {
      return;
    }
    populateEmployeeSelect();
    renderSavedRecords();
    applyFormula({ useAuto: false });
  }

  function fallbackToDefaultEmployees() {
    if (DEFAULT_EMPLOYEES.length) {
      applyEmployeeList(cloneEmployeeList(DEFAULT_EMPLOYEES));
    } else {
      applyEmployeeList([
        { id: 'E0001', name: '員工一' },
        { id: 'E0002', name: '員工二' },
      ]);
    }
    loadAutoData({ reapply: true });
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

  function persistPayrollRecord(record) {
    const payload = {
      roc_year: record.year,
      month: record.month,
      employee_id: record.employeeId,
      employee_name: record.employeeName,
      expenses: record.expenses,
      incomes: record.incomes,
      note: record.note,
    };
    return fetch(RECORDS_ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }).then((response) =>
      response
        .json()
        .catch(() => null)
        .then((data) => {
          if (!response.ok || !data?.ok) {
            throw new Error(data?.error || `HTTP ${response.status}`);
          }
          return data;
        })
    );
  }

  function serializeEntries(rows = []) {
    return Array.from({ length: ROW_COUNT }, (_, index) => {
      const item = rows[index] || {};
      const label = typeof item.label === 'string' ? item.label.trim() : '';
      const amountValue =
        item.amount === null || item.amount === undefined || item.amount === ''
          ? ''
          : String(item.amount).trim();
      return { label, amount: amountValue };
    });
  }

  function scrollToSavedCard() {
    if (!savedCardEl) {
      return;
    }
    const offset = savedCardEl.getBoundingClientRect().top + window.scrollY - 24;
    window.scrollTo({ top: offset, behavior: 'smooth' });
    savedCardEl.classList.add('payroll-card--highlight');
    window.setTimeout(() => {
      savedCardEl.classList.remove('payroll-card--highlight');
    }, 1200);
  }

  function syncSavedStateToCurrentPeriod() {
    if (!yearSelect || !monthSelect) {
      return;
    }
    const yearValue = Number(yearSelect.value);
    const monthValue = Number(monthSelect.value);
    if (Number.isFinite(yearValue) && yearValue > 0) {
      savedState.year = yearValue;
    }
    if (Number.isFinite(monthValue) && monthValue >= 1 && monthValue <= 12) {
      savedState.month = monthValue;
    }
  }

  function selectPrintEmployee(employeeId) {
    if (!employeeId) {
      return;
    }
    printSelection.add(employeeId);
    renderPrintPicker();
  }

  function closePrintPicker() {
    if (printPickerDropdown && !printPickerDropdown.hasAttribute('hidden')) {
      printPickerDropdown.setAttribute('hidden', 'hidden');
    }
  }

  function handlePrintPickerOutside(event) {
    if (
      !printPickerDropdown ||
      printPickerDropdown.hasAttribute('hidden') ||
      (printPickerDropdown.contains(event.target) || (printPickerToggle && printPickerToggle.contains(event.target)))
    ) {
      if (
        printPickerDropdown &&
        (printPickerDropdown.contains(event.target) || (printPickerToggle && printPickerToggle.contains(event.target)))
      ) {
        document.addEventListener('click', handlePrintPickerOutside, { once: true });
      }
      return;
    }
    closePrintPicker();
  }

  function restrictEmployeeList(list) {
    if (!DEFAULT_EMPLOYEES.length) {
      return list;
    }
    const byCode = new Map();
    const byName = new Map();
    list.forEach((item) => {
      if (item.id) {
        byCode.set(item.id, item);
      }
      const key = normalizeEmployeeName(item.name);
      if (key) {
        byName.set(key, item);
      }
    });
    const result = [];
    DEFAULT_EMPLOYEES.forEach((preset, index) => {
      if (!preset) {
        return;
      }
      const presetId = preset.id || normalizeEmployeeId('', index);
      const presetName = typeof preset.name === 'string' ? preset.name.trim() : '';
      let matched = null;
      if (presetId && byCode.has(presetId)) {
        matched = byCode.get(presetId);
      } else {
        const key = normalizeEmployeeName(presetName);
        if (key && byName.has(key)) {
          matched = byName.get(key);
        }
      }
      if (matched) {
        result.push({ id: matched.id, name: matched.name });
        return;
      }
      const fallbackId = normalizeEmployeeId(presetId || `E${1000 + index}`, index);
      const fallbackName = presetName || fallbackId;
      result.push({ id: fallbackId, name: fallbackName });
    });
    return result;
  }

  function normalizeEmployeeName(value) {
    if (typeof value !== 'string') {
      return '';
    }
    return value.replace(/\s+/g, '').toLowerCase();
  }

  function initToast() {
    const existing = document.querySelector('[data-payroll-toast]');
    if (existing) {
      return existing;
    }
    const el = document.createElement('div');
    el.className = 'payroll-toast';
    el.dataset.payrollToast = 'true';
    el.setAttribute('aria-live', 'polite');
    el.hidden = true;
    document.body.appendChild(el);
    return el;
  }

  function showMessage(type, text) {
    if (!toastRoot) {
      if (text) {
        console.log(`[${type}] ${text}`);
      }
      return;
    }
    if (!text) {
      toastRoot.hidden = true;
      toastRoot.textContent = '';
      return;
    }
    toastRoot.textContent = text;
    toastRoot.dataset.type = type;
    toastRoot.hidden = false;
    window.clearTimeout(showMessage.timer);
    showMessage.timer = window.setTimeout(() => {
      toastRoot.hidden = true;
    }, 3000);
  }
})();
