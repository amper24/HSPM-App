/**
 * Учебный отдел ВШПМ СПбГУПТД — SPA приложение (Vue 2)
 */
const api = {
  async request(method, url, body = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const resp = await fetch('/api' + url, opts);
    const data = await resp.json();
    if (!data.success) throw new Error(data.error || 'Ошибка');
    return data;
  }
};

const app = new Vue({
  el: '#app',
  data() {
    return {
      theme: localStorage.getItem('theme') || 'light',
      user: null,
      view: 'loading', // loading | login | app
      login: { username: '', password: '', error: '', busy: false },

      section: 'dashboard',
      clock: '',

      // dashboard
      dash: {
        date: new Date().toISOString().split('T')[0],
        group: null,
        groups: [],
        stats: [],
        schedule: [],
        loading: false,
        search: '',
        searchOpen: false,
        searchActive: -1
      },

      // CRUD конфиги
      configs: {
        teachers: null,
        classrooms: null,
        schedule: null,
        software: null,
        users: null
      },
      state: {},       // id -> { page, perPage, items, total, pages, loading, editingItem }
      filters: {},     // id -> { field: value }
      filterOptions: {}, // id -> { key: [values] }

      // import
      importCfg: { type: 'teachers', file: null, busy: false, result: '', progress: false, createMissing: true, replace: false },
      // free classrooms
      free: { date: new Date().toISOString().split('T')[0], pair_number: '', building: '', room_type: '', has_projector: '', has_speakers: '', seats_min: '', results: [], searched: false, loading: false }
    };
  },

  computed: {
    userLabel() {
      return this.user ? `${this.user.full_name || ''} (${this.user.role || ''})` : '';
    },
    isAdmin() {
      return this.user && this.user.role === 'admin';
    },
    menu() {
      const sections = [
        { id: 'dashboard', label: 'Главная' },
        { id: 'schedule', label: 'Расписание' },
        { id: 'teachers', label: 'Преподаватели' },
        { id: 'classrooms', label: 'Аудитории' },
        { id: 'software', label: 'Программное обеспечение' }
      ];
      if (this.isAdmin) {
        sections.push({ id: 'users', label: 'Пользователи' });
        sections.push({ id: 'import', label: 'Импорт данных' });
      }
      return sections;
    },
    groupSuggestions() {
      const q = this.dash.search.trim().toLowerCase();
      if (!q) return [];
      return this.dash.groups.filter(g => g.toLowerCase().includes(q));
    }
  },

  created() {
    this.applyTheme();
    this.init();
  },

  methods: {
    // ====================== ИНИЦИАЛИЗАЦИЯ ======================
    async init() {
      try {
        const resp = await fetch('/api/auth/me');
        const data = await resp.json();
        if (data.success && data.data && data.data.id) {
          this.user = data.data;
          this.view = 'app';
          this.navigate('dashboard');
        } else {
          this.view = 'login';
        }
      } catch (e) {
        this.view = 'login';
      }
    },

    startClock() {
      this.clock = this.formatClock(new Date());
      if (this._clockTimer) clearInterval(this._clockTimer);
      this._clockTimer = setInterval(() => {
        this.clock = this.formatClock(new Date());
      }, 1000);
    },

    formatClock(now) {
      return now.toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },

    // ====================== АВТОРИЗАЦИЯ ======================
    async doLogin() {
      this.login.busy = true;
      this.login.error = '';
      try {
        const resp = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username: this.login.username, password: this.login.password })
        });
        const data = await resp.json();
        if (data.success) {
          window.location.reload();
        } else {
          this.login.error = data.error;
        }
      } catch (e) {
        this.login.error = 'Ошибка сети';
      }
      this.login.busy = false;
    },

    async logout() {
      await fetch('/api/auth/logout', { method: 'POST' });
      window.location.reload();
    },

    toggleTheme() {
      this.theme = this.theme === 'dark' ? 'light' : 'dark';
      localStorage.setItem('theme', this.theme);
      this.applyTheme();
    },

    applyTheme() {
      if (this.theme === 'dark') {
        document.body.classList.add('dark-theme');
      } else {
        document.body.classList.remove('dark-theme');
      }
    },

    // ====================== НАВИГАЦИЯ ======================
    navigate(section) {
      this.section = section;
      if (section === 'dashboard') {
        this.startClock();
        this.loadDashboard();
      } else if (['teachers', 'classrooms', 'schedule', 'software', 'users'].includes(section)) {
        this.ensureConfig(section);
      }
    },

    // ====================== DASHBOARD ======================
    async loadDashboard() {
      this.dash.stats = [];
      this.dash.schedule = [];
      this.loadDashboardStats();
      this.loadDashboardGroups();
      this.loadDashboardSchedule();
    },

    onDashboardDateChange() {
      this.loadDashboardSchedule();
    },

    async loadDashboardStats() {
      try {
        const [teachers, classrooms, schedule, software] = await Promise.all([
          api.request('GET', '/teachers?per_page=1'),
          api.request('GET', '/classrooms?per_page=1'),
          api.request('GET', '/schedule?per_page=1'),
          api.request('GET', '/software?per_page=1')
        ]);
        const getTotal = (r) => (r.data.pagination && r.data.pagination.total) || r.data.total || 0;
        this.dash.stats = [
          { num: getTotal(teachers), label: 'Преподавателей', color: '#409EFF' },
          { num: getTotal(classrooms), label: 'Аудиторий', color: '#67C23A' },
          { num: getTotal(schedule), label: 'Записей расписания', color: '#E6A23C' },
          { num: getTotal(software), label: 'Записей ПО', color: '#F56C6C' }
        ];
      } catch (e) {
        this.dash.stats = [];
      }
    },

    async loadDashboardGroups() {
      try {
        const r = await api.request('GET', '/schedule/groups');
        this.dash.groups = (r.data || []).map(g => String(g).trim()).filter(Boolean);
      } catch (e) {
        this.dash.groups = [];
      }
    },

    async loadDashboardSchedule() {
      this.dash.loading = true;
      const params = { per_page: 50, date: this.dash.date };
      if (this.dash.group) params.group_code = this.dash.group;
      try {
        const data = await api.request('GET', '/schedule?' + new URLSearchParams(params));
        this.dash.schedule = data.data.items || [];
      } catch (e) {
        this.dash.schedule = [];
      }
      this.dash.loading = false;
    },

    selectGroup(g) {
      this.dash.group = g || null;
      this.dash.search = g || '';
      this.dash.searchOpen = false;
      this.loadDashboardSchedule();
    },

    onGroupSearchInput() {
      this.dash.searchActive = -1;
      if (!this.dash.search.trim()) {
        this.dash.searchOpen = false;
        if (this.dash.group) {
          this.dash.group = null;
          this.loadDashboardSchedule();
        }
      } else {
        this.dash.searchOpen = true;
      }
    },

    onGroupSearchKeydown(e) {
      if (!this.dash.searchOpen) return;
      if (e.key === 'Escape') {
        this.dash.searchOpen = false;
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        this.dash.searchActive = this.dash.searchActive < this.groupSuggestions.length - 1 ? this.dash.searchActive + 1 : 0;
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        this.dash.searchActive = this.dash.searchActive > 0 ? this.dash.searchActive - 1 : this.groupSuggestions.length - 1;
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (this.dash.searchActive >= 0 && this.groupSuggestions[this.dash.searchActive]) {
          this.selectGroup(this.groupSuggestions[this.dash.searchActive]);
        }
      }
    },

    // ====================== CONFIG / CRUD ======================
    ensureConfig(id) {
      if (id === 'users' && !this.isAdmin) return;
      if (!this.state[id]) {
        this.$set(this.state, id, { page: 1, perPage: 50, items: [], total: 0, pages: 1, loading: false, editingItem: null });
      }
      if (!this.filters[id]) this.$set(this.filters, id, {});
      if (!this.filterOptions[id]) this.$set(this.filterOptions, id, {});

      if (!this.configs[id]) this.buildConfig(id);
      const cfg = this.configs[id];
      if (cfg && cfg.filters) {
        for (const f of cfg.filters) {
          if (this.filters[id][f.field] === undefined) {
            this.$set(this.filters[id], f.field, '');
          }
        }
      }
      this.loadFilterOptions(id);
      this.loadCrudData(id);
    },

    buildConfig(id) {
      if (id === 'teachers') {
        this.configs.teachers = {
          type: 'teachers', title: 'Преподаватели', itemName: 'Преподаватель', showReport: false,
          columns: [
            { label: 'ФИО', render: item => this.fio(item) },
            { label: 'Кафедра', field: 'department' }, { label: 'Должность', field: 'position' },
            { label: 'Степень', field: 'degree' }, { label: 'Звание', field: 'title' },
            { label: 'Занятость', field: 'employment_type' }, { label: 'Email', field: 'email' },
            { label: 'Телефон', field: 'phone' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по ФИО...' },
            { type: 'select', field: 'department', placeholder: 'Все кафедры' },
            { type: 'select', field: 'degree', placeholder: 'Все степени' },
            { type: 'select', field: 'title', placeholder: 'Все звания' },
            { type: 'select', field: 'employment_type', placeholder: 'Все формы' },
            { type: 'select', field: 'transfer_cancel', placeholder: 'Перенос/отмена', options: [{ value: 'перенос', label: 'Переносы' }, { value: 'отмена', label: 'Отмены' }] }
          ],
          formFields: [
            { field: 'last_name', label: 'Фамилия' }, { field: 'first_name', label: 'Имя' }, { field: 'middle_name', label: 'Отчество' },
            { field: 'position', label: 'Должность' }, { field: 'degree', label: 'Степень' }, { field: 'title', label: 'Звание' },
            { field: 'department', label: 'Кафедра' }, { field: 'employment_type', label: 'Форма занятости' },
            { field: 'email', label: 'Email' }, { field: 'phone', label: 'Телефон' }
          ]
        };
      } else if (id === 'classrooms') {
        this.configs.classrooms = {
          type: 'classrooms', title: 'Аудитории', itemName: 'Аудиторию', showReport: true,
          columns: [
            { label: 'Аудитория', field: 'room_number' }, { label: 'Корпус', field: 'building' },
            { label: 'Тип', field: 'room_type' }, { label: 'ПК', field: 'computers_count' },
            { label: 'Проектор', field: 'has_projector', type: 'bool' },
            { label: 'Колонки', field: 'has_speakers', type: 'bool' }, { label: 'Мест', field: 'seats' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по номеру, типу...' },
            { type: 'select', field: 'building', placeholder: 'Все корпуса', options: [{ value: 'Д', label: 'Джамбула' }, { value: 'В', label: 'Вознесенский' }, { value: 'БМ', label: 'Большая Морская' }] },
            { type: 'select', field: 'room_type', placeholder: 'Все типы' },
            { type: 'select', field: 'has_projector', placeholder: 'Проектор', options: [{ value: '1', label: 'Есть' }, { value: '0', label: 'Нет' }] },
            { type: 'select', field: 'has_speakers', placeholder: 'Колонки', options: [{ value: '1', label: 'Есть' }, { value: '0', label: 'Нет' }] },
            { type: 'select', field: 'sort_seats', placeholder: 'Сортировка по местам', options: [{ value: 'asc', label: 'По местам ↑' }, { value: 'desc', label: 'По местам ↓' }] }
          ],
          formFields: [
            { field: 'room_number', label: '№ аудитории' }, { field: 'building', label: 'Корпус' },
            { field: 'room_type', label: 'Тип' }, { field: 'computers_count', label: 'Количество ПК' },
            { field: 'has_projector', label: 'Проектор', type: 'checkbox' }, { field: 'has_speakers', label: 'Колонки', type: 'checkbox' },
            { field: 'seats', label: 'Посадочных мест' }
          ]
        };
      } else if (id === 'schedule') {
        this.configs.schedule = {
          type: 'schedule', title: 'Расписание', itemName: 'Запись расписания', showReport: false,
          columns: [
            { label: 'Дата', field: 'date' },
            { label: 'Время', render: item => item.time_start ? `${String(item.time_start).substr(0, 5)}-${String(item.time_end).substr(0, 5)}` : '-' },
            { label: 'Группа', field: 'group_code' },
            { label: 'Дисциплина', field: 'discipline' },
            { label: 'Вид', field: 'exam_type' },
            { label: 'Экзаменатор', field: 'examiner' },
            { label: 'Аудитория', render: item => this.scheduleRooms(item) },
            { label: 'Перенос/отмена', render: item => this.transferBadge(item.transfer_cancel) }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по дисциплине, группе, преподавателю...' },
            { type: 'select', field: 'transfer_cancel', placeholder: 'Все', options: [{ value: 'перенос', label: 'Переносы' }, { value: 'отмена', label: 'Отмены' }, { value: 'нет', label: 'Без переносов/отмен' }] }
          ],
          formFields: [
            { field: 'date', label: 'Дата' }, { field: 'time_start', label: 'Время начала' }, { field: 'time_end', label: 'Время окончания' },
            { field: 'group_code', label: 'Группа' }, { field: 'discipline', label: 'Дисциплина' },
            { field: 'exam_type', label: 'Вид' }, { field: 'examiner', label: 'Экзаменатор' },
            { field: 'classrooms', label: 'Аудитории' }, { field: 'group_department', label: 'Кафедра группы' },
            { field: 'teacher_department', label: 'Кафедра преп.' }, { field: 'teacher_position', label: 'Должность преп.' },
            { field: 'session_start', label: 'Сессия с' }, { field: 'session_end', label: 'Сессия по' },
            { field: 'lesson_type', label: 'Тип занятия' }, { field: 'transfer_cancel', label: 'Перенос/отмена' }
          ]
        };
      } else if (id === 'software') {
        this.configs.software = {
          type: 'software', title: 'Программное обеспечение', itemName: 'ПО', showReport: false,
          columns: [
            { label: 'Название', field: 'name' }, { label: 'Аудитория', field: 'room_number' }, { label: 'Корпус', field: 'building' }
          ],
          filters: [
            { type: 'text', field: 'search', placeholder: 'Поиск по названию...' },
            { type: 'select', field: 'building', placeholder: 'Все корпуса' }
          ],
          formFields: [
            { field: 'name', label: 'Название ПО' }, { field: 'room_number', label: 'Аудитория' }, { field: 'building', label: 'Корпус' }
          ]
        };
      } else if (id === 'users') {
        this.configs.users = {
          type: 'users', title: 'Пользователи', itemName: 'Пользователя', showReport: false,
          columns: [
            { label: 'Логин', field: 'username' }, { label: 'Роль', field: 'role' },
            { label: 'ФИО', field: 'full_name' }, { label: 'Создан', field: 'created_at' }
          ],
          filters: [],
          formFields: [
            { field: 'username', label: 'Логин' }, { field: 'password', label: 'Пароль' },
            { field: 'role', label: 'Роль', type: 'select', options: [{ value: 'user', label: 'Пользователь' }, { value: 'admin', label: 'Администратор' }] },
            { field: 'full_name', label: 'ФИО' }
          ]
        };
      }
    },

    async loadFilterOptions(id) {
      const load = async (url) => {
        try { const r = await api.request('GET', url); return r.data || []; } catch (e) { return []; }
      };
      const toOpts = (arr) => (arr || []).map(d => ({ value: d, label: d }));

      if (id === 'teachers') {
        const [departments, degrees, titles, empTypes] = await Promise.all([
          load('/teachers/departments'), load('/teachers/degrees'), load('/teachers/titles'), load('/teachers/employment-types')
        ]);
        this.$set(this.filterOptions, 'teachers', { department: toOpts(departments), degree: toOpts(degrees), title: toOpts(titles), employment_type: toOpts(empTypes) });
      } else if (id === 'classrooms') {
        const roomTypes = await load('/classrooms/room-types');
        this.$set(this.filterOptions, 'classrooms', { room_type: toOpts(roomTypes) });
      } else if (id === 'software') {
        const buildings = await load('/software/buildings');
        this.$set(this.filterOptions, 'software', { building: toOpts(buildings) });
      }
    },

    async loadCrudData(id) {
      const cfg = this.configs[id];
      const st = this.state[id];
      if (!cfg || !st) return;
      st.loading = true;
      const params = { page: st.page, per_page: st.perPage };
      for (const key in (this.filters[id] || {})) {
        if (this.filters[id][key]) params[key] = this.filters[id][key];
      }
      try {
        const data = await api.request('GET', '/' + cfg.type + '?' + new URLSearchParams(params));
        st.items = data.data.items || data.data || [];
        st.total = (data.data.pagination && data.data.pagination.total) || data.data.total || st.items.length;
        st.pages = Math.ceil(st.total / st.perPage);
      } catch (e) {
        st.items = [];
        st.total = 0;
        st.pages = 1;
      }
      st.loading = false;
    },

    debouncedLoad(id) {
      clearTimeout(this._debounce);
      this._debounce = setTimeout(() => {
        this.state[id].page = 1;
        this.loadCrudData(id);
      }, 300);
    },

    onPerPageChange(id) {
      this.state[id].page = 1;
      this.state[id].perPage = Number(document.getElementById('per-page-' + id).value) || 50;
      this.loadCrudData(id);
    },

    goPage(id, page) {
      this.state[id].page = page;
      this.loadCrudData(id);
    },

    pagerButtons(id) {
      const st = this.state[id];
      const current = st.page;
      const total = st.pages;
      const items = [];
      if (total <= 1) return items;
      items.push({ page: 1, label: '«', disabled: current === 1 });
      const maxVisible = 5;
      let start = Math.max(1, current - Math.floor(maxVisible / 2));
      let end = Math.min(total, start + maxVisible - 1);
      if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1);
      if (start > 1) {
        items.push({ page: 1, label: '1' });
        if (start > 2) items.push({ page: -1, label: '...', disabled: true });
      }
      for (let i = start; i <= end; i++) items.push({ page: i, label: String(i) });
      if (end < total) {
        if (end < total - 1) items.push({ page: -1, label: '...', disabled: true });
        items.push({ page: total, label: String(total) });
      }
      items.push({ page: total, label: '»', disabled: current === total });
      return items;
    },

    // ====================== КОЛОНКИ / ФОРМАТ ======================
    fio(item) {
      return `${item.last_name || ''} ${item.first_name || ''} ${item.middle_name || ''}`.trim();
    },
    transferBadge(tc) {
      if (tc === 'перенос') return '<span class="badge badge-warning">Перенос</span>';
      if (tc === 'отмена') return '<span class="badge badge-danger">Отмена</span>';
      return '';
    },
    scheduleRooms(item) {
      const rooms = item.classrooms || item.classrooms_raw || (item.room_number && item.building ? `${item.building}${item.room_number}` : '');
      return rooms === 'ДО' ? 'ДО' : (rooms || '-');
    },
    rowClass(item) {
      if (item.transfer_cancel === 'перенос') return 'row-transfer';
      if (item.transfer_cancel === 'отмена') return 'row-cancel';
      return '';
    },
    cellValue(item, col) {
      if (col.render) return col.render(item);
      let val = item[col.field];
      if (val === null || val === undefined || val === '') return '-';
      if (col.type === 'bool') return val ? '<span class="badge badge-success">Да</span>' : '<span class="badge">Нет</span>';
      return this.esc(String(val));
    },

    // ====================== РЕДАКТИРОВАНИЕ ======================
    openForm(id, item) {
      const st = this.state[id];
      const cfg = this.configs[id];
      if (item) {
        api.request('GET', '/' + cfg.type + '/' + item.id).then(r => {
          st.editingItem = { ...(r.data || item) };
        }).catch(() => { st.editingItem = { ...item }; });
      } else {
        st.editingItem = {};
      }
    },

    closeForm(id) {
      this.state[id].editingItem = null;
    },

    async saveForm(id) {
      const cfg = this.configs[id];
      const st = this.state[id];
      const item = st.editingItem;
      const data = {};
      for (const f of cfg.formFields) {
        if (item[f.field] !== undefined) data[f.field] = item[f.field];
      }
      if (cfg.type === 'schedule' && data.classrooms !== undefined) {
        data.classrooms_raw = data.classrooms;
      }
      try {
        if (item.id) {
          await api.request('PUT', '/' + cfg.type + '/' + item.id, data);
        } else {
          await api.request('POST', '/' + cfg.type, data);
        }
        this.closeForm(id);
        await this.loadCrudData(id);
      } catch (e) {
        alert('Ошибка: ' + e.message);
      }
    },

    async deleteRow(id, itemId) {
      if (!confirm('Удалить запись?')) return;
      const cfg = this.configs[id];
      try {
        await api.request('DELETE', '/' + cfg.type + '/' + itemId);
        await this.loadCrudData(id);
      } catch (e) {
        alert('Ошибка: ' + e.message);
      }
    },

    async truncateTable(id) {
      const cfg = this.configs[id];
      const label = cfg.title || id;
      if (!confirm(`Вы уверены, что хотите ОЧИСТИТЬ ВСЮ ТАБЛИЦУ «${label}»?\n\nЭТО ДЕЙСТВИЕ НЕОБРАТИМО!`)) return;
      if (!confirm(`Подтвердите: очистить «${label}»?`)) return;
      try {
        await api.request('POST', '/' + cfg.type + '/truncate');
        await this.loadCrudData(id);
        alert('Таблица очищена.');
      } catch (e) {
        alert('Ошибка: ' + e.message);
      }
    },

    // ====================== ЭКСПОРТ ======================
    async exportExcel(action) {
      try {
        const data = await api.request('GET', '/export/' + action);
        const url = data.data && data.data.url;
        if (!url) { alert('Экспорт выполнен'); return; }
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
      } catch (e) {
        alert('Ошибка экспорта: ' + e.message);
      }
    },

    // ====================== СВОБОДНЫЕ АУДИТОРИИ ======================
    async loadFreeClassrooms() {
      this.free.loading = true;
      this.free.searched = false;
      const params = { date: this.free.date };
      if (this.free.pair_number) params.pair_number = this.free.pair_number;
      if (this.free.building) params.building = this.free.building;
      if (this.free.room_type) params.room_type = this.free.room_type;
      if (this.free.has_projector) params.has_projector = this.free.has_projector;
      if (this.free.has_speakers) params.has_speakers = this.free.has_speakers;
      if (this.free.seats_min) params.seats_min = this.free.seats_min;
      try {
        const data = await api.request('GET', '/classrooms/free?' + new URLSearchParams(params));
        this.free.results = data.data || [];
      } catch (e) {
        this.free.results = [];
      }
      this.free.searched = true;
      this.free.loading = false;
    },

    // ====================== ИМПОРТ ======================
    onFileChange(e) {
      this.importCfg.file = e.target.files[0] || null;
    },

    async doImport() {
      const c = this.importCfg;
      if (!c.file) { c.result = '<div class="alert alert-danger">Выберите файл</div>'; return; }
      c.progress = true;
      c.busy = true;
      c.result = '';
      const fd = new FormData();
      fd.append('file', c.file);
      fd.append('type', c.type);
      if (c.createMissing) fd.append('create_missing', '1');
      if (c.replace) fd.append('replace', '1');
      try {
        const resp = await fetch('/api/import', { method: 'POST', body: fd });
        const data = await resp.json();
        c.result = data.success
          ? `<div class="alert alert-success">${data.message}</div>`
          : `<div class="alert alert-danger">${data.error}</div>`;
      } catch (e) {
        c.result = '<div class="alert alert-danger">Ошибка сети</div>';
      }
      c.progress = false;
      c.busy = false;
    },

    // ====================== УТИЛИТЫ ======================
    esc(str) {
      if (str === null || str === undefined) return '';
      return String(str).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>');
    }
  },

  template: `
  <div>
    <div v-if="view === 'login'" class="login-wrapper">
      <div class="login-box">
        <h3>Учебный отдел ВШПМ</h3>
        <p class="login-sub">СПбГУПТД</p>
        <div class="form-group"><label>Логин</label><input v-model="login.username" @keydown.enter="doLogin"></div>
        <div class="form-group"><label>Пароль</label><input type="password" v-model="login.password" @keydown.enter="doLogin"></div>
        <button class="primary login-btn" :disabled="login.busy" @click="doLogin">Войти</button>
        <div v-if="login.error" class="alert alert-danger">{{ login.error }}</div>
        <div v-if="login.busy" class="spinner"></div>
      </div>
    </div>

    <div v-else-if="view === 'loading'" class="login-wrapper">
      <div class="spinner"></div>
    </div>

    <div v-else>
      <div class="header">
        <h2>Учебный отдел ВШПМ СПбГУПТД</h2>
        <div class="header-user">
          <span>{{ userLabel }}</span>
          <button class="outline" @click="logout">Выйти</button>
        </div>
      </div>
      <div class="layout">
        <div class="aside">
          <a v-for="s in menu" :key="s.id" class="menu-link" :class="{ active: s.id === section }" @click="navigate(s.id)">{{ s.label }}</a>
        </div>
        <div class="main">

          <div v-if="section === 'dashboard'">
            <div class="dashboard-header">
              <div class="dashboard-clock">{{ clock }}</div>
              <div class="dashboard-filters">
                <input type="date" v-model="dash.date" @change="onDashboardDateChange">
                <div class="group-search">
                  <input type="text" v-model="dash.search" placeholder="Поиск группы..." autocomplete="off"
                    @input="onGroupSearchInput" @keydown="onGroupSearchKeydown">
                  <div class="group-search-dropdown" v-show="dash.searchOpen">
                    <div v-if="!groupSuggestions.length" class="group-search-empty">Нет совпадений</div>
                    <div v-for="(g, i) in groupSuggestions" :key="g" class="group-search-item"
                      :class="{ active: i === dash.searchActive }" @mousedown.prevent="selectGroup(g)">{{ g }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="stats">
              <div v-for="s in dash.stats" :key="s.label" class="stat-card">
                <div class="num" :style="{ color: s.color }">{{ s.num }}</div>
                <div class="label">{{ s.label }}</div>
              </div>
            </div>
            <div class="dashboard-schedule">
              <div v-if="dash.loading" class="spinner"></div>
              <div v-else-if="!dash.schedule.length" class="card"><p style="text-align:center;color:#909399;">Нет занятий на выбранную дату</p></div>
              <div v-else class="card">
                <h3>Расписание на {{ dash.date }} <span v-if="dash.group">— {{ dash.group }}</span></h3>
                <table class="dash-table">
                  <thead><tr><th>Время</th><th>Дисциплина</th><th>Тип</th><th>Аудитория</th><th>Преподаватель</th></tr></thead>
                  <tbody>
                    <tr v-for="item in dash.schedule" :key="item.id">
                      <td>{{ item.time_start ? item.time_start.substr(0,5) + '–' + item.time_end.substr(0,5) : (item.pair_number ? 'Пара ' + item.pair_number : '-') }}</td>
                      <td>{{ item.discipline || '-' }}</td>
                      <td>{{ item.lesson_type || '-' }}</td>
                      <td>{{ scheduleRooms(item) }}</td>
                      <td>{{ item.teacher_name || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-else-if="configs[section] && state[section]" class="card">
            <div class="card-header">
              <h3>{{ configs[section].title }}</h3>
              <div class="card-actions">
                <button v-if="configs[section].showReport" class="success" @click="exportExcel('report')">Отчёт по загруженности</button>
                <button v-if="['teachers','classrooms','schedule','software'].includes(section)" class="success" @click="exportExcel(section)">Экспорт Excel</button>
                <button v-if="isAdmin" class="danger" @click="truncateTable(section)">Очистить</button>
                <button v-if="isAdmin" class="primary" @click="openForm(section, null)">+ Добавить</button>
              </div>
            </div>
            <div class="filters-bar">
              <template v-for="f in configs[section].filters">
                <input v-if="f.type === 'text'" :key="f.field" class="filter-input" :placeholder="f.placeholder" v-model="filters[section][f.field]" @input="debouncedLoad(section)">
                <select v-else :key="f.field" class="filter-input" v-model="filters[section][f.field]" @change="debouncedLoad(section)">
                  <option value="">{{ f.placeholder }}</option>
                  <option v-for="o in (f.options || (filterOptions[section] && filterOptions[section][f.field]) || [])" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </template>
            </div>

            <div v-if="section === 'classrooms'" class="free-search card">
              <h3>Поиск свободной аудитории</h3>
              <div class="filters-bar">
                <label class="free-label">Дата <input type="date" v-model="free.date"></label>
                <label class="free-label">Пара
                  <select v-model="free.pair_number">
                    <option value="">Любая</option>
                    <option v-for="n in 8" :key="n" :value="n">{{ n }}</option>
                  </select>
                </label>
                <label class="free-label">Корпус
                  <select v-model="free.building">
                    <option value="">Любой</option>
                    <option value="Д">Д</option>
                    <option value="В">В</option>
                    <option value="БМ">БМ</option>
                  </select>
                </label>
                <label class="free-label">Проектор
                  <select v-model="free.has_projector">
                    <option value="">Не важно</option>
                    <option value="1">Есть</option>
                    <option value="0">Нет</option>
                  </select>
                </label>
                <label class="free-label">Колонки
                  <select v-model="free.has_speakers">
                    <option value="">Не важно</option>
                    <option value="1">Есть</option>
                    <option value="0">Нет</option>
                  </select>
                </label>
                <label class="free-label">Мест от <input type="number" v-model="free.seats_min" min="0" style="width:80px"></label>
                <button class="primary" @click="loadFreeClassrooms">Найти</button>
              </div>
              <div v-if="free.loading" class="spinner"></div>
              <div v-else-if="free.searched">
                <p v-if="!free.results.length" style="text-align:center;color:#909399;margin-top:12px;">Свободных аудиторий не найдено</p>
                <table v-else class="free-table" style="margin-top:12px;width:100%;border-collapse:collapse;">
                  <thead><tr><th>Аудитория</th><th>Корпус</th><th>Тип</th><th>ПК</th><th>Мест</th><th>Проектор</th><th>Колонки</th></tr></thead>
                  <tbody>
                    <tr v-for="r in free.results" :key="r.id">
                      <td>{{ r.room_number }}</td>
                      <td>{{ r.building }}</td>
                      <td>{{ r.room_type }}</td>
                      <td>{{ r.computers_count }}</td>
                      <td>{{ r.seats }}</td>
                      <td v-html="r.has_projector ? '<span class=badge badge-success>Да</span>' : '<span class=badge>Нет</span>'"></td>
                      <td v-html="r.has_speakers ? '<span class=badge badge-success>Да</span>' : '<span class=badge>Нет</span>'"></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="pagination pagination-top">
              <template v-for="(b, i) in pagerButtons(section)">
                <span v-if="b.page === -1" :key="'d'+i" class="page-dots">...</span>
                <button v-else :key="b.page" class="page-btn" :class="{ primary: b.page === state[section].page, outline: b.page !== state[section].page }" :disabled="b.disabled" @click="goPage(section, b.page)">{{ b.label }}</button>
              </template>
            </div>

            <div class="table-wrap">
              <table>
                <thead><tr><th>№</th><th v-for="c in configs[section].columns" :key="c.label">{{ c.label }}</th><th v-if="isAdmin"></th></tr></thead>
                <tbody>
                  <tr v-if="state[section].editingItem && !state[section].editingItem.id" class="edit-row">
                    <td :colspan="configs[section].columns.length + 2">
                      <div class="edit-grid">
                        <div v-for="f in configs[section].formFields" :key="f.field" class="edit-field">
                          <label>{{ f.label }}</label>
                          <select v-if="f.type === 'select'" v-model="state[section].editingItem[f.field]">
                            <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                          </select>
                          <input v-else-if="f.type === 'checkbox'" type="checkbox" v-model="state[section].editingItem[f.field]">
                          <input v-else v-model="state[section].editingItem[f.field]">
                        </div>
                      </div>
                      <div class="edit-actions">
                        <button class="primary" @click="saveForm(section)">✓ Сохранить</button>
                        <button class="outline" @click="closeForm(section)">✕ Отмена</button>
                      </div>
                    </td>
                  </tr>
                  <template v-for="(item, i) in state[section].items">
                    <tr v-if="state[section].editingItem && state[section].editingItem.id === item.id" :key="'edit-' + item.id" class="edit-row">
                      <td :colspan="configs[section].columns.length + 2">
                        <div class="edit-grid">
                          <div v-for="f in configs[section].formFields" :key="f.field" class="edit-field">
                            <label>{{ f.label }}</label>
                            <select v-if="f.type === 'select'" v-model="state[section].editingItem[f.field]">
                              <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <input v-else-if="f.type === 'checkbox'" type="checkbox" v-model="state[section].editingItem[f.field]">
                            <input v-else v-model="state[section].editingItem[f.field]">
                          </div>
                        </div>
                        <div class="edit-actions">
                          <button class="primary" @click="saveForm(section)">✓ Сохранить</button>
                          <button class="outline" @click="closeForm(section)">✕ Отмена</button>
                        </div>
                      </td>
                    </tr>
                    <tr v-else :key="item.id" class="data-row" :class="rowClass(item)">
                      <td class="row-num">{{ ((state[section].page - 1) * state[section].perPage) + i + 1 }}</td>
                      <td v-for="c in configs[section].columns" :key="c.label" v-html="cellValue(item, c)"></td>
                      <td v-if="isAdmin" class="row-actions">
                        <button class="outline" @click="openForm(section, item)">Ред</button>
                        <button class="danger" @click="deleteRow(section, item.id)">Уд</button>
                      </td>
                    </tr>
                  </template>
                  <tr v-if="!state[section].items.length && !state[section].editingItem"><td colspan="99" style="text-align:center;color:#999;">Нет данных</td></tr>
                </tbody>
              </table>
            </div>

            <div class="pagination pagination-bottom">
              <template v-for="(b, i) in pagerButtons(section)">
                <span v-if="b.page === -1" :key="'d'+i" class="page-dots">...</span>
                <button v-else :key="b.page" class="page-btn" :class="{ primary: b.page === state[section].page, outline: b.page !== state[section].page }" :disabled="b.disabled" @click="goPage(section, b.page)">{{ b.label }}</button>
              </template>
            </div>
            <div class="per-page-row">
              <label class="per-page-label">Строк:
                <select class="filter-input" :id="'per-page-' + section" @change="onPerPageChange(section)">
                  <option value="15">15</option>
                  <option value="50" selected>50</option>
                  <option value="100">100</option>
                </select>
              </label>
            </div>
          </div>

          <div v-else-if="section === 'import'" class="card">
            <h3>Импорт данных</h3>
            <div class="form-group"><label>Тип данных</label>
              <select v-model="importCfg.type">
                <option value="teachers">Преподаватели</option>
                <option value="classrooms">Аудитории</option>
                <option value="schedule">Расписание</option>
                <option value="software">Программное обеспечение</option>
              </select>
            </div>
            <div class="form-group"><label>Файл (.xlsx, .xls)</label>
              <input type="file" accept=".xlsx,.xls" @change="onFileChange">
            </div>
            <div class="form-group">
              <label><input type="checkbox" v-model="importCfg.createMissing"> Создавать отсутствующих преподавателей из расписания</label>
            </div>
            <div v-if="importCfg.type === 'schedule'" class="form-group">
              <label><input type="checkbox" v-model="importCfg.replace"> Заменить (удалить записи этого файла, которых больше нет)</label>
            </div>
            <button class="primary" :disabled="importCfg.busy" @click="doImport">Загрузить</button>
            <div class="import-progress" v-if="importCfg.progress"><div class="spinner"></div><span>Импорт данных...</span></div>
            <div v-if="importCfg.result" v-html="importCfg.result" style="margin-top:12px"></div>
          </div>

        </div>
      </div>
      <button class="theme-toggle" @click="toggleTheme" :title="theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'">{{ theme === 'dark' ? '☾' : '☀' }}</button>
    </div>
  </div>
  `
});