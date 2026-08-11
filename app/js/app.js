/**
 * Vue 2 приложение "Учебный отдел ВШПМ"
 * Компоненты и роутинг на клиенте
 */

// --- Компонент: Форма входа ---
Vue.component('login-form', {
    template: `
    <div class="login-box">
        <h3>Вход в систему</h3>
        <el-form ref="form" :model="form" :rules="rules" label-position="top">
            <el-form-item label="Логин" prop="username">
                <el-input v-model="form.username" placeholder="Введите логин"></el-input>
            </el-form-item>
            <el-form-item label="Пароль" prop="password">
                <el-input v-model="form.password" type="password" placeholder="Введите пароль" @keyup.enter.native="submit"></el-input>
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="submit" :loading="loading" style="width:100%">Войти</el-button>
            </el-form-item>
        </el-form>
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" style="margin-top:12px"></el-alert>
    </div>`,
    data() {
        return {
            form: { username: '', password: '' },
            rules: {
                username: [{ required: true, message: 'Введите логин', trigger: 'blur' }],
                password: [{ required: true, message: 'Введите пароль', trigger: 'blur' }],
            },
            loading: false,
            error: '',
        };
    },
    methods: {
        submit() {
            this.$refs.form.validate(async (valid) => {
                if (!valid) return;
                this.loading = true;
                this.error = '';
                try {
                    await API.login(this.form.username, this.form.password);
                    this.$emit('login-success');
                } catch (e) {
                    this.error = e.message;
                }
                this.loading = false;
            });
        }
    }
});

// --- Компонент: Дашборд ---
Vue.component('dashboard-page', {
    template: `
    <div>
        <div class="page-header"><h3>Главная панель</h3></div>
        <el-row :gutter="20">
            <el-col :span="6" v-for="s in stats" :key="s.label">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </el-card>
            </el-col>
        </el-row>
        <el-row :gutter="20" style="margin-top:20px">
            <el-col :span="12">
                <el-card header="Поиск свободной аудитории">
                    <el-form :inline="true" size="small">
                        <el-form-item label="Дата">
                            <el-date-picker v-model="freeSearch.date" type="date" value-format="yyyy-MM-dd" placeholder="Выберите дату"></el-date-picker>
                        </el-form-item>
                        <el-form-item label="Пара">
                            <el-select v-model="freeSearch.pair_number" placeholder="Номер пары" clearable>
                                <el-option v-for="n in 6" :key="n" :label="'Пара ' + n" :value="n"></el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="findFree" icon="el-icon-search">Найти</el-button>
                        </el-form-item>
                    </el-form>
                    <el-table :data="freeRooms" border stripe size="small" v-loading="freeLoading">
                        <el-table-column prop="room_number" label="Аудитория" width="90"></el-table-column>
                        <el-table-column prop="building" label="Корпус" width="70"></el-table-column>
                        <el-table-column prop="room_type" label="Тип" width="140"></el-table-column>
                        <el-table-column prop="seats" label="Мест" width="60"></el-table-column>
                        <el-table-column label="Проектор" width="80">
                            <template slot-scope="s">{{ s.row.has_projector ? 'Да' : 'Нет' }}</template>
                        </el-table-column>
                        <el-table-column label="Колонки" width="80">
                            <template slot-scope="s">{{ s.row.has_speakers ? 'Да' : 'Нет' }}</template>
                        </el-table-column>
                        <el-table-column prop="computers_count" label="ПК" width="60"></el-table-column>
                    </el-table>
                    <el-empty v-if="freeRooms !== null && freeRooms.length === 0" description="Нет свободных аудиторий"></el-empty>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card header="Последние переносы/отмены">
                    <el-table :data="transfers" border stripe size="small" v-loading="transLoading" :row-class-name="transferRowClass">
                        <el-table-column prop="date" label="Дата" width="100"></el-table-column>
                        <el-table-column prop="room_number" label="Ауд." width="70"></el-table-column>
                        <el-table-column prop="teacher_name" label="Преподаватель" min-width="140"></el-table-column>
                        <el-table-column label="Статус" width="90">
                            <template slot-scope="s">
                                <span :class="s.row.transfer_cancel === 'перенос' ? 'transfer-tag' : 'cancel-tag'">
                                    {{ s.row.transfer_cancel }}
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>`,
    data() {
        return {
            stats: [
                { label: 'Аудиторий', value: 0 },
                { label: 'Преподавателей', value: 0 },
                { label: 'Записей расписания', value: 0 },
                { label: 'Записей ПО', value: 0 },
            ],
            freeSearch: { date: '', pair_number: null },
            freeRooms: null,
            freeLoading: false,
            transfers: [],
            transLoading: false,
        };
    },
    mounted() {
        this.loadStats();
        this.loadTransfers();
    },
    methods: {
        async loadStats() {
            try {
                const [cr, t, s, sw] = await Promise.all([
                    API.getClassrooms({ per_page: 1 }),
                    API.getTeachers({ per_page: 1 }),
                    API.getSchedule({ per_page: 1 }),
                    API.getSoftware({ per_page: 1 }),
                ]);
                this.stats[0].value = cr.data.pagination.total;
                this.stats[1].value = t.data.pagination.total;
                this.stats[2].value = s.data.pagination.total;
                this.stats[3].value = sw.data.pagination.total;
            } catch (e) { /* тихо */ }
        },
        async loadTransfers() {
            this.transLoading = true;
            try {
                const r = await API.getSchedule({ transfer_cancel: 'перенос', per_page: 5 });
                const r2 = await API.getSchedule({ transfer_cancel: 'отмена', per_page: 5 });
                this.transfers = [...(r.data.items || []), ...(r2.data.items || [])].slice(0, 5);
            } catch (e) { /* тихо */ }
            this.transLoading = false;
        },
        async findFree() {
            if (!this.freeSearch.date) {
                this.$message.warning('Выберите дату');
                return;
            }
            this.freeLoading = true;
            try {
                const r = await API.getFreeClassrooms(this.freeSearch);
                this.freeRooms = r.data || [];
            } catch (e) {
                this.$message.error(e.message);
            }
            this.freeLoading = false;
        },
        transferRowClass({ row }) {
            if (row.transfer_cancel === 'перенос') return 'transfer-row';
            if (row.transfer_cancel === 'отмена') return 'cancel-row';
            return '';
        }
    }
});

// --- Компонент: Расписание ---
Vue.component('schedule-page', {
    template: `
    <div>
        <div class="page-header">
            <h3>Расписание занятий</h3>
            <el-button v-if="isAdmin" type="primary" size="small" icon="el-icon-plus" @click="openDialog()">Добавить запись</el-button>
        </div>
        <div class="filter-bar">
            <el-date-picker v-model="filters.date" type="date" value-format="yyyy-MM-dd" placeholder="Дата" size="small" clearable @change="load"></el-date-picker>
            <el-select v-model="filters.building" placeholder="Корпус" size="small" clearable @change="load">
                <el-option label="Джамбула" value="Д"></el-option>
                <el-option label="Вознесенский" value="В"></el-option>
            </el-select>
            <el-select v-model="filters.lesson_type" placeholder="Вид занятия" size="small" clearable @change="load">
                <el-option v-for="lt in lessonTypes" :key="lt" :label="lt" :value="lt"></el-option>
            </el-select>
            <el-select v-model="filters.transfer_cancel" placeholder="Перенос/отмена" size="small" clearable @change="load">
                <el-option label="Перенос" value="перенос"></el-option>
                <el-option label="Отмена" value="отмена"></el-option>
                <el-option label="Нет" value="нет"></el-option>
            </el-select>
            <el-button type="primary" size="small" icon="el-icon-search" @click="load">Поиск</el-button>
        </div>
        <el-table :data="items" border stripe v-loading="loading" :row-class-name="rowClass">
            <el-table-column prop="date" label="Дата" width="110" sortable></el-table-column>
            <el-table-column prop="day_of_week" label="День недели" width="110"></el-table-column>
            <el-table-column prop="room_number" label="Аудитория" width="90"></el-table-column>
            <el-table-column prop="building" label="Корпус" width="70"></el-table-column>
            <el-table-column prop="pair_number" label="Пара" width="55"></el-table-column>
            <el-table-column label="Время" width="130">
                <template slot-scope="s">{{ s.row.time_start }} - {{ s.row.time_end }}</template>
            </el-table-column>
            <el-table-column prop="lesson_type" label="Вид" width="70"></el-table-column>
            <el-table-column prop="teacher_name" label="Преподаватель" min-width="160"></el-table-column>
            <el-table-column prop="numerator_denominator" label="Числ/Знам" width="100"></el-table-column>
            <el-table-column label="Статус" width="100">
                <template slot-scope="s">
                    <span v-if="s.row.transfer_cancel === 'перенос'" class="transfer-tag">Перенос</span>
                    <span v-else-if="s.row.transfer_cancel === 'отмена'" class="cancel-tag">Отмена</span>
                    <span v-else>—</span>
                </template>
            </el-table-column>
            <el-table-column v-if="isAdmin" label="Действия" width="160" fixed="right">
                <template slot-scope="s">
                    <el-button size="mini" @click="openDialog(s.row)">Изм.</el-button>
                    <el-button size="mini" type="danger" @click="del(s.row.id)">Удалить</el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-pagination style="margin-top:16px;text-align:right"
            :current-page="page" :page-size="perPage" :total="total"
            layout="total, prev, pager, next" @current-change="onPageChange">
        </el-pagination>

        <el-dialog :title="editId ? 'Редактировать занятие' : 'Добавить занятие'" :visible.sync="dialogVisible" width="600px">
            <el-form :model="form" label-width="140px" size="small">
                <el-form-item label="Аудитория">
                    <el-select v-model="form.classroom_id" filterable placeholder="Выберите аудиторию">
                        <el-option v-for="c in classrooms" :key="c.id" :label="c.room_number + ' (' + c.building + ')'" :value="c.id"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Преподаватель">
                    <el-select v-model="form.teacher_id" filterable placeholder="Выберите преподавателя" clearable>
                        <el-option v-for="t in teachers" :key="t.id" :label="t.last_name + ' ' + t.first_name" :value="t.id"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Дата"><el-date-picker v-model="form.date" type="date" value-format="yyyy-MM-dd"></el-date-picker></el-form-item>
                <el-form-item label="День недели"><el-input v-model="form.day_of_week"></el-input></el-form-item>
                <el-form-item label="Номер пары"><el-input-number v-model="form.pair_number" :min="1" :max="8"></el-input-number></el-form-item>
                <el-form-item label="Время начала"><el-time-picker v-model="form.time_start" value-format="HH:mm:ss" placeholder="Начало"></el-time-picker></el-form-item>
                <el-form-item label="Время окончания"><el-time-picker v-model="form.time_end" value-format="HH:mm:ss" placeholder="Конец"></el-time-picker></el-form-item>
                <el-form-item label="Вид занятия">
                    <el-select v-model="form.lesson_type">
                        <el-option v-for="lt in lessonTypes" :key="lt" :label="lt" :value="lt"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Числитель/Знаменатель">
                    <el-select v-model="form.numerator_denominator" clearable>
                        <el-option label="Числитель" value="числитель"></el-option>
                        <el-option label="Знаменатель" value="знаменатель"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Перенос/отмена">
                    <el-select v-model="form.transfer_cancel">
                        <el-option label="Нет" value="нет"></el-option>
                        <el-option label="Перенос" value="перенос"></el-option>
                        <el-option label="Отмена" value="отмена"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Нестандартное время">
                    <el-switch v-model="form.is_nonstandard_time" :active-value="1" :inactive-value="0"></el-switch>
                </el-form-item>
            </el-form>
            <span slot="footer">
                <el-button @click="dialogVisible = false">Отмена</el-button>
                <el-button type="primary" @click="save" :loading="saving">Сохранить</el-button>
            </span>
        </el-dialog>
    </div>`,
    data() {
        return {
            filters: { date: '', building: '', lesson_type: '', transfer_cancel: '' },
            items: [], loading: false, page: 1, perPage: 20, total: 0,
            lessonTypes: ['лекц.', 'прак.', 'лаб.', 'конс.', 'экз.', 'ДО'],
            dialogVisible: false, editId: null, saving: false,
            form: { classroom_id: null, teacher_id: null, date: '', day_of_week: '', pair_number: 1,
                time_start: '', time_end: '', lesson_type: '', numerator_denominator: null,
                transfer_cancel: 'нет', is_nonstandard_time: 0 },
            classrooms: [], teachers: [],
        };
    },
    computed: {
        isAdmin() { return app.currentUser && app.currentUser.role === 'admin'; }
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const params = { page: this.page, per_page: this.perPage };
                Object.keys(this.filters).forEach(k => { if (this.filters[k]) params[k] = this.filters[k]; });
                const r = await API.getSchedule(params);
                this.items = r.data.items;
                this.total = r.data.pagination.total;
            } catch (e) { this.$message.error(e.message); }
            this.loading = false;
        },
        onPageChange(p) { this.page = p; this.load(); },
        rowClass({ row }) {
            if (row.transfer_cancel === 'перенос') return 'transfer-row';
            if (row.transfer_cancel === 'отмена') return 'cancel-row';
            return '';
        },
        async openDialog(row) {
            this.editId = row ? row.id : null;
            if (row) {
                this.form = {
                    classroom_id: row.classroom_id, teacher_id: row.teacher_id,
                    date: row.date, day_of_week: row.day_of_week, pair_number: row.pair_number,
                    time_start: row.time_start, time_end: row.time_end,
                    lesson_type: row.lesson_type, numerator_denominator: row.numerator_denominator,
                    transfer_cancel: row.transfer_cancel, is_nonstandard_time: row.is_nonstandard_time,
                };
            } else {
                this.form = { classroom_id: null, teacher_id: null, date: '', day_of_week: '', pair_number: 1,
                    time_start: '', time_end: '', lesson_type: '', numerator_denominator: null,
                    transfer_cancel: 'нет', is_nonstandard_time: 0 };
            }
            try {
                const [cr, t] = await Promise.all([API.getClassrooms({ per_page: 200 }), API.getTeachers({ per_page: 200 })]);
                this.classrooms = cr.data.items;
                this.teachers = t.data.items;
            } catch (e) { /* */ }
            this.dialogVisible = true;
        },
        async save() {
            this.saving = true;
            try {
                const data = { ...this.form };
                if (!data.teacher_id) data.teacher_id = null;
                if (this.editId) {
                    await API.updateScheduleItem(this.editId, data);
                } else {
                    await API.createScheduleItem(data);
                }
                this.dialogVisible = false;
                this.$message.success(this.editId ? 'Запись обновлена' : 'Запись добавлена');
                this.load();
            } catch (e) { this.$message.error(e.message); }
            this.saving = false;
        },
        async del(id) {
            try {
                await this.$confirm('Удалить запись расписания?', 'Подтверждение', { type: 'warning' });
                await API.deleteScheduleItem(id);
                this.$message.success('Запись удалена');
                this.load();
            } catch (e) { if (e !== 'cancel') this.$message.error(e.message); }
        }
    }
});

// --- Компонент: Аудитории ---
Vue.component('classrooms-page', {
    template: `
    <div>
        <div class="page-header">
            <h3>Аудитории</h3>
            <el-button v-if="isAdmin" type="primary" size="small" icon="el-icon-plus" @click="openDialog()">Добавить аудиторию</el-button>
        </div>
        <div class="filter-bar">
            <el-input v-model="filters.room_number" placeholder="Номер ауд." size="small" clearable style="width:140px" @change="load"></el-input>
            <el-select v-model="filters.building" placeholder="Корпус" size="small" clearable @change="load">
                <el-option label="Джамбула" value="Д"></el-option>
                <el-option label="Вознесенский" value="В"></el-option>
            </el-select>
            <el-select v-model="filters.room_type" placeholder="Тип помещения" size="small" clearable @change="load">
                <el-option v-for="rt in roomTypes" :key="rt" :label="rt" :value="rt"></el-option>
            </el-select>
            <el-select v-model="filters.has_projector" placeholder="Проектор" size="small" clearable @change="load">
                <el-option label="Есть" :value="1"></el-option>
                <el-option label="Нет" :value="0"></el-option>
            </el-select>
            <el-button type="primary" size="small" icon="el-icon-search" @click="load">Поиск</el-button>
        </div>
        <el-table :data="items" border stripe v-loading="loading">
            <el-table-column prop="room_number" label="Номер" width="90"></el-table-column>
            <el-table-column label="Корпус" width="110">
                <template slot-scope="s">{{ s.row.building === 'Д' ? 'Джамбула' : 'Вознесенский' }}</template>
            </el-table-column>
            <el-table-column prop="room_type" label="Тип помещения" width="160"></el-table-column>
            <el-table-column prop="seats" label="Мест" width="60"></el-table-column>
            <el-table-column label="Проектор" width="80">
                <template slot-scope="s">{{ s.row.has_projector ? 'Да' : 'Нет' }}</template>
            </el-table-column>
            <el-table-column label="Колонки" width="80">
                <template slot-scope="s">{{ s.row.has_speakers ? 'Да' : 'Нет' }}</template>
            </el-table-column>
            <el-table-column prop="computers_count" label="ПК" width="60"></el-table-column>
            <el-table-column prop="software_installed" label="ПО" min-width="200" show-overflow-tooltip></el-table-column>
            <el-table-column v-if="isAdmin" label="Действия" width="160" fixed="right">
                <template slot-scope="s">
                    <el-button size="mini" @click="openDialog(s.row)">Изм.</el-button>
                    <el-button size="mini" type="danger" @click="del(s.row.id)">Удалить</el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-pagination style="margin-top:16px;text-align:right"
            :current-page="page" :page-size="perPage" :total="total"
            layout="total, prev, pager, next" @current-change="onPageChange">
        </el-pagination>

        <el-dialog :title="editId ? 'Редактировать аудиторию' : 'Добавить аудиторию'" :visible.sync="dialogVisible" width="500px">
            <el-form :model="form" label-width="160px" size="small">
                <el-form-item label="Номер аудитории" required>
                    <el-input v-model="form.room_number"></el-input>
                </el-form-item>
                <el-form-item label="Корпус" required>
                    <el-select v-model="form.building">
                        <el-option label="Д - Джамбула" value="Д"></el-option>
                        <el-option label="В - Вознесенский" value="В"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Тип помещения" required>
                    <el-select v-model="form.room_type">
                        <el-option v-for="rt in roomTypes" :key="rt" :label="rt" :value="rt"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Посадочных мест"><el-input-number v-model="form.seats" :min="0"></el-input-number></el-form-item>
                <el-form-item label="Компьютеров"><el-input-number v-model="form.computers_count" :min="0"></el-input-number></el-form-item>
                <el-form-item label="Проектор"><el-switch v-model="form.has_projector" :active-value="1" :inactive-value="0"></el-switch></el-form-item>
                <el-form-item label="Колонки"><el-switch v-model="form.has_speakers" :active-value="1" :inactive-value="0"></el-switch></el-form-item>
                <el-form-item label="Установленное ПО"><el-input type="textarea" v-model="form.software_installed" rows="3"></el-input></el-form-item>
            </el-form>
            <span slot="footer">
                <el-button @click="dialogVisible = false">Отмена</el-button>
                <el-button type="primary" @click="save" :loading="saving">Сохранить</el-button>
            </span>
        </el-dialog>
    </div>`,
    data() {
        return {
            filters: { room_number: '', building: '', room_type: '', has_projector: '' },
            items: [], loading: false, page: 1, perPage: 20, total: 0,
            roomTypes: ['компьютерный класс', 'лаборатория', 'лекционная аудитория'],
            dialogVisible: false, editId: null, saving: false,
            form: { room_number: '', building: 'Д', room_type: '', seats: null, computers_count: 0,
                has_projector: 0, has_speakers: 0, software_installed: '' },
        };
    },
    computed: {
        isAdmin() { return app.currentUser && app.currentUser.role === 'admin'; }
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const params = { page: this.page, per_page: this.perPage };
                Object.keys(this.filters).forEach(k => { if (this.filters[k] !== '') params[k] = this.filters[k]; });
                const r = await API.getClassrooms(params);
                this.items = r.data.items;
                this.total = r.data.pagination.total;
            } catch (e) { this.$message.error(e.message); }
            this.loading = false;
        },
        onPageChange(p) { this.page = p; this.load(); },
        openDialog(row) {
            this.editId = row ? row.id : null;
            this.form = row ? { ...row } : { room_number: '', building: 'Д', room_type: '', seats: null,
                computers_count: 0, has_projector: 0, has_speakers: 0, software_installed: '' };
            this.dialogVisible = true;
        },
        async save() {
            if (!this.form.room_number || !this.form.room_type) {
                this.$message.warning('Заполните обязательные поля'); return;
            }
            this.saving = true;
            try {
                if (this.editId) {
                    await API.updateClassroom(this.editId, this.form);
                } else {
                    await API.createClassroom(this.form);
                }
                this.dialogVisible = false;
                this.$message.success(this.editId ? 'Аудитория обновлена' : 'Аудитория добавлена');
                this.load();
            } catch (e) { this.$message.error(e.message); }
            this.saving = false;
        },
        async del(id) {
            try {
                await this.$confirm('Удалить аудиторию? Это также удалит связанное расписание.', 'Подтверждение', { type: 'warning' });
                await API.deleteClassroom(id);
                this.$message.success('Аудитория удалена');
                this.load();
            } catch (e) { if (e !== 'cancel') this.$message.error(e.message); }
        }
    }
});

// --- Компонент: Преподаватели ---
Vue.component('teachers-page', {
    template: `
    <div>
        <div class="page-header">
            <h3>Преподаватели</h3>
            <el-button v-if="isAdmin" type="primary" size="small" icon="el-icon-plus" @click="openDialog()">Добавить преподавателя</el-button>
        </div>
        <div class="filter-bar">
            <el-input v-model="filters.search" placeholder="Поиск по ФИО" size="small" clearable style="width:200px" @change="load"></el-input>
            <el-select v-model="filters.department" placeholder="Кафедра" size="small" clearable @change="load">
                <el-option v-for="d in departments" :key="d" :label="d" :value="d"></el-option>
            </el-select>
            <el-select v-model="filters.employment_type" placeholder="Форма занятости" size="small" clearable @change="load">
                <el-option v-for="e in empTypes" :key="e" :label="e" :value="e"></el-option>
            </el-select>
            <el-button type="primary" size="small" icon="el-icon-search" @click="load">Поиск</el-button>
        </div>
        <el-table :data="items" border stripe v-loading="loading">
            <el-table-column prop="last_name" label="Фамилия" width="120"></el-table-column>
            <el-table-column prop="first_name" label="Имя" width="110"></el-table-column>
            <el-table-column prop="middle_name" label="Отчество" width="130"></el-table-column>
            <el-table-column prop="position" label="Должность" width="150" show-overflow-tooltip></el-table-column>
            <el-table-column prop="department" label="Кафедра" width="120" show-overflow-tooltip></el-table-column>
            <el-table-column prop="employment_type" label="Форма занятости" width="150"></el-table-column>
            <el-table-column prop="email" label="Email" width="180" show-overflow-tooltip></el-table-column>
            <el-table-column prop="phone" label="Телефон" width="130"></el-table-column>
            <el-table-column v-if="isAdmin" label="Действия" width="160" fixed="right">
                <template slot-scope="s">
                    <el-button size="mini" @click="openDialog(s.row)">Изм.</el-button>
                    <el-button size="mini" type="danger" @click="del(s.row.id)">Удалить</el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-pagination style="margin-top:16px;text-align:right"
            :current-page="page" :page-size="perPage" :total="total"
            layout="total, prev, pager, next" @current-change="onPageChange">
        </el-pagination>

        <el-dialog :title="editId ? 'Редактировать преподавателя' : 'Добавить преподавателя'" :visible.sync="dialogVisible" width="550px">
            <el-form :model="form" label-width="150px" size="small">
                <el-form-item label="Фамилия" required><el-input v-model="form.last_name"></el-input></el-form-item>
                <el-form-item label="Имя" required><el-input v-model="form.first_name"></el-input></el-form-item>
                <el-form-item label="Отчество"><el-input v-model="form.middle_name"></el-input></el-form-item>
                <el-form-item label="Должность"><el-input v-model="form.position"></el-input></el-form-item>
                <el-form-item label="Степень"><el-input v-model="form.degree"></el-input></el-form-item>
                <el-form-item label="Звание"><el-input v-model="form.title"></el-input></el-form-item>
                <el-form-item label="Кафедра">
                    <el-select v-model="form.department" clearable>
                        <el-option v-for="d in departments" :key="d" :label="d" :value="d"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Форма занятости">
                    <el-select v-model="form.employment_type" clearable>
                        <el-option v-for="e in empTypes" :key="e" :label="e" :value="e"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Email"><el-input v-model="form.email"></el-input></el-form-item>
                <el-form-item label="Телефон"><el-input v-model="form.phone"></el-input></el-form-item>
                <el-form-item label="Особые отметки"><el-input type="textarea" v-model="form.notes" rows="3"></el-input></el-form-item>
            </el-form>
            <span slot="footer">
                <el-button @click="dialogVisible = false">Отмена</el-button>
                <el-button type="primary" @click="save" :loading="saving">Сохранить</el-button>
            </span>
        </el-dialog>
    </div>`,
    data() {
        return {
            filters: { search: '', department: '', employment_type: '' },
            items: [], loading: false, page: 1, perPage: 20, total: 0,
            departments: ['ЖиМ СМИ','ГиСЭД','Графика','КиКТ','Реклама','ТПиПК','ТПП','ИиУС','ПОиУ'],
            empTypes: ['штатный','внешний совместитель','внутренний совместитель','ГПХ'],
            dialogVisible: false, editId: null, saving: false,
            form: { last_name: '', first_name: '', middle_name: '', position: '', degree: '', title: '',
                department: null, employment_type: null, email: '', phone: '', notes: '' },
        };
    },
    computed: {
        isAdmin() { return app.currentUser && app.currentUser.role === 'admin'; }
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const params = { page: this.page, per_page: this.perPage };
                Object.keys(this.filters).forEach(k => { if (this.filters[k]) params[k] = this.filters[k]; });
                const r = await API.getTeachers(params);
                this.items = r.data.items;
                this.total = r.data.pagination.total;
            } catch (e) { this.$message.error(e.message); }
            this.loading = false;
        },
        onPageChange(p) { this.page = p; this.load(); },
        openDialog(row) {
            this.editId = row ? row.id : null;
            this.form = row ? { ...row } : { last_name: '', first_name: '', middle_name: '', position: '', degree: '',
                title: '', department: null, employment_type: null, email: '', phone: '', notes: '' };
            this.dialogVisible = true;
        },
        async save() {
            if (!this.form.last_name || !this.form.first_name) {
                this.$message.warning('Фамилия и Имя обязательны'); return;
            }
            this.saving = true;
            try {
                if (this.editId) {
                    await API.updateTeacher(this.editId, this.form);
                } else {
                    await API.createTeacher(this.form);
                }
                this.dialogVisible = false;
                this.$message.success(this.editId ? 'Преподаватель обновлен' : 'Преподаватель добавлен');
                this.load();
            } catch (e) { this.$message.error(e.message); }
            this.saving = false;
        },
        async del(id) {
            try {
                await this.$confirm('Удалить преподавателя?', 'Подтверждение', { type: 'warning' });
                await API.deleteTeacher(id);
                this.$message.success('Преподаватель удален');
                this.load();
            } catch (e) { if (e !== 'cancel') this.$message.error(e.message); }
        }
    }
});

// --- Компонент: ПО ---
Vue.component('software-page', {
    template: `
    <div>
        <div class="page-header">
            <h3>Программное обеспечение</h3>
            <el-button v-if="isAdmin" type="primary" size="small" icon="el-icon-plus" @click="openDialog()">Добавить ПО</el-button>
        </div>
        <div class="filter-bar">
            <el-input v-model="filters.room_number" placeholder="Номер ауд." size="small" clearable style="width:140px" @change="load"></el-input>
            <el-select v-model="filters.building" placeholder="Корпус" size="small" clearable @change="load">
                <el-option label="Джамбула" value="Д"></el-option>
                <el-option label="Вознесенский" value="В"></el-option>
            </el-select>
            <el-input v-model="filters.name" placeholder="Название ПО" size="small" clearable style="width:200px" @change="load"></el-input>
            <el-button type="primary" size="small" icon="el-icon-search" @click="load">Поиск</el-button>
        </div>
        <el-table :data="items" border stripe v-loading="loading">
            <el-table-column type="index" width="50" label="№"></el-table-column>
            <el-table-column prop="room_number" label="Аудитория" width="100"></el-table-column>
            <el-table-column prop="building" label="Корпус" width="70"></el-table-column>
            <el-table-column prop="name" label="Наименование ПО" min-width="250"></el-table-column>
            <el-table-column prop="notes" label="Примечания" min-width="200" show-overflow-tooltip></el-table-column>
            <el-table-column v-if="isAdmin" label="Действия" width="160" fixed="right">
                <template slot-scope="s">
                    <el-button size="mini" @click="openDialog(s.row)">Изм.</el-button>
                    <el-button size="mini" type="danger" @click="del(s.row.id)">Удалить</el-button>
                </template>
            </el-table-column>
        </el-table>
        <el-pagination style="margin-top:16px;text-align:right"
            :current-page="page" :page-size="perPage" :total="total"
            layout="total, prev, pager, next" @current-change="onPageChange">
        </el-pagination>

        <el-dialog :title="editId ? 'Редактировать ПО' : 'Добавить ПО'" :visible.sync="dialogVisible" width="500px">
            <el-form :model="form" label-width="130px" size="small">
                <el-form-item label="Аудитория"><el-input v-model="form.room_number"></el-input></el-form-item>
                <el-form-item label="Корпус">
                    <el-select v-model="form.building" clearable>
                        <el-option label="Д - Джамбула" value="Д"></el-option>
                        <el-option label="В - Вознесенский" value="В"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="Наименование ПО" required><el-input v-model="form.name"></el-input></el-form-item>
                <el-form-item label="Примечания"><el-input type="textarea" v-model="form.notes" rows="3"></el-input></el-form-item>
            </el-form>
            <span slot="footer">
                <el-button @click="dialogVisible = false">Отмена</el-button>
                <el-button type="primary" @click="save" :loading="saving">Сохранить</el-button>
            </span>
        </el-dialog>
    </div>`,
    data() {
        return {
            filters: { room_number: '', building: '', name: '' },
            items: [], loading: false, page: 1, perPage: 20, total: 0,
            dialogVisible: false, editId: null, saving: false,
            form: { room_number: '', building: null, name: '', notes: '' },
        };
    },
    computed: {
        isAdmin() { return app.currentUser && app.currentUser.role === 'admin'; }
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const params = { page: this.page, per_page: this.perPage };
                Object.keys(this.filters).forEach(k => { if (this.filters[k]) params[k] = this.filters[k]; });
                const r = await API.getSoftware(params);
                this.items = r.data.items;
                this.total = r.data.pagination.total;
            } catch (e) { this.$message.error(e.message); }
            this.loading = false;
        },
        onPageChange(p) { this.page = p; this.load(); },
        openDialog(row) {
            this.editId = row ? row.id : null;
            this.form = row ? { ...row } : { room_number: '', building: null, name: '', notes: '' };
            this.dialogVisible = true;
        },
        async save() {
            if (!this.form.name) { this.$message.warning('Введите название ПО'); return; }
            this.saving = true;
            try {
                if (this.editId) {
                    await API.updateSoftware(this.editId, this.form);
                } else {
                    await API.createSoftware(this.form);
                }
                this.dialogVisible = false;
                this.$message.success(this.editId ? 'ПО обновлено' : 'ПО добавлено');
                this.load();
            } catch (e) { this.$message.error(e.message); }
            this.saving = false;
        },
        async del(id) {
            try {
                await this.$confirm('Удалить запись ПО?', 'Подтверждение', { type: 'warning' });
                await API.deleteSoftware(id);
                this.$message.success('Запись ПО удалена');
                this.load();
            } catch (e) { if (e !== 'cancel') this.$message.error(e.message); }
        }
    }
});

// --- Компонент: Импорт ---
Vue.component('import-page', {
    template: `
    <div>
        <div class="page-header"><h3>Импорт данных</h3></div>
        <el-tabs>
            <el-tab-pane label="Преподаватели">
                <el-card>
                    <p style="margin-bottom:12px">Загрузите Excel-файл (.xls, .xlsx) со списком преподавателей. Ожидаемые столбцы: Фамилия, Имя, Отчество, Должность, Степень, Звание, Кафедра, Форма занятости, Email, Телефон, Примечания.</p>
                    <el-upload ref="uploadTeachers" :action="apiBase + '/import/teachers'" :auto-upload="false"
                        :on-success="onSuccess" :on-error="onError" :before-upload="beforeUpload"
                        accept=".xls,.xlsx" drag>
                        <i class="el-icon-upload"></i>
                        <div class="el-upload__text">Перетащите файл или <em>нажмите для выбора</em></div>
                    </el-upload>
                    <el-button type="primary" @click="submitUpload('uploadTeachers')" style="margin-top:12px">Загрузить</el-button>
                </el-card>
            </el-tab-pane>
            <el-tab-pane label="Аудитории">
                <el-card>
                    <p style="margin-bottom:12px">Excel-файл с техническими характеристиками аудиторий. Столбцы: Номер, Корпус, Тип, ПО, Мест, Проектор, Колонки, ПК.</p>
                    <el-upload ref="uploadClassrooms" :action="apiBase + '/import/classrooms'" :auto-upload="false"
                        :on-success="onSuccess" :on-error="onError" :before-upload="beforeUpload"
                        accept=".xls,.xlsx" drag>
                        <i class="el-icon-upload"></i>
                        <div class="el-upload__text">Перетащите файл или <em>нажмите для выбора</em></div>
                    </el-upload>
                    <el-button type="primary" @click="submitUpload('uploadClassrooms')" style="margin-top:12px">Загрузить</el-button>
                </el-card>
            </el-tab-pane>
            <el-tab-pane label="Расписание">
                <el-card>
                    <p style="margin-bottom:12px">Excel-файл расписания. Столбцы: Аудитория, Дата, День недели, Пара, Время начала, Время конца, Вид занятия, Преподаватель, Числ/Знам.</p>
                    <el-checkbox v-model="replaceSchedule" style="margin-bottom:12px">Заменить существующее расписание за период</el-checkbox>
                    <el-upload ref="uploadSchedule" :action="apiBase + '/import/schedule'" :auto-upload="false"
                        :on-success="onSuccess" :on-error="onError" :before-upload="beforeUpload"
                        accept=".xls,.xlsx" drag>
                        <i class="el-icon-upload"></i>
                        <div class="el-upload__text">Перетащите файл или <em>нажмите для выбора</em></div>
                    </el-upload>
                    <el-button type="primary" @click="submitUpload('uploadSchedule')" style="margin-top:12px">Загрузить</el-button>
                </el-card>
            </el-tab-pane>
            <el-tab-pane label="Программное обеспечение">
                <el-card>
                    <p style="margin-bottom:12px">Excel-файл с ПО. Столбцы: Аудитория, Корпус, Наименование ПО, Примечания.</p>
                    <el-upload ref="uploadSoftware" :action="apiBase + '/import/software'" :auto-upload="false"
                        :on-success="onSuccess" :on-error="onError" :before-upload="beforeUpload"
                        accept=".xls,.xlsx" drag>
                        <i class="el-icon-upload"></i>
                        <div class="el-upload__text">Перетащите файл или <em>нажмите для выбора</em></div>
                    </el-upload>
                    <el-button type="primary" @click="submitUpload('uploadSoftware')" style="margin-top:12px">Загрузить</el-button>
                </el-card>
            </el-tab-pane>
        </el-tabs>
        <el-dialog title="Результат импорта" :visible.sync="resultVisible" width="500px">
            <p><strong>{{ resultMsg }}</strong></p>
            <p v-if="resultData">Создано: {{ resultData.imported }}, обновлено: {{ resultData.updated || 0 }}, ошибок: {{ (resultData.errors || []).length }}</p>
            <ul v-if="resultData && resultData.errors && resultData.errors.length">
                <li v-for="(e, i) in resultData.errors.slice(0, 10)" :key="i" style="color:red;font-size:12px">{{ e }}</li>
            </ul>
            <span slot="footer"><el-button @click="resultVisible = false">OK</el-button></span>
        </el-dialog>
    </div>`,
    data() {
        return {
            apiBase: '/api',
            replaceSchedule: false,
            resultVisible: false,
            resultMsg: '',
            resultData: null,
        };
    },
    methods: {
        beforeUpload(file) {
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['xls', 'xlsx'].includes(ext)) {
                this.$message.error('Поддерживаются только файлы .xls и .xlsx');
                return false;
            }
            return true;
        },
        submitUpload(refName) {
            const uploadComponent = this.$refs[refName];
            if (!uploadComponent.uploadFiles.length) {
                this.$message.warning('Выберите файл'); return;
            }
            const formData = new FormData();
            formData.append('file', uploadComponent.uploadFiles[0].raw);
            if (refName === 'uploadSchedule' && this.replaceSchedule) {
                formData.append('replace', 'true');
            }
            const url = uploadComponent.action;
            API.importFile(url, formData)
                .then(r => {
                    this.resultMsg = r.message;
                    this.resultData = r.data;
                    this.resultVisible = true;
                    uploadComponent.clearFiles();
                })
                .catch(e => this.$message.error(e.message));
        },
        onSuccess() {},
        onError(err) { this.$message.error('Ошибка загрузки: ' + (err.message || 'неизвестная ошибка')); }
    }
});

// --- Компонент: Экспорт ---
Vue.component('export-page', {
    template: `
    <div>
        <div class="page-header"><h3>Экспорт данных и отчеты</h3></div>
        <el-row :gutter="20">
            <el-col :span="8">
                <el-card header="Преподаватели">
                    <el-select v-model="exportFilters.department" placeholder="Кафедра" size="small" clearable style="width:100%;margin-bottom:12px">
                        <el-option v-for="d in departments" :key="d" :label="d" :value="d"></el-option>
                    </el-select>
                    <el-button type="primary" icon="el-icon-download" @click="exportData('teachers')" style="width:100%">Экспорт в Excel</el-button>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card header="Аудитории">
                    <p style="margin-bottom:12px;color:#909399">Экспорт всех аудиторий с характеристиками.</p>
                    <el-button type="primary" icon="el-icon-download" @click="exportData('classrooms')" style="width:100%">Экспорт в Excel</el-button>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card header="Расписание">
                    <el-date-picker v-model="exportFilters.dateRange" type="daterange" range-separator="—" start-placeholder="С" end-placeholder="По"
                        value-format="yyyy-MM-dd" size="small" style="width:100%;margin-bottom:12px">
                    </el-date-picker>
                    <el-button type="primary" icon="el-icon-download" @click="exportData('schedule')" style="width:100%">Экспорт в Excel</el-button>
                </el-card>
            </el-col>
        </el-row>
        <el-row :gutter="20" style="margin-top:20px">
            <el-col :span="8">
                <el-card header="Программное обеспечение">
                    <p style="margin-bottom:12px;color:#909399">Экспорт реестра ПО по аудиториям.</p>
                    <el-button type="primary" icon="el-icon-download" @click="exportData('software')" style="width:100%">Экспорт в Excel</el-button>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card header="Сводный отчет">
                    <p style="margin-bottom:12px;color:#909399">Загруженность аудиторий, переносы, отмены.</p>
                    <el-button type="success" icon="el-icon-document" @click="exportData('report')" style="width:100%">Сформировать отчет</el-button>
                </el-card>
            </el-col>
        </el-row>
    </div>`,
    data() {
        return {
            departments: ['ЖиМ СМИ','ГиСЭД','Графика','КиКТ','Реклама','ТПиПК','ТПП','ИиУС','ПОиУ'],
            exportFilters: { department: '', dateRange: null },
        };
    },
    methods: {
        exportData(type) {
            const params = {};
            if (type === 'schedule' && this.exportFilters.dateRange) {
                params.date_from = this.exportFilters.dateRange[0];
                params.date_to = this.exportFilters.dateRange[1];
            }
            if (type === 'teachers' && this.exportFilters.department) {
                params.department = this.exportFilters.department;
            }
            const url = API.getExportUrl(type, params);
            window.open(url, '_blank');
        }
    }
});

// --- Компонент: Пользователи ---
Vue.component('users-page', {
    template: `
    <div>
        <div class="page-header">
            <h3>Управление пользователями</h3>
            <el-button type="primary" size="small" icon="el-icon-plus" @click="openDialog()">Добавить пользователя</el-button>
        </div>
        <el-table :data="users" border stripe v-loading="loading">
            <el-table-column type="index" width="50"></el-table-column>
            <el-table-column prop="username" label="Логин" width="150"></el-table-column>
            <el-table-column prop="full_name" label="ФИО" width="200"></el-table-column>
            <el-table-column label="Роль" width="120">
                <template slot-scope="s">
                    <el-tag :type="s.row.role === 'admin' ? 'danger' : 'info'" size="small">
                        {{ s.row.role === 'admin' ? 'Администратор' : 'Пользователь' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="created_at" label="Создан" width="160"></el-table-column>
            <el-table-column label="Действия" width="160" fixed="right">
                <template slot-scope="s">
                    <el-button size="mini" @click="openDialog(s.row)">Изм.</el-button>
                    <el-button size="mini" type="danger" @click="del(s.row.id)" :disabled="s.row.id === currentUserId">Удалить</el-button>
                </template>
            </el-table-column>
        </el-table>

        <el-dialog :title="editId ? 'Редактировать пользователя' : 'Добавить пользователя'" :visible.sync="dialogVisible" width="450px">
            <el-form :model="form" label-width="120px" size="small">
                <el-form-item label="Логин" required><el-input v-model="form.username"></el-input></el-form-item>
                <el-form-item label="Пароль" :required="!editId">
                    <el-input v-model="form.password" type="password" :placeholder="editId ? 'Оставьте пустым, чтобы не менять' : ''"></el-input>
                </el-form-item>
                <el-form-item label="ФИО"><el-input v-model="form.full_name"></el-input></el-form-item>
                <el-form-item label="Роль" required>
                    <el-select v-model="form.role">
                        <el-option label="Администратор" value="admin"></el-option>
                        <el-option label="Пользователь" value="user"></el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <span slot="footer">
                <el-button @click="dialogVisible = false">Отмена</el-button>
                <el-button type="primary" @click="save" :loading="saving">Сохранить</el-button>
            </span>
        </el-dialog>
    </div>`,
    data() {
        return {
            users: [], loading: false,
            dialogVisible: false, editId: null, saving: false,
            form: { username: '', password: '', full_name: '', role: 'user' },
        };
    },
    computed: {
        currentUserId() { return app.currentUser ? app.currentUser.id : null; }
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const r = await API.getUsers();
                this.users = r.data || [];
            } catch (e) { this.$message.error(e.message); }
            this.loading = false;
        },
        openDialog(row) {
            this.editId = row ? row.id : null;
            this.form = row ? { username: row.username, password: '', full_name: row.full_name, role: row.role }
                : { username: '', password: '', full_name: '', role: 'user' };
            this.dialogVisible = true;
        },
        async save() {
            if (!this.form.username) { this.$message.warning('Введите логин'); return; }
            if (!this.editId && !this.form.password) { this.$message.warning('Введите пароль'); return; }
            this.saving = true;
            try {
                if (this.editId) {
                    await API.updateUser(this.editId, this.form);
                } else {
                    await API.createUser(this.form);
                }
                this.dialogVisible = false;
                this.$message.success(this.editId ? 'Пользователь обновлен' : 'Пользователь создан');
                this.load();
            } catch (e) { this.$message.error(e.message); }
            this.saving = false;
        },
        async del(id) {
            try {
                await this.$confirm('Удалить пользователя?', 'Подтверждение', { type: 'warning' });
                await API.deleteUser(id);
                this.$message.success('Пользователь удален');
                this.load();
            } catch (e) { if (e !== 'cancel') this.$message.error(e.message); }
        }
    }
});

// --- Корневое Vue приложение ---
var app = new Vue({
    el: '#app',
    data: {
        currentUser: null,
        currentRoute: '/',
    },
    created() {
        this.checkAuth();
        this.updateRoute();
        window.addEventListener('hashchange', this.updateRoute);
    },
    methods: {
        async checkAuth() {
            try {
                const r = await API.getMe();
                if (r.data && r.data.id) {
                    this.currentUser = r.data;
                }
            } catch (e) { /* не авторизован */ }
        },
        onLogin() {
            this.checkAuth();
        },
        async logout() {
            await API.logout();
            this.currentUser = null;
        },
        updateRoute() {
            this.currentRoute = window.location.hash.slice(1) || '/';
        },
    },
    computed: {
        activeMenu() {
            return this.currentRoute;
        }
    }
});