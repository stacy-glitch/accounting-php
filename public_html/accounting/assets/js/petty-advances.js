(function () {
  'use strict';

  const ADVANCES_ENDPOINT = '../api/petty-cash/advances.php';
  const ADVANCE_LINK_ENDPOINT = '../api/petty-cash/advance_links.php';
  const CODE_SOURCES = [
    {
      endpoint: '../api/master-data/master_codes.php?action=list',
      extract: (row) => {
        const code = ((row && (row.code || row.value)) || '').trim();
        const label = ((row && (row.label || row.name)) || '').trim();
        if (!code || !label) {
          return null;
        }
        const normalized = ((row && row.normalized_code) || '').trim() || normalizeCode(code);
        return {
          value: code,
          label,
          normalized,
        };
      },
    },
  ];

  const root = document.body;
  const searchForm = document.querySelector('[data-advance-search-form]');
  const codeInput = document.querySelector('[data-advance-search-code]');
  const codeDatalist = document.getElementById('advance-code-list');
  const searchMessage = document.querySelector('[data-search-message]');
  const searchResultContainer = document.querySelector('[data-search-result]');
  const searchResultBody = document.querySelector('[data-search-result-body]');
  const searchSummary = document.querySelector('[data-search-summary]');
  const searchTotal = document.querySelector('[data-search-total]');
  const monthTitle = document.querySelector('[data-month-title]');
  const monthRows = document.querySelector('[data-month-rows]');
  const monthFooter = document.querySelector('[data-month-footer]');
  const monthTotal = document.querySelector('[data-month-total]');
  const monthNavButtons = document.querySelectorAll('[data-month-nav]');

  const state = {
    year: parseInt(root.dataset.initialYear || String(new Date().getFullYear()), 10),
    month: parseInt(root.dataset.initialMonth || String(new Date().getMonth() + 1), 10),
    loadingSearch: false,
    loadingMonth: false,
    lastSearchCode: '',
  };

  const codeLookup = {
    byNormalized: new Map(),
    byDisplay: new Map(),
  };

  init();

  function init() {
    bindEvents();
    loadCodeDatalist();
    updateMonthTitle();
    loadMonthData();
  }

  function bindEvents() {
    if (searchForm) {
      searchForm.addEventListener('submit', handleSearchSubmit);
    }

    if (codeInput) {
      codeInput.addEventListener('input', handleCodeInputChange);
      codeInput.addEventListener('blur', handleCodeInputBlur);
    }

    monthNavButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const dir = button.dataset.monthNav;
        if (dir === 'prev') {
          goToPreviousMonth();
        } else if (dir === 'next') {
          goToNextMonth();
        }
      });
    });

    document.addEventListener('change', handleLinkSelectChange, true);
  }

  function handleSearchSubmit(event) {
    event.preventDefault();
    if (state.loadingSearch) {
      return;
    }
    const code = resolveCodeValue();
    if (!code) {
      showSearchMessage('error', '請輸入代號');
      return;
    }
    performCodeSearch(code);
  }

  function handleCodeInputChange() {
    const raw = (codeInput.value || '').trim();
    if (!raw) {
      codeInput.dataset.codeValue = '';
      return;
    }
    const lookup = codeLookup.byDisplay.get(raw);
    if (lookup) {
      codeInput.dataset.codeValue = lookup.value;
      return;
    }
    if (raw.includes('—')) {
      codeInput.dataset.codeValue = raw.split('—')[0].trim();
      return;
    }
    const normalized = normalizeCode(raw);
    const entry = codeLookup.byNormalized.get(normalized);
    if (entry) {
      codeInput.dataset.codeValue = entry.value;
    } else {
      codeInput.dataset.codeValue = raw;
    }
  }

  function handleCodeInputBlur() {
    if (!codeInput) return;
    const raw = (codeInput.value || '').trim();
    if (!raw) {
      codeInput.dataset.codeValue = '';
      codeInput.value = '';
      return;
    }
    const entry = codeLookup.byDisplay.get(raw);
    if (entry) {
      codeInput.value = `${entry.value} — ${entry.label}`;
      codeInput.dataset.codeValue = entry.value;
      return;
    }
    if (raw.includes('—')) {
      const parts = raw.split('—');
      const code = parts[0].trim();
      const normalized = normalizeCode(code);
      const lookup = codeLookup.byNormalized.get(normalized);
      if (lookup) {
        codeInput.value = `${lookup.value} — ${lookup.label}`;
        codeInput.dataset.codeValue = lookup.value;
      } else {
        codeInput.value = code;
        codeInput.dataset.codeValue = code;
      }
      return;
    }
    const normalized = normalizeCode(raw);
    const lookup = codeLookup.byNormalized.get(normalized);
    if (lookup) {
      codeInput.value = `${lookup.value} — ${lookup.label}`;
      codeInput.dataset.codeValue = lookup.value;
    } else {
      codeInput.value = raw;
      codeInput.dataset.codeValue = raw;
    }
  }

  function resolveCodeValue() {
    if (!codeInput) return '';
    const datasetValue = (codeInput.dataset.codeValue || '').trim();
    if (datasetValue) {
      return datasetValue;
    }
    const raw = (codeInput.value || '').trim();
    if (!raw) {
      return '';
    }
    if (raw.includes('—')) {
      return raw.split('—')[0].trim();
    }
    return raw;
  }

  function performCodeSearch(code) {
    state.loadingSearch = true;
    state.lastSearchCode = code;
    showSearchMessage('info', `代號 ${code} 查詢中…`, true);
    const url = new URL(ADVANCES_ENDPOINT, window.location.href);
    url.searchParams.set('code', code);
    fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(assertResponseOk)
      .then((payload) => payload?.data || {})
      .then((data) => {
        renderSearchResults(data);
      })
      .catch((error) => {
        showSearchMessage('error', error.message || '查詢失敗');
        renderSearchResults({ code, outstanding: [], total_remaining: 0 });
      })
      .finally(() => {
        state.loadingSearch = false;
      });
  }

  function renderSearchResults(data) {
    const items = Array.isArray(data.outstanding) ? data.outstanding : [];
    const code = data.code || resolveCodeValue();
    if (items.length === 0) {
      showSearchMessage('info', `代號 ${code} 目前沒有未銷帳的代墊款。`);
    } else {
      showSearchMessage('success', `代號 ${code} 尚有 ${items.length} 筆未銷帳代墊款。`);
    }

    if (searchResultContainer) {
      searchResultContainer.hidden = false;
    }

    if (searchSummary) {
      searchSummary.hidden = false;
    }
    if (searchTotal) {
      searchTotal.textContent = formatCurrency(data.total_remaining || 0);
    }

    if (!searchResultBody) return;
    if (!items.length) {
      searchResultBody.innerHTML = `<tr><td colspan="6" class="table-empty">目前沒有未銷帳代墊款</td></tr>`;
      return;
    }

    const rows = items
      .map((item) => {
        const remaining = Number(item.remaining_amount || 0);
        return `
          <tr data-context="search" data-advance-id="${escapeHtml(String(item.id || ''))}">
            <td>${escapeHtml(formatCodeDisplay(item.code))}</td>
            <td>${formatRocDate(item.entry_date)}</td>
            <td>${formatRocDate(item.transaction_date)}</td>
            <td>${formatCurrency(item.display_amount != null ? item.display_amount : remaining)}</td>
            <td class="advance-link-cell">${renderLinkCell(item, 'search')}</td>
            <td>${escapeHtml(item.note || '')}</td>
          </tr>
        `;
      })
      .join('');
    searchResultBody.innerHTML = rows;
  }

  function showSearchMessage(type, text, sticky = false) {
    if (!searchMessage) return;
    const classMap = {
      success: 'notice--success',
      error: 'notice--error',
      info: 'notice--info',
    };
    searchMessage.className = `notice ${classMap[type] || ''}`.trim();
    searchMessage.textContent = text;
    searchMessage.hidden = false;
    if (!sticky) {
      window.setTimeout(() => {
        if (searchMessage) {
          searchMessage.hidden = true;
        }
      }, 5000);
    }
  }

  function updateMonthTitle() {
    if (!monthTitle) return;
    monthTitle.textContent = formatRocMonthTitle(state.year, state.month);
  }

  function loadMonthData() {
    state.loadingMonth = true;
    if (monthRows) {
      monthRows.innerHTML = '<tr><td colspan="6" class="table-empty">資料載入中…</td></tr>';
    }
    const url = new URL(ADVANCES_ENDPOINT, window.location.href);
    url.searchParams.set('year', String(state.year));
    url.searchParams.set('month', String(state.month));
    fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(assertResponseOk)
      .then((payload) => payload?.data || {})
      .then((data) => {
        renderMonthData(data);
      })
      .catch((error) => {
        renderMonthError(error.message || '資料載入失敗');
      })
      .finally(() => {
        state.loadingMonth = false;
      });
  }

  function renderMonthData(data) {
    const items = Array.isArray(data.entries) ? data.entries : [];
    if (!monthRows) return;
    if (!items.length) {
      monthRows.innerHTML = '<tr><td colspan="6" class="table-empty">本月份沒有未銷帳代墊款</td></tr>';
    } else {
      const rows = items
        .map((item) => {
          const remaining = Number(item.remaining_amount || 0);
          return `
            <tr data-context="month" data-advance-id="${escapeHtml(String(item.id || ''))}">
              <td>${escapeHtml(formatCodeDisplay(item.code))}</td>
              <td>${formatRocDate(item.entry_date)}</td>
              <td>${formatRocDate(item.transaction_date)}</td>
              <td>${formatCurrency(item.display_amount != null ? item.display_amount : remaining)}</td>
              <td class="advance-link-cell">${renderLinkCell(item, 'month')}</td>
              <td>${escapeHtml(item.note || '')}</td>
            </tr>
          `;
        })
        .join('');
      monthRows.innerHTML = rows;
    }
    if (monthFooter) {
      monthFooter.hidden = false;
    }
    if (monthTotal) {
      monthTotal.textContent = formatCurrency(data.total_remaining || 0);
    }
  }

  function renderMonthError(message) {
    if (monthRows) {
      monthRows.innerHTML = `<tr><td colspan="6" class="table-empty">${escapeHtml(message)}</td></tr>`;
    }
    if (monthFooter) {
      monthFooter.hidden = true;
    }
  }

  function handleLinkSelectChange(event) {
    const select = event.target.closest('select[data-link-select]');
    if (!select) {
      return;
    }

    const context = select.dataset.context || '';
    const previousValue = select.dataset.prevValue || '';
    const value = select.value;
    const advanceId = Number(select.dataset.advanceId || 0);
    const linkId = Number(select.dataset.linkId || 0);
    const remaining = Number(select.dataset.remaining || 0);

    const revert = (val) => {
      select.value = val;
      select.disabled = false;
    };

    select.disabled = true;

    if (value === 'revenue') {
      if (linkId) {
        select.disabled = false;
        select.dataset.prevValue = 'revenue';
        return;
      }
      if (!advanceId || remaining <= 0) {
        revert(previousValue);
        return;
      }
      submitAdvanceLink({
        control: select,
        advanceId,
        amount: remaining,
        context,
      });
      return;
    }

    if (value === '') {
      if (!linkId) {
        select.dataset.prevValue = '';
        select.disabled = false;
        return;
      }
      submitAdvanceLinkCancellation({
        control: select,
        linkId,
        context,
      });
      return;
    }

    alert('此表單尚未開放自動對應，請選擇其他表單。');
    revert(previousValue);
  }

  function submitAdvanceLink({ control, advanceId, amount, context }) {
    sendJsonRequest(ADVANCE_LINK_ENDPOINT, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        advance_id: advanceId,
        amount,
      }),
    })
      .then((payload) => {
        if (context === 'search' && payload?.message) {
          showSearchMessage('success', payload.message, true);
        }
        control.dataset.prevValue = 'revenue';
        refreshAfterLink();
      })
      .catch((error) => {
        const message = (error && error.message) || '併入營收失敗';
        if (context === 'search') {
          showSearchMessage('error', message, true);
        } else {
          alert(message);
        }
        control.value = control.dataset.prevValue || '';
      })
      .finally(() => {
        control.disabled = false;
      });
  }

  function submitAdvanceLinkCancellation({ control, linkId, context }) {
    const url = new URL(ADVANCE_LINK_ENDPOINT, window.location.href);
    url.searchParams.set('id', String(linkId));
    sendJsonRequest(url.toString(), {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    })
      .then((payload) => {
        if (context === 'search' && payload?.message) {
          showSearchMessage('success', payload.message, true);
        }
        control.dataset.prevValue = '';
        delete control.dataset.linkId;
        refreshAfterLink();
      })
      .catch((error) => {
        const message = (error && error.message) || '取消對應失敗';
        if (context === 'search') {
          showSearchMessage('error', message, true);
        } else {
          alert(message);
        }
        control.value = 'revenue';
      })
      .finally(() => {
        control.disabled = false;
      });
  }

  function refreshAfterLink() {
    loadMonthData();
    if (state.lastSearchCode && !state.loadingSearch) {
      performCodeSearch(state.lastSearchCode);
    }
  }

  function renderLinkCell(item, context) {
    const links = Array.isArray(item.links) ? item.links : [];
    const activeLink = links.find((link) => (link.status || 'linked') === 'linked') || null;
    const advanceId = escapeHtml(String(item.id || ''));
    const contextValue = escapeHtml(context);
    const remaining = Number(item.remaining_amount || 0);

    const options = [
      { value: '', label: '— 選擇表單 —' },
      { value: 'revenue', label: '營收報表' },
      { value: 'partner', label: '靠行表' },
      { value: 'payroll', label: '薪資表' },
    ];

    const currentValue = activeLink ? 'revenue' : '';
    const attributes = [
      `data-link-select`,
      `data-context="${contextValue}"`,
      `data-advance-id="${advanceId}"`,
      `data-prev-value="${currentValue}"`,
      `data-remaining="${escapeHtml(String(remaining))}"`,
    ];
    if (activeLink) {
      attributes.push(`data-link-id="${escapeHtml(String(activeLink.id || ''))}"`);
    }

    if (!activeLink && remaining <= 0) {
      return '<span class="advance-link-empty">—</span>';
    }

    const optionsHtml = options
      .map((option) => {
        const selected = option.value === currentValue ? ' selected' : '';
        const disabled = option.value !== '' && option.value !== 'revenue' ? ' disabled' : '';
        return `<option value="${option.value}"${selected}${disabled}>${option.label}</option>`;
      })
      .join('');

    return `<select class="advance-link-select" ${attributes.join(' ')}>${optionsHtml}</select>`;
  }

  function sendJsonRequest(url, options) {
    return fetch(url, options)
      .then((response) =>
        response
          .json()
          .catch(() => ({}))
          .then((payload) => {
            if (!response.ok || (payload && payload.ok === false)) {
              const message =
                (payload && payload.error) || response.statusText || `HTTP ${response.status}`;
              const error = new Error(message);
              error.response = response;
              error.payload = payload;
              throw error;
            }
            return payload;
          })
      );
  }

  function goToPreviousMonth() {
    let { year, month } = state;
    month -= 1;
    if (month < 1) {
      month = 12;
      year -= 1;
    }
    state.year = year;
    state.month = month;
    updateMonthTitle();
    loadMonthData();
  }

  function goToNextMonth() {
    let { year, month } = state;
    month += 1;
    if (month > 12) {
      month = 1;
      year += 1;
    }
    state.year = year;
    state.month = month;
    updateMonthTitle();
    loadMonthData();
  }

  function loadCodeDatalist() {
    if (!codeDatalist) return;
    const tasks = CODE_SOURCES.map((source) =>
      fetch(source.endpoint, { credentials: 'same-origin' })
        .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))))
        .then((payload) => {
          const rows = Array.isArray(payload?.data) ? payload.data : [];
          return rows
            .map(source.extract)
            .filter((item) => item && item.value);
        })
        .catch(() => [])
    );

    Promise.all(tasks)
      .then((results) => {
        const merged = results.flat();
        const map = new Map();
        merged.forEach((item) => {
          if (!item || !item.value) return;
          const value = String(item.value).trim();
          if (!value) return;
          const normalized = item.normalized || normalizeCode(value);
          if (!normalized || map.has(normalized)) {
            return;
          }
          map.set(normalized, { value, label: item.label || '' });
        });
        const cleaned = Array.from(map.values()).sort((a, b) => {
          const an = Number(a.value);
          const bn = Number(b.value);
          if (Number.isFinite(an) && Number.isFinite(bn)) {
            return an - bn;
          }
          return a.value.localeCompare(b.value, 'zh-Hant');
        });
        populateDatalist(codeDatalist, cleaned);
        storeCodeLookup(cleaned);
      })
      .catch(() => {});
  }

  function populateDatalist(datalist, items) {
    if (!datalist) return;
    datalist.innerHTML = items
      .map((item) => {
        const value = String(item.value || '').trim();
        const label = item.label ? String(item.label).trim() : '';
        const combined = label ? `${value} — ${label}` : value;
        return `<option value="${escapeHtml(combined)}" data-code="${escapeHtml(value)}"></option>`;
      })
      .join('');
  }

  function storeCodeLookup(items) {
    codeLookup.byNormalized.clear();
    codeLookup.byDisplay.clear();
    items.forEach((item) => {
      const value = String(item.value || '').trim();
      if (!value) return;
      const label = item.label ? String(item.label).trim() : '';
      const display = label ? `${value} — ${label}` : value;
      const normalized = normalizeCode(value);
      codeLookup.byNormalized.set(normalized, { value, label, display });
      codeLookup.byDisplay.set(display, { value, label, display });
    });
  }

  function normalizeCode(code) {
    const trimmed = String(code == null ? '' : code).trim();
    if (!trimmed) {
      return '';
    }
    return trimmed.replace(/\s+/g, '').toUpperCase();
  }

  function assertResponseOk(response) {
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  }

  function formatCurrency(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) {
      return '0';
    }
    return Math.round(num).toLocaleString('zh-TW', { minimumFractionDigits: 0 });
  }

  function formatRocDate(value) {
    if (!value) return '';
    const date = toDate(value);
    if (!date) return escapeHtml(value);
    const rocYear = date.getFullYear() - 1911;
    const month = date.getMonth() + 1;
    const day = date.getDate();
    if (rocYear <= 0) {
      return `${date.getFullYear()}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }
    return `${rocYear}年${month}月${day}日`;
  }

  function toDate(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
      return new Date(value.getFullYear(), value.getMonth(), value.getDate());
    }
    const text = String(value || '').trim();
    if (!text) return null;
    const isoMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (isoMatch) {
      const year = Number(isoMatch[1]);
      const month = Number(isoMatch[2]);
      const day = Number(isoMatch[3]);
      if (Number.isFinite(year) && Number.isFinite(month) && Number.isFinite(day)) {
        return new Date(year, month - 1, day);
      }
    }
    return null;
  }

  function formatCodeDisplay(code) {
    if (code === null || code === undefined) {
      return '';
    }
    const text = String(code).trim();
    if (!text) {
      return '';
    }
    const numericMatch = /^-?\d+(?:\.\d+)?$/.exec(text);
    if (!numericMatch) {
      return text;
    }
    if (text.includes('.')) {
      const [intPart, decimalPart = ''] = text.split('.');
      if (/^0+$/.test(decimalPart)) {
        return intPart;
      }
      return `${intPart}.${decimalPart.replace(/0+$/, '')}`;
    }
    return text;
  }

  function formatRocMonthTitle(year, month) {
    const roc = year - 1911;
    if (Number.isFinite(roc) && roc > 0) {
      return `${roc}年${month}月代墊款表`;
    }
    return `${year}年${month}月代墊款表`;
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
})();
