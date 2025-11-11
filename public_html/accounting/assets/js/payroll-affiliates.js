/* global fetch */
(function () {
  'use strict';

  const ROW_COUNT = 8;
  const MONTH_WINDOWS = [
    { start: 1, end: 2, label: '1-2 月' },
    { start: 3, end: 4, label: '3-4 月' },
    { start: 5, end: 6, label: '5-6 月' },
    { start: 7, end: 8, label: '7-8 月' },
    { start: 9, end: 10, label: '9-10 月' },
    { start: 11, end: 12, label: '11-12 月' },
  ];
  const EMPLOYEE_ENDPOINT = '../api/master-data/master_employees.php';
  const VEHICLE_ENDPOINT = '../api/master-data/master_vehicles.php';
  const ALLOWED_DRIVERS = ['李正源', '蕭添丁', '八達', '張逢升', '阮明昭'];

  const expenseLabelEls = document.querySelectorAll('[data-affiliate-expense-label]');
  const expenseAmountEls = document.querySelectorAll('[data-affiliate-expense-amount]');
  const incomeLabelEls = document.querySelectorAll('[data-affiliate-income-label]');
  const incomeAmountEls = document.querySelectorAll('[data-affiliate-income-amount]');
  const expenseTotalEl = document.querySelector('[data-affiliate-expense-total]');
  const incomeTotalEl = document.querySelector('[data-affiliate-income-total]');
  const netTotalEl = document.querySelector('[data-affiliate-net]');
  const employeeSelect = document.querySelector('[data-affiliate-select="employee"]');
  const yearSelect = document.querySelector('[data-affiliate-select="year"]');
  const rangeSelect = document.querySelector('[data-affiliate-select="range"]');
  const driverHeadingEl = document.querySelector('[data-affiliate-driver-heading]');
  const periodHeadingEl = document.querySelector('[data-affiliate-period-heading]');
  const carDisplayEl = document.querySelector('[data-affiliate-car]');
  const downloadBtn = document.querySelector('[data-affiliate-download]');
  const addBtn = document.querySelector('[data-affiliate-add]');
  const savedListEl = document.querySelector('[data-affiliate-saved]');
  const printStackEl = document.querySelector('[data-affiliate-print-stack]');
  const uploadInput = document.querySelector('[data-affiliate-upload]');

  if (!employeeSelect || !yearSelect || !rangeSelect) {
    return;
  }

  const state = {
    employees: [],
    expenses: Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' })),
    incomes: Array.from({ length: ROW_COUNT }, () => ({ label: '', amount: '' })),
  };
  const vehicleMap = {};
  const savedEntries = [];
  let editingIndex = -1;

  init();

  function init() {
    populateYearMonth();
    bindEvents();
    renderRows();
    recalcTotals();
    loadEmployees();
    renderSavedList();
  }

  function populateYearMonth() {
    const now = new Date();
    const baseYear = Number(document.body.dataset.rocYear) || now.getFullYear() - 1911;
    const baseMonth = Number(document.body.dataset.month) || now.getMonth() + 1;
    const defaultYear = baseYear;
    const defaultMonth = baseMonth - (baseMonth % 2 === 0 ? 1 : 0);

    yearSelect.innerHTML = '';
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

    rangeSelect.innerHTML = '';
    MONTH_WINDOWS.forEach((window) => {
      const option = document.createElement('option');
      option.value = String(window.start);
      option.textContent = window.label;
      if (window.start === defaultMonth) {
        option.selected = true;
      }
      rangeSelect.appendChild(option);
    });

    updatePeriodHeading();
  }

  function loadEmployees() {
    fetch(`${VEHICLE_ENDPOINT}?action=list`, { credentials: 'same-origin' })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((payload) => {
        const rows = Array.isArray(payload?.data) ? payload.data : [];
        const employees = [];
        const driverPlateMap = {};
        const allowedNormalized = ALLOWED_DRIVERS.map((name) => normalizeName(name));
        rows.forEach((row, index) => {
          const name = (row?.driver || '').trim();
          if (!name) return;
          const normalized = normalizeName(name);
          if (!allowedNormalized.includes(normalized)) return;
          if (employees.some((emp) => normalizeName(emp.name) === normalized)) {
            if (!driverPlateMap[normalized]) {
              driverPlateMap[normalized] = row.license || row.plate || '';
            }
            return;
          }
          const code = row.code || row.id || `DRV${index}`;
          const plate = row.license || row.plate || '';
          employees.push({ id: code, name, plate });
          if (normalized && plate) {
            driverPlateMap[normalized] = plate;
          }
        });
        ALLOWED_DRIVERS.forEach((name, index) => {
          if (!employees.some((emp) => emp.name === name)) {
            employees.push({ id: `DRV${index}`, name, plate: '' });
          }
        });
        state.employees = employees;
        Object.keys(vehicleMap).forEach((key) => delete vehicleMap[key]);
        Object.assign(vehicleMap, driverPlateMap);
        populateEmployeeSelect();
      })
      .catch((error) => {
        console.error('load affiliate drivers failed', error);
        state.employees = ALLOWED_DRIVERS.map((name, index) => ({
          id: `DRV${index}`,
          name,
          plate: '',
        }));
        Object.keys(vehicleMap).forEach((key) => delete vehicleMap[key]);
        populateEmployeeSelect();
      });
  }

  function populateEmployeeSelect() {
    employeeSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- 選擇司機 --';
    employeeSelect.appendChild(placeholder);
    state.employees.forEach((employee) => {
      const option = document.createElement('option');
      option.value = employee.id;
      option.textContent = employee.name || employee.id;
      employeeSelect.appendChild(option);
    });
    updateEmployeeDisplay(getSelectedEmployee());
  }

  function bindEvents() {
    employeeSelect.addEventListener('change', () => {
      updateEmployeeDisplay(getSelectedEmployee());
    });
    yearSelect.addEventListener('change', () => {
      updatePeriodHeading();
    });
    rangeSelect.addEventListener('change', () => {
      updatePeriodHeading();
    });

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
    if (addBtn) {
      addBtn.addEventListener('click', handleAddEntry);
    }
    if (uploadInput) {
      uploadInput.addEventListener('change', handleUploadFile);
    }
    if (downloadBtn) {
      downloadBtn.addEventListener('click', handleDownload);
    }
    if (savedListEl) {
      savedListEl.addEventListener('click', handleSavedListClick);
    }
  }

  function getSelectedEmployee() {
    const id = employeeSelect.value;
    if (!id) {
      return null;
    }
    return state.employees.find((emp) => emp.id === id) || null;
  }

  function updateEmployeeDisplay(employee) {
    if (driverHeadingEl) {
      driverHeadingEl.textContent = employee ? employee.name || '—' : '—';
    }
    updateCarDisplay(employee);
    updatePeriodHeading();
  }

  function updateCarDisplay(employee) {
    if (!carDisplayEl) return;
    if (!employee) {
      carDisplayEl.textContent = '—';
      return;
    }
    const key = normalizeName(employee.name || '');
    carDisplayEl.textContent = key && vehicleMap[key] ? vehicleMap[key] : '—';
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
    updatePeriodHeading();
  }

  function recalcTotals() {
    const expenseTotal = sumAmounts(state.expenses);
    const incomeTotal = sumAmounts(state.incomes);
    if (expenseTotalEl) {
      expenseTotalEl.textContent = `$ ${formatNumber(expenseTotal)}`;
    }
    if (incomeTotalEl) {
      incomeTotalEl.textContent = `$ ${formatNumber(incomeTotal)}`;
    }
    if (netTotalEl) {
      netTotalEl.textContent = `$ ${formatNumber(expenseTotal - incomeTotal)}`;
    }
  }

  function sumAmounts(items) {
    return items.reduce((sum, item) => sum + toNumber(item.amount), 0);
  }

  function toNumber(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
  }

  function buildTwoMonthLabel(year, startMonth, endMonth) {
    if (!year) return '';
    if (startMonth === 12) {
      return `${year} 年 12 月 - ${year + 1} 年 1 月`;
    }
    return `${year} 年 ${startMonth} - ${endMonth} 月`;
  }

  function formatNumber(value) {
    return Number(value).toLocaleString('zh-TW', { maximumFractionDigits: 0 });
  }

  function normalizeName(name) {
    return (name || '').trim().replace(/\s+/g, '').toLowerCase();
  }

  function handleAddEntry() {
    const employee = getSelectedEmployee();
    if (!employee) {
      window.alert('請先選擇司機');
      return;
    }
    const entry = collectSheetData(employee);
    entry.reconciliation = entry.reconciliation || '';
    entry.form = entry.form || '';
    entry.note = entry.note || '';
    if (editingIndex >= 0) {
      savedEntries[editingIndex] = entry;
      editingIndex = -1;
      window.alert('已更新靠行明細');
    } else {
      savedEntries.push(entry);
      window.alert('已新增靠行明細');
    }
    renderSavedList();
  }

  function renderSavedList() {
    if (!savedListEl) return;
    if (!savedEntries.length) {
      savedListEl.innerHTML = '<div class="payroll-template-empty">尚未新增明細</div>';
      return;
    }
    const rows = savedEntries
      .map((entry, index) => {
        return `
          <tr>
            <td>${escapeHtml(entry.employee.id || '')}</td>
            <td>${escapeHtml(entry.employee.name || '')}</td>
            <td>$ ${formatNumber(entry.net)}</td>
            <td>${escapeHtml(entry.reconciliation || '')}</td>
            <td>${escapeHtml(entry.form || '')}</td>
            <td>${escapeHtml(entry.note || '')}</td>
            <td>
              <button type="button" class="btn btn--ghost btn--small" data-entry-edit="${index}">編輯</button>
              <button type="button" class="btn btn--danger-soft btn--small" data-entry-delete="${index}">刪除</button>
            </td>
          </tr>
        `;
      })
      .join('');
    savedListEl.innerHTML = `
      <table class="affiliate-list-table">
        <thead>
          <tr>
            <th>代號</th>
            <th>司機</th>
            <th>本期費用</th>
            <th>對帳</th>
            <th>對應表單</th>
            <th>備註</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  }

  function handleSavedListClick(event) {
    const editBtn = event.target.closest('[data-entry-edit]');
    if (editBtn) {
      const index = Number(editBtn.dataset.entryEdit);
      if (Number.isInteger(index) && savedEntries[index]) {
        loadEntry(savedEntries[index], index);
      }
      return;
    }
    const deleteBtn = event.target.closest('[data-entry-delete]');
    if (deleteBtn) {
      const index = Number(deleteBtn.dataset.entryDelete);
      if (Number.isInteger(index) && savedEntries[index]) {
        if (window.confirm('確定要刪除這筆靠行明細嗎？')) {
          savedEntries.splice(index, 1);
          editingIndex = -1;
          renderSavedList();
        }
      }
    }
  }

  function loadEntry(entry, index) {
    editingIndex = index;
    if (employeeSelect) {
      employeeSelect.value = entry.employee.id || '';
    }
    if (yearSelect) {
      yearSelect.value = String(entry.year);
    }
    if (rangeSelect) {
      rangeSelect.value = String(entry.startMonth);
    }
    updateEmployeeDisplay(getSelectedEmployee());
    state.expenses = entry.expenses.map((item) => ({ label: item.label || '', amount: item.amount || '' }));
    state.incomes = entry.incomes.map((item) => ({ label: item.label || '', amount: item.amount || '' }));
    renderRows();
    recalcTotals();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function updatePeriodHeading() {
    if (!periodHeadingEl) return;
    const year = Number(yearSelect.value) || 0;
    const startMonth = Number(rangeSelect.value) || 1;
    const windowDef = MONTH_WINDOWS.find((item) => item.start === startMonth);
    const endMonth = windowDef ? windowDef.end : startMonth + 1;
    const label = buildTwoMonthLabel(year, startMonth, endMonth) || '—';
    periodHeadingEl.textContent = label;
  }

  function collectSheetData(employee) {
    const expenses = state.expenses.map((item) => ({ ...item }));
    const incomes = state.incomes.map((item) => ({ ...item }));
    const expenseTotal = sumAmounts(expenses);
    const incomeTotal = sumAmounts(incomes);
    const year = Number(yearSelect.value) || 0;
    const startMonth = Number(rangeSelect.value) || 1;
    const windowDef = MONTH_WINDOWS.find((item) => item.start === startMonth);
    const endMonth = windowDef ? windowDef.end : startMonth + 1;
    return {
      employee: { ...employee },
      year,
      startMonth,
      endMonth,
      car: carDisplayEl ? carDisplayEl.textContent : '',
      expenses,
      incomes,
      expenseTotal,
      incomeTotal,
      net: expenseTotal - incomeTotal,
      savedAt: new Date().toISOString(),
    };
  }

  function handleDownload() {
    const employee = getSelectedEmployee();
    if (!employee) {
      window.alert('請先選擇司機');
      return;
    }
    const data = collectSheetData(employee);
    preparePrintSheet(data);
    window.print();
  }

  function preparePrintSheet(entry) {
    if (!printStackEl) return;
    const label = buildTwoMonthLabel(entry.year, entry.startMonth, entry.endMonth);
    const sheet = document.createElement('section');
    sheet.className = 'affiliate-print-sheet';
    sheet.innerHTML = `
      <table class="payroll-table affiliate-table affiliate-table--print">
        <thead>
          <tr>
            <th colspan="4" class="affiliate-heading affiliate-heading--center">${escapeHtml(
              entry.employee.name || ''
            )}</th>
          </tr>
          <tr>
            <th colspan="4" class="affiliate-heading affiliate-heading--center">${escapeHtml(label || '')}</th>
          </tr>
          <tr>
            <th colspan="3"></th>
            <th class="affiliate-heading affiliate-heading--right">車號：${escapeHtml(entry.car || '—')}</th>
          </tr>
          <tr>
            <th>支出項目</th>
            <th>金額</th>
            <th>收入項目</th>
            <th>金額</th>
          </tr>
        </thead>
        <tbody>
          ${buildPrintRows(entry.expenses, entry.incomes)}
          <tr class="payroll-total-row">
            <td class="payroll-total-label">支出合計</td>
            <td class="payroll-total-value"><span class="payroll-currency">$</span>${formatNumber(entry.expenseTotal)}</td>
            <td class="payroll-total-label">收入合計</td>
            <td class="payroll-total-value"><span class="payroll-currency">$</span>${formatNumber(entry.incomeTotal)}</td>
          </tr>
          <tr class="payroll-net-row">
            <td class="payroll-total-label" colspan="3">本期費用（支出－收入）</td>
            <td class="payroll-net-total">$ ${formatNumber(entry.net)}</td>
          </tr>
        </tbody>
      </table>
    `;
    printStackEl.innerHTML = '';
    printStackEl.appendChild(sheet);
  }

  function buildPrintRows(expenses, incomes) {
    const rows = [];
    for (let i = 0; i < ROW_COUNT; i += 1) {
      const expense = expenses[i] || { label: '', amount: '' };
      const income = incomes[i] || { label: '', amount: '' };
      rows.push(`
        <tr>
          <td class="payroll-cell-label">${escapeHtml(expense.label || '')}</td>
          <td class="payroll-cell-amount"><span class="payroll-currency">$</span>${displayAmount(expense.amount)}</td>
          <td class="payroll-cell-label">${escapeHtml(income.label || '')}</td>
          <td class="payroll-cell-amount"><span class="payroll-currency">$</span>${displayAmount(income.amount)}</td>
        </tr>
      `);
    }
    return rows.join('');
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

  function handleUploadFile(event) {
    const file = event.target?.files?.[0];
    if (!file) {
      return;
    }
    if (!/\.xlsx$/i.test(file.name)) {
      window.alert('僅支援上傳 XLSX 檔案');
      uploadInput.value = '';
      return;
    }
    window.alert(`檔案 ${file.name} 已選擇，待後端解析功能完成後再匯入。`);
    uploadInput.value = '';
  }

  function displayAmount(value) {
    if (value === null || value === undefined) {
      return '';
    }
    const text = String(value).trim();
    if (text === '') {
      return '';
    }
    const num = Number(text);
    if (!Number.isFinite(num)) {
      return text;
    }
    return formatNumber(num);
  }
})();
