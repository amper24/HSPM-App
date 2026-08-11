/**
 * Учебный отдел ВШПМ СПбГУПТД — SPA приложение
 * Vanilla JS, без фреймворков
 */
const app = {
  currentUser: null,
  currentSection: 'dashboard',
  apiBase: '/api',
  dashboardDate: null,
  dashboardGroup: null,
  clockInterval: null,

  // ====================== ИНИЦИАЛИЗАЦИЯ ======================
  async init() {
    try {
      const resp = await fetch(this.apiBase + '/auth/me');
      const data = await resp.json();
      if (data.success) { this.currentUser = data.data; this.renderApp(); }
      else { this.renderLogin(); }
    } catch (e) { this.renderLogin(); }
  },

  // ====================== РЕНДЕР ======================
  renderApp() {
    const appEl = document.getElementById('app');
    appEl.innerHTML = `
      <div class="header">
        <h2>Учебный отдел ВШПМ СПбГУПТД</h2>
        <div class="header-user">
          <span>${this.esc(this.currentUser.full_name)} (${this.currentUser.role})</span>
          <button class="outline logout-btn" onclick="app.logout()">Выйти</button>
        </div>
      </div>
      <div class="layout">
        <div class="aside" id="aside"></div>
        <div class="main" id="main"></div>
      </div>
      <button class="theme-toggle" id="theme-toggle-btn" onclick="app.toggleTheme()" title="Светлая/темная тема">☀</button>`;
    if (localStorage.getItem('theme') === 'dark') { document.body.classList.add('dark-theme'); document.getElementById('theme-toggle-btn').textContent = '☾'; }
    this.renderMenu();
    this.navigate(this.currentSection);
  },

  toggleTheme() {
    const isDark = document.body.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    const btn = document.getElementById('theme-toggle-btn');
    if (btn) btn.textContent = isDark ? '☾' : '☀';
  },

  renderLogin() {
    document.getElementById('app').innerHTML = `
      <div class="login-wrapper">
        <div class="login-box">
          <h3>Учебный отдел ВШПМ</h3>
          <p class="login-sub">СПбГУПТД</p>
          <div class="form-group"><label>Логин</label><input id="login-username"></div>
          <div class="form-group"><label>Пароль</label><input type="password" id="login-password"></div>
          <button class="primary login-btn" onclick="app.doLogin()">Войти</button>
          <div id="login-error" class="alert alert-danger" style="display:none"></div>
          <div id="login-spinner" class="spinner" style="display:none"></div>
        </div>
      </div>`;
    document.getElementById('login-username').focus();
    document.getElementById('login-password').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') app.doLogin();
    });
    document.getElementById('login-username').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') document.getElementById('login-password').focus();
    });
  },

  renderMenu() {
    const sections = [
      { id: 'dashboard', label: 'Главная' },
      { id: 'schedule', label: 'Расписание' },
      { id: 'teachers', label: 'Преподаватели' },
      { id: 'classrooms', label: 'Аудитории' },
      { id: 'software', label: 'Программное обеспечение' },
    ];
    if (this.currentUser?.role === 'admin') {
      sections.push({ id: 'users', label: 'Пользователи' });
      sections.push({ id: 'import', label: 'Импорт данных' });
    }
    let html = '';
    sections.forEach(s => {
      html += `<a class="menu-link ${s.id === this.currentSection ? 'active' : ''}" onclick="app.navigate('${s.id}')">${s.label}</a>`;
    });
    document.getElementById('aside').innerHTML = html;
  },

  // ====================== НАВИГАЦИЯ ======================
  navigate(section) {
    this.currentSection = section;
    this.renderMenu();
    const main = document.getElementById('main');
    if (this.clockInterval) { clearInterval(this.clockInterval); this.clockInterval = null; }
    switch (section) {
      case 'dashboard': this.pageDashboard(main); break;
      case 'teachers': this.pageTeachers(main); break;
      case 'classrooms': this.pageClassrooms(main); break;
      case 'schedule': this.pageSchedule(main); break;
      case 'software': this.pageSoftware(main); break;
      case 'users': this.pageUsers(main); break;
      case 'import': this.pageImport(main); break;
    }
  },

  // ====================== АВТОРИЗАЦИЯ ======================
  async doLogin() {
    const u = document.getElementById('login-username').value;
    const p = document.getElementById('login-password').value;
    const err = document.getElementById('login-error');
    const spinner = document.getElementById('login-spinner');
    const btn = document.querySelector('.login-btn');
    err.style.display = 'none';
    spinner.style.display = 'inline-block';
    btn.disabled = true;
    try {
      const resp = await fetch(this.apiBase + '/auth/login', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: u, password: p })
      });
      const data = await resp.json();
      if (data.success) { window.location.reload(); }
      else { err.style.display = 'block'; err.textContent = data.error; }
    } catch (e) { err.style.display = 'block'; err.textContent = 'Ошибка сети'; }
    spinner.style.display = 'none';
    btn.disabled = false;
  },

  async logout() {
    await fetch(this.apiBase + '/auth/logout', { method: 'POST' });
    window.location.reload();
  },

  async api(method, url, body = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const resp = await fetch(this.apiBase + url, opts);
    const data = await resp.json();
    if (!data.success) throw new Error(data.error || 'Ошибка');
    return data;
  },

  // ====================== ГЛАВНАЯ (DASHBOARD) ======================
  startClock() {
    const el = document.getElementById('dashboard-clock');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    if (this.clockInterval) clearInterval(this.clockInterval);
    this.clockInterval = setInterval(() => {
      const n = new Date();
      const cel = document.getElementById('dashboard-clock');
      if (cel) cel.textContent = n.toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }, 1000);
  },

  async pageDashboard(main) {
    const today = new Date().toISOString().split('T')[0];
    if (!this.dashboardDate) this.dashboardDate = today;
    main.innerHTML = `
      <div class="dashboard-header">
        <div class="dashboard-clock" id="dashboard-clock"></div>
        <div class="dashboard-filters">
          <input type="date" id="dash-date" value="${this.dashboardDate}" onchange="app.onDashboardDateChange()">
          <select id="dash-group" onchange="app.onDashboardGroupChange()">
            <option value="">Все группы</option>
          </select>
          <button class="primary" onclick="app.loadDashboardSchedule()">Показать</button>
        </div>
      </div>
      <div class="stats" id="stats-cards"></div>
      <div class="dashboard-schedule" id="dash-schedule"></div>`;
    this.startClock();
    this.loadDashboardStats();
    this.loadDashboardGroups();
    this.loadDashboardSchedule();
  },

  onDashboardDateChange() {
    this.dashboardDate = document.getElementById('dash-date').value;
    this.loadDashboardSchedule();
  },

  onDashboardGroupChange() {
    this.dashboardGroup = document.getElementById('dash-group').value || null;
    this.loadDashboardSchedule();
  },

  async loadDashboardStats() {
    const stats = document.getElementById('stats-cards');
    try {
      const [teachers, classrooms, schedule, software] = await Promise.all([
        this.api('GET', '/teachers?per_page=1'),
        this.api('GET', '/classrooms?per_page=1'),
        this.api('GET', '/schedule?per_page=1'),
        this.api('GET', '/software?per_page=1'),
      ]);
      const getTotal = (r) => (r.data.pagination && r.data.pagination.total) || r.data.total || 0;
      const items = [
        { num: getTotal(teachers), label: 'Преподавателей', color: '#409EFF' },
        { num: getTotal(classrooms), label: 'Аудиторий', color: '#67C23A' },
        { num: getTotal(schedule), label: 'Записей расписания', color: '#E6A23C' },
        { num: getTotal(software), label: 'Записей ПО', color: '#F56C6C' },
      ];
      stats.innerHTML = items.map(i => `<div class="stat-card"><div class="num" style="color:${i.color}">${i.num}</div><div class="label">${i.label}</div></div>`).join('');
    } catch (e) { stats.innerHTML = '<div class="alert alert-danger">Ошибка загрузки статистики</div>'; }
  },

  async loadDashboardGroups() {
    try {
      const r = await this.api('GET', '/schedule/groups');
      const groups = r.data || [];
      const sel = document.getElementById('dash-group');
      if (!sel) return;
      sel.innerHTML = '<option value="">Все группы</option>' + groups.map(g => `<option value="${g}" ${this.dashboardGroup === g ? 'selected' : ''}>${g}</option>`).join('');
    } catch(e) {}
  },

  async loadDashboardSchedule() {
    const container = document.getElementById('dash-schedule');
    container.innerHTML = '<div class="spinner"></div>';
    const params = { per_page: 50, date: this.dashboardDate };
    if (this.dashboardGroup) params.numerator_denominator = this.dashboardGroup;
    try {
      const data = await this.api('GET', '/schedule?' + new URLSearchParams(params));
      const items = data.data.items || [];
      if (!items.length) {
        container.innerHTML = '<div class="card"><p style="text-align:center;color:#909399;">Нет занятий на выбранную дату</p></div>';
        return;
      }
      let html = '<div class="card"><h3>Расписание на ' + this.dashboardDate + (this.dashboardGroup ? ' — ' + this.dashboardGroup : '') + '</h3>';
      html += '<table class="dash-table"><thead><tr><th>Время</th><th>Дисциплина</th><th>Тип</th><th>Аудитория</th><th>Преподаватель</th></tr></thead><tbody>';
      items.forEach(item => {
        const time = item.time_start ? `${item.time_start?.substr(0,5)}–${item.time_end?.substr(0,5)}` : (item.pair_number ? 'Пара ' + item.pair_number : '-');
        html += `<tr>
          <td>${time}</td>
          <td>${this.esc(item.discipline || '-')}</td>
          <td>${this.esc(item.lesson_type || '-')}</td>
          <td>${item.room_number ? app.esc(item.room_number + (item.building ? ' (' + item.building + ')' : '')) : '-'}</td>
          <td>${this.esc(item.teacher_name || '-')}</td>
        </tr>`;
      });
      html += '</tbody></table></div>';
      container.innerHTML = html;
    } catch (e) {
      container.innerHTML = '<div class="alert alert-danger">Ошибка загрузки расписания</div>';
    }
  },

  // ====================== УНИВЕРСАЛЬНАЯ ТАБЛИЦА ======================
  async renderCrudPage(main, config) {
    const { title, columns, apiGet, apiCreate, apiUpdate, apiDelete, filters, formFields, itemName } = config;
    const adminBtns = app.currentUser?.role === 'admin' ? `<button class="danger" onclick="app.truncateTable('${config.id}')" title="Очистить всю таблицу">Очистить</button><button class="primary" onclick="app.openForm('${config.id}', null)">+ Добавить</button>` : `<button class="primary" onclick="app.openForm('${config.id}', null)">+ Добавить</button>`;
    main.innerHTML = `
      <div class="card">
        <div class="card-header">
          <h3>${title}</h3>
          <div class="card-actions">${adminBtns}</div>
        </div>
        <div class="filters-bar" id="filters-${config.id}">${this.renderFilters(config)}
          <label class="per-page-label">Строк: <select class="filter-input" id="per-page-${config.id}" onchange="app.onPerPageChange('${config.id}')"><option value="15">15</option><option value="50" selected>50</option><option value="100">100</option></select></label>
        </div>
        <div class="pagination pagination-top" id="pager-top-${config.id}"></div>
        <div class="table-wrap">
          <table id="table-${config.id}">
            <thead><tr><th>№</th>${columns.map(c => `<th>${c.label}</th>`).join('')}<th></th></tr></thead>
            <tbody id="tbody-${config.id}"></tbody>
          </table>
        </div>
        <div class="pagination pagination-bottom" id="pager-bottom-${config.id}"></div>
      </div>`;

    config.page = 1;
    config.editingItem = null;
    await this.loadCrudData(config);
  },

  renderFilters(config) {
    if (!config.filters) return '';
    return config.filters.map(f => {
      if (f.type === 'text') return `<input class="filter-input" id="filter-${f.field}" placeholder="${f.placeholder}" oninput="app.debouncedLoad('${config.id}')">`;
      if (f.type === 'select') {
        let opts = f.options.map(o => `<option value="${o.value}">${o.label}</option>`).join('');
        return `<select class="filter-input" id="filter-${f.field}" onchange="app.debouncedLoad('${config.id}')"><option value="">${f.placeholder}</option>${opts}</select>`;
      }
      return '';
    }).join('');
  },

  async loadCrudData(config) {
    const params = { page: config.page || 1, per_page: config.perPage || 50 };
    if (config.filters) {
      config.filters.forEach(f => {
        const el = document.getElementById('filter-' + f.field);
        if (el && el.value) params[f.param || f.field] = el.value;
      });
    }
    try {
      const data = await config.apiGet(params);
      const items = data.data.items || data.data || [];
      const total = (data.data.pagination && data.data.pagination.total) || data.data.total || items.length;
      this.renderTable(config, items);
      const perPage = config.perPage || 50;
      const pages = Math.ceil(total / perPage);
      this.renderPager('pager-top-' + config.id, config.page, pages);
      this.renderPager('pager-bottom-' + config.id, config.page, pages);
    } catch (e) {
      document.getElementById('tbody-' + config.id).innerHTML = '<tr><td colspan="99">Ошибка загрузки</td></tr>';
    }
  },

  onPerPageChange(id) {
    const config = this.getConfig(id);
    config.perPage = parseInt(document.getElementById('per-page-' + id).value) || 50;
    config.page = 1;
    this.loadCrudData(config);
  },

  renderTable(config, items) {
    const tbody = document.getElementById('tbody-' + config.id);
    if (!items.length && !config.editingItem) {
      tbody.innerHTML = '<tr><td colspan="99" style="text-align:center;color:#999;">Нет данных</td></tr>';
      return;
    }
    let html = '';
    if (config.editingItem && !config.editingItem.id) {
      html += this.buildEditRow(config);
    }
    html += items.map((item, i) => {
      const perPage = config.perPage || 50;
      const idx = ((config.page - 1) * perPage) + i + 1;
      if (config.editingItem && config.editingItem.id === item.id) {
        return this.buildEditRow(config);
      }
      const cells = config.columns.map((c, ci) => `<td>${this.formatCell(item, c, idx)}</td>`).join('');
      return `<tr id="row-${config.id}-${item.id}" class="data-row">
        <td class="row-num">${idx}</td>
        ${cells}
        <td class="row-actions">
          <button class="outline" onclick="app.editRow('${config.id}', ${item.id})">Ред</button>
          <button class="danger" onclick="app.deleteRow('${config.id}', ${item.id})">Уд</button>
        </td></tr>`;
    }).join('');
    tbody.innerHTML = html || '<tr><td colspan="99" style="text-align:center;color:#999;">Нет данных</td></tr>';
  },

  buildEditRow(config) {
    const item = config.editingItem || {};
    const colSpan = config.columns.length + 2;
    let fields = '';
    config.formFields.forEach(f => {
      const val = item[f.field] ?? '';
      if (f.type === 'select') {
        const opts = f.options.map(o => `<option value="${o.value}" ${o.value == val ? 'selected' : ''}>${o.label}</option>`).join('');
        fields += `<div class="edit-field"><label>${f.label}</label><select id="ef-${f.field}">${opts}</select></div>`;
      } else if (f.type === 'checkbox') {
        fields += `<div class="edit-field"><label><input type="checkbox" id="ef-${f.field}" ${val ? 'checked' : ''}> ${f.label}</label></div>`;
      } else {
        fields += `<div class="edit-field"><label>${f.label}</label><input id="ef-${f.field}" value="${this.esc(String(val))}" placeholder="${f.placeholder || ''}"></div>`;
      }
    });
    return `<tr id="edit-row-${config.id}" class="edit-row"><td colspan="${colSpan}">
      <div class="edit-grid">${fields}</div>
      <div class="edit-actions">
        <button class="primary" onclick="app.saveForm('${config.id}')">✓ Сохранить</button>
        <button class="outline" onclick="app.closeForm('${config.id}')">✕ Отмена</button>
      </div>
    </td></tr>`;
  },

  formatCell(item, col, idx) {
    if (col.render) return col.render(item, idx);
    let val = item[col.field];
    if (val === null || val === undefined || val === '') return '-';
    if (col.type === 'bool') return val ? '<span class="badge badge-success">Да</span>' : '<span class="badge">Нет</span>';
    if (col.type === 'enum' && col.map) return col.map[val] || val;
    return this.esc(String(val));
  },

  renderPager(pagerId, currentPage, totalPages) {
    const pager = document.getElementById(pagerId);
    if (!pager) return;
    if (totalPages <= 0) { pager.innerHTML = ''; return; }
    if (totalPages === 1) {
      pager.innerHTML = '<span class="page-info">1 из 1</span>';
      return;
    }
    const configId = pagerId.replace('pager-top-', '').replace('pager-bottom-', '');
    const maxVisible = 5;
    let items = [];
    items.push({ page: 1, label: '«', disabled: currentPage === 1 });
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
    if (start > 1) {
      items.push({ page: 1, label: '1' });
      if (start > 2) items.push({ page: -1, label: '...', disabled: true });
    }
    for (let i = start; i <= end; i++) items.push({ page: i, label: String(i) });
    if (end < totalPages) {
      if (end < totalPages - 1) items.push({ page: -1, label: '...', disabled: true });
      items.push({ page: totalPages, label: String(totalPages) });
    }
    items.push({ page: totalPages, label: '»', disabled: currentPage === totalPages });
    let html = '';
    items.forEach(btn => {
      if (btn.disabled && btn.page === -1) html += '<span class="page-dots">...</span>';
      else if (btn.disabled) html += `<button class="page-btn outline" disabled>${btn.label}</button>`;
      else html += `<button class="page-btn ${btn.page === currentPage ? 'primary' : 'outline'}" onclick="app.goPage('${configId}', ${btn.page})">${btn.label}</button>`;
    });
    pager.innerHTML = html;
  },

  // ====================== ИНЛАЙН-РЕДАКТИРОВАНИЕ ======================
  openForm(id, item) {
    const config = this.getConfig(id);
    config.editingItem = item || {};
    const tbody = document.getElementById('tbody-' + id);
    if (!item) {
      tbody.insertAdjacentHTML('afterbegin', this.buildEditRow(config));
    } else {
      const row = document.getElementById('row-' + id + '-' + item.id);
      if (row) {
        row.insertAdjacentHTML('beforebegin', this.buildEditRow(config));
        row.style.display = 'none';
      }
    }
    const firstInput = document.querySelector('#edit-row-' + id + ' input');
    if (firstInput) firstInput.focus();
  },

  closeForm(id) {
    const config = this.getConfig(id);
    const editRow = document.getElementById('edit-row-' + id);
    if (editRow) {
      const hiddenRow = editRow.nextElementSibling;
      if (hiddenRow && hiddenRow.classList.contains('data-row') && hiddenRow.style.display === 'none') {
        hiddenRow.style.display = '';
      }
      editRow.remove();
    }
    config.editingItem = null;
  },

  async saveForm(id) {
    const config = this.getConfig(id);
    const data = {};
    config.formFields.forEach(f => {
      const el = document.getElementById('ef-' + f.field);
      if (!el) return;
      if (f.type === 'checkbox') data[f.field] = el.checked ? 1 : 0;
      else data[f.field] = el.value;
    });
    try {
      if (config.editingItem?.id) {
        await config.apiUpdate(config.editingItem.id, data);
      } else {
        await config.apiCreate(data);
      }
      this.closeForm(id);
      await this.loadCrudData(config);
    } catch (e) {
      alert('Ошибка: ' + e.message);
    }
  },

  async editRow(id, itemId) {
    const config = this.getConfig(id);
    try {
      const data = await this.api('GET', `/${config.type}/${itemId}`);
      this.openForm(id, data.data);
    } catch (e) { alert('Ошибка: ' + e.message); }
  },

  async truncateTable(id) {
    const config = this.getConfig(id);
    const label = config.title || config.id;
    if (!confirm(`Вы уверены, что хотите ОЧИСТИТЬ ВСЮ ТАБЛИЦУ «${label}»?\n\nЭТО ДЕЙСТВИЕ НЕОБРАТИМО!\n\nВсе записи будут удалены безвозвратно.`)) return;
    if (!confirm(`Подтвердите: очистить «${label}»?`)) return;
    try {
      await this.api('POST', `/${config.type}/truncate`);
      await this.loadCrudData(config);
      alert(`Таблица «${label}» очищена.`);
    } catch (e) { alert('Ошибка: ' + e.message); }
  },

  async deleteRow(id, itemId) {
    if (!confirm('Удалить запись?')) return;
    const config = this.getConfig(id);
    try {
      await config.apiDelete(itemId);
      await this.loadCrudData(config);
    } catch (e) { alert('Ошибка: ' + e.message); }
  },

  // ====================== ПАГИНАЦИЯ И ПОИСК ======================
  goPage(id, page) {
    const config = this.getConfig(id);
    config.page = page;
    this.loadCrudData(config);
  },

  debouncedLoad(id) {
    clearTimeout(this._debounceTimer);
    this._debounceTimer = setTimeout(() => {
      const config = this.getConfig(id);
      config.page = 1;
      this.loadCrudData(config);
    }, 300);
  },

  // ====================== КОНФИГУРАЦИИ CRUD ======================
  _configs: {},
  getConfig(id) { return this._configs[id]; },
  registerConfig(id, cfg) { this._configs[id] = { id, ...cfg }; },

  // ====================== СТРАНИЦЫ ======================

  async pageTeachers(main) {
    const toOpts = (arr) => (arr || []).map(d => ({ value: d, label: d }));
    let departments = [], degrees = [], titles = [], empTypes = [];
    try {
      const [d1, d2, d3, d4] = await Promise.all([
        this.api('GET', '/teachers/departments'),
        this.api('GET', '/teachers/degrees'),
        this.api('GET', '/teachers/titles'),
        this.api('GET', '/teachers/employment-types'),
      ]);
      departments = d1.data || []; degrees = d2.data || []; titles = d3.data || []; empTypes = d4.data || [];
    } catch(e) {}
    this.registerConfig('teachers', {
      type: 'teachers', itemName: 'Преподаватель', title: 'Преподаватели',
      columns: [
        { label: 'ФИО', field: null, render: item => app.esc(`${item.last_name||''} ${item.first_name||''} ${item.middle_name||''}`.trim()) },
        { label: 'Кафедра', field: 'department' }, { label: 'Должность', field: 'position' },
        { label: 'Степень', field: 'degree' }, { label: 'Звание', field: 'title' },
        { label: 'Занятость', field: 'employment_type' }, { label: 'Email', field: 'email' },
        { label: 'Телефон', field: 'phone' },
      ],
      apiGet: (p) => this.api('GET', '/teachers?' + new URLSearchParams(p)),
      apiCreate: (d) => this.api('POST', '/teachers', d),
      apiUpdate: (id, d) => this.api('PUT', '/teachers/' + id, d),
      apiDelete: (id) => this.api('DELETE', '/teachers/' + id),
      filters: [
        { type: 'text', field: 'search', placeholder: 'Поиск по ФИО...' },
        { type: 'select', field: 'department', placeholder: 'Все кафедры', options: toOpts(departments) },
        { type: 'select', field: 'degree', placeholder: 'Все степени', options: toOpts(degrees) },
        { type: 'select', field: 'title', placeholder: 'Все звания', options: toOpts(titles) },
        { type: 'select', field: 'employment_type', placeholder: 'Все формы', options: toOpts(empTypes) },
      ],
      formFields: [
        { field: 'last_name', label: 'Фамилия', placeholder: 'Иванов' },
        { field: 'first_name', label: 'Имя', placeholder: 'Иван' },
        { field: 'middle_name', label: 'Отчество', placeholder: 'Иванович' },
        { field: 'position', label: 'Должность', placeholder: 'доцент' },
        { field: 'degree', label: 'Степень', placeholder: 'к.т.н.' },
        { field: 'title', label: 'Звание', placeholder: 'доцент' },
        { field: 'department', label: 'Кафедра', placeholder: 'КиКТ' },
        { field: 'employment_type', label: 'Форма занятости', placeholder: 'штатный' },
        { field: 'email', label: 'Email', placeholder: 'ivanov@example.com' },
        { field: 'phone', label: 'Телефон', placeholder: '+7...' },
      ],
    });
    this.renderCrudPage(main, this.getConfig('teachers'));
  },

  async pageClassrooms(main) {
    let roomTypes = [];
    try { const r = await this.api('GET', '/classrooms/room-types'); roomTypes = r.data || []; } catch(e) {}
    const toOpts = (arr) => (arr || []).map(d => ({ value: d, label: d }));
    this.registerConfig('classrooms', {
      type: 'classrooms', itemName: 'Аудиторию', title: 'Аудитории',
      columns: [
        { label: 'Аудитория', field: 'room_number' }, { label: 'Корпус', field: 'building' },
        { label: 'Тип', field: 'room_type' }, { label: 'ПК', field: 'computers_count' },
        { label: 'Проектор', field: 'has_projector', type: 'bool' },
        { label: 'Колонки', field: 'has_speakers', type: 'bool' }, { label: 'Мест', field: 'seats' },
      ],
      apiGet: (p) => this.api('GET', '/classrooms?' + new URLSearchParams(p)),
      apiCreate: (d) => this.api('POST', '/classrooms', d),
      apiUpdate: (id, d) => this.api('PUT', '/classrooms/' + id, d),
      apiDelete: (id) => this.api('DELETE', '/classrooms/' + id),
      filters: [
        { type: 'text', field: 'search', placeholder: 'Поиск по номеру, типу...' },
        { type: 'select', field: 'building', placeholder: 'Все корпуса', options: [{ value: 'Д', label: 'Джамбула' }, { value: 'В', label: 'Вознесенский' }, { value: 'БМ', label: 'Большая Морская' }] },
        { type: 'select', field: 'room_type', placeholder: 'Все типы', options: toOpts(roomTypes) },
        { type: 'select', field: 'has_projector', placeholder: 'Проектор', options: [{ value: '1', label: 'Есть' }, { value: '0', label: 'Нет' }] },
        { type: 'select', field: 'has_speakers', placeholder: 'Колонки', options: [{ value: '1', label: 'Есть' }, { value: '0', label: 'Нет' }] },
        { type: 'select', field: 'sort_seats', placeholder: 'Сортировка по местам', options: [{ value: 'asc', label: 'По местам ↑' }, { value: 'desc', label: 'По местам ↓' }] },
      ],
      formFields: [
        { field: 'room_number', label: '№ аудитории', placeholder: '201' },
        { field: 'building', label: 'Корпус', placeholder: 'В/Д/БМ' },
        { field: 'room_type', label: 'Тип', placeholder: 'лекционная аудитория' },
        { field: 'computers_count', label: 'Количество ПК', placeholder: '0' },
        { field: 'has_projector', label: 'Проектор', type: 'checkbox' },
        { field: 'has_speakers', label: 'Колонки', type: 'checkbox' },
        { field: 'seats', label: 'Посадочных мест', placeholder: '24' },
      ],
    });
    this.renderCrudPage(main, this.getConfig('classrooms'));
  },

  pageSchedule(main) {
    this.registerConfig('schedule', {
      type: 'schedule', itemName: 'Запись расписания', title: 'Расписание',
      columns: [
        { label: 'Дата', field: 'date' },
        { label: 'Время', field: 'time_range', render: item => item.time_start ? `${item.time_start?.substr(0,5)}-${item.time_end?.substr(0,5)}` : '-' },
        { label: 'Группа', field: 'group_code' },
        { label: 'Дисциплина', field: 'discipline' },
        { label: 'Вид', field: 'exam_type' },
        { label: 'Экзаменатор', field: 'examiner' },
        { label: 'Аудитория', field: 'classroom_room', render: item => item.classroom_room ? `${item.classroom_room} (${item.classroom_building || ''})` : '-' },
        { label: 'Перенос', field: 'transfer_cancel', render: item => item.transfer_cancel === 'перенос' ? '<span class="badge badge-warning">Перенос</span>' : '' },
      ],
      apiGet: (p) => this.api('GET', '/schedule?' + new URLSearchParams(p)),
      apiCreate: (d) => this.api('POST', '/schedule', d),
      apiUpdate: (id, d) => this.api('PUT', '/schedule/' + id, d),
      apiDelete: (id) => this.api('DELETE', '/schedule/' + id),
      filters: [
        { type: 'text', field: 'search', placeholder: 'Поиск по дисциплине, группе, преподавателю...' },
        { type: 'select', field: 'transfer_cancel', placeholder: 'Все', options: [{ value: 'перенос', label: 'Переносы' }, { value: 'нет', label: 'Без переносов' }] },
      ],
      formFields: [
        { field: 'date', label: 'Дата', placeholder: '2025-12-22' },
        { field: 'time_start', label: 'Время начала', placeholder: '10:05' },
        { field: 'time_end', label: 'Время окончания', placeholder: '11:30' },
        { field: 'group_code', label: 'Группа', placeholder: '1-ГИД-19' },
        { field: 'discipline', label: 'Дисциплина', placeholder: 'Информационные технологии' },
        { field: 'exam_type', label: 'Вид', placeholder: 'экзамен / консультация' },
        { field: 'examiner', label: 'Экзаменатор', placeholder: 'Иванов И.И.' },
        { field: 'group_department', label: 'Кафедра группы', placeholder: 'КиКТ' },
        { field: 'teacher_department', label: 'Кафедра преп.', placeholder: 'ИиУС' },
        { field: 'teacher_position', label: 'Должность преп.', placeholder: 'ст. пр.' },
        { field: 'session_start', label: 'Сессия с', placeholder: '2025-12-22' },
        { field: 'session_end', label: 'Сессия по', placeholder: '2025-12-30' },
        { field: 'lesson_type', label: 'Тип занятия', placeholder: 'лекция / практика / лабораторная' },
        { field: 'transfer_cancel', label: 'Перенос/отмена', placeholder: 'нет / перенос' },
      ],
    });
    this.renderCrudPage(main, this.getConfig('schedule'));
  },

  async pageSoftware(main) {
    let buildings = [];
    try { const r = await this.api('GET', '/software/buildings'); buildings = r.data || []; } catch(e) {}
    const toOpts = (arr) => (arr || []).map(d => ({ value: d, label: d }));
    this.registerConfig('software', {
      type: 'software', itemName: 'ПО', title: 'Программное обеспечение',
      columns: [
        { label: 'Название', field: 'name' }, { label: 'Аудитория', field: 'room_number' }, { label: 'Корпус', field: 'building' },
      ],
      apiGet: (p) => this.api('GET', '/software?' + new URLSearchParams(p)),
      apiCreate: (d) => this.api('POST', '/software', d),
      apiUpdate: (id, d) => this.api('PUT', '/software/' + id, d),
      apiDelete: (id) => this.api('DELETE', '/software/' + id),
      filters: [
        { type: 'text', field: 'search', placeholder: 'Поиск по названию...' },
        { type: 'select', field: 'building', placeholder: 'Все корпуса', options: toOpts(buildings) },
      ],
      formFields: [
        { field: 'name', label: 'Название ПО', placeholder: 'Microsoft Office 2016' },
        { field: 'room_number', label: 'Аудитория', placeholder: '444' },
        { field: 'building', label: 'Корпус', placeholder: 'Д' },
      ],
    });
    this.renderCrudPage(main, this.getConfig('software'));
  },

  pageUsers(main) {
    if (this.currentUser?.role !== 'admin') return;
    this.registerConfig('users', {
      type: 'users', itemName: 'Пользователя', title: 'Пользователи',
      columns: [
        { label: 'Логин', field: 'username' }, { label: 'Роль', field: 'role' },
        { label: 'ФИО', field: 'full_name' }, { label: 'Создан', field: 'created_at' },
      ],
      apiGet: (p) => this.api('GET', '/users?' + new URLSearchParams(p)),
      apiCreate: (d) => this.api('POST', '/users', d),
      apiUpdate: (id, d) => this.api('PUT', '/users/' + id, d),
      apiDelete: (id) => this.api('DELETE', '/users/' + id),
      formFields: [
        { field: 'username', label: 'Логин', placeholder: 'user' },
        { field: 'password', label: 'Пароль', placeholder: '' },
        { field: 'role', label: 'Роль', type: 'select', options: [{ value: 'user', label: 'Пользователь' }, { value: 'admin', label: 'Администратор' }] },
        { field: 'full_name', label: 'ФИО', placeholder: 'Иванов И.И.' },
      ],
    });
    this.renderCrudPage(main, this.getConfig('users'));
  },

  pageImport(main) {
    if (this.currentUser?.role !== 'admin') return;
    main.innerHTML = `
      <div class="card">
        <h3>Импорт данных</h3>
        <div class="form-group"><label>Тип данных</label>
          <select id="import-type">
            <option value="teachers">Преподаватели</option>
            <option value="classrooms">Аудитории</option>
            <option value="schedule">Расписание</option>
            <option value="software">Программное обеспечение</option>
          </select>
        </div>
        <div class="form-group"><label>Файл (.xlsx, .xls)</label>
          <input type="file" id="import-file" accept=".xlsx,.xls">
        </div>
        <button class="primary" id="import-btn" onclick="app.doImport()">Загрузить</button>
        <div class="import-progress" id="import-progress" style="display:none">
          <div class="spinner"></div>
          <span>Импорт данных...</span>
        </div>
        <div id="import-result" style="margin-top:12px"></div>
      </div>`;
  },

  async doImport() {
    const fileEl = document.getElementById('import-file');
    const type = document.getElementById('import-type').value;
    const result = document.getElementById('import-result');
    const progress = document.getElementById('import-progress');
    const btn = document.getElementById('import-btn');
    if (!fileEl.files[0]) { result.innerHTML = '<div class="alert alert-danger">Выберите файл</div>'; return; }
    progress.style.display = 'flex';
    btn.disabled = true;
    result.innerHTML = '';
    const fd = new FormData();
    fd.append('file', fileEl.files[0]);
    fd.append('type', type);
    try {
      const resp = await fetch(this.apiBase + '/import', { method: 'POST', body: fd });
      const data = await resp.json();
      result.innerHTML = data.success
        ? `<div class="alert alert-success">${data.message}</div>`
        : `<div class="alert alert-danger">${data.error}</div>`;
    } catch (e) {
      result.innerHTML = '<div class="alert alert-danger">Ошибка сети</div>';
    }
    progress.style.display = 'none';
    btn.disabled = false;
  },

  // ====================== УТИЛИТЫ ======================
  esc(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
  },
};

window.axios = {
  async request(config) {
    const url = config.url;
    const opts = { method: config.method, headers: { 'Content-Type': 'application/json', ...config.headers } };
    if (config.data) opts.body = JSON.stringify(config.data);
    const resp = await fetch(url, opts);
    const data = await resp.json();
    return { data };
  },
  post(url, data, opts) { return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json()); }
};

document.addEventListener('DOMContentLoaded', () => app.init());