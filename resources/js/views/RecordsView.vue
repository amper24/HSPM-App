<template>
  <section class="card">
    <div class="card-header"><h3>{{ config.title }}</h3><div class="card-actions">
      <button v-if="resource !== 'users'" class="success" @click="exportFile(resource)">Экспорт Excel</button>
      <button v-if="config.showReport" class="success" @click="exportFile('report')">Отчёт по загруженности</button>
      <button v-if="isAdmin && resource !== 'users'" class="danger" @click="clearAll">Очистить</button>
      <button v-if="isAdmin" class="primary" @click="editing = {}">+ Добавить</button>
    </div></div>
    <div class="filters-bar"><template v-for="field in config.filters">
      <input v-if="field.type === 'text'" :key="field.field" class="filter-input" :aria-label="field.placeholder" :placeholder="field.placeholder" :value="list.filters[field.field]" @input="filter(field.field, $event.target.value)">
      <select v-else :key="field.field" class="filter-input" :aria-label="field.placeholder" :value="list.filters[field.field] || ''" @change="filter(field.field, $event.target.value)"><option value="">{{ field.placeholder }}</option><option v-for="option in field.options || options[field.field] || []" :key="option.value" :value="option.value">{{ option.label }}</option></select>
    </template></div>
    <FreeClassrooms v-if="resource === 'classrooms'" />
    <p v-if="error || list.error" role="alert" class="alert alert-danger">{{ error || list.error }}</p>
    <RecordForm v-if="editing" :key="editing.id || 'new'" :record="editing" :fields="config.formFields" :busy="saving" :error="formError" @save="save" @cancel="editing = null" />
    <ListPagination v-bind="list" :disabled="list.loading" @page="page" @size="size" />
    <div v-if="list.loading" class="spinner"></div>
    <div class="table-wrap"><table>
      <thead><tr><th>№</th><th v-for="column in config.columns" :key="column.label">{{ column.label }}</th><th v-if="isAdmin">Действия</th></tr></thead>
      <tbody><tr v-for="(item, index) in list.items" :key="item.id" :class="{ 'row-transfer': item.transfer_cancel === 'перенос', 'row-cancel': item.transfer_cancel === 'отмена' }"><td>{{ (list.page - 1) * list.perPage + index + 1 }}</td><td v-for="column in config.columns" :key="column.label">{{ cellText(item, column) }}</td><td v-if="isAdmin" class="row-actions"><button class="outline" @click="edit(item)">Ред</button><button class="danger" @click="remove(item.id)">Уд</button></td></tr>
        <tr v-if="!list.items.length && !list.loading"><td :colspan="config.columns.length + 2">Нет данных</td></tr>
      </tbody>
    </table></div>
  </section>
</template>
<script>
import { resources } from '../config/resources';
import { education } from '../services/education';
import { cellText, scheduleRooms } from '../utils/format';
import RecordForm from '../components/RecordForm.vue';
import ListPagination from '../components/ListPagination.vue';
import FreeClassrooms from '../components/FreeClassrooms.vue';

export default {
  components: { RecordForm, ListPagination, FreeClassrooms }, props: { resource: { type: String, required: true } },
  data: () => ({ editing: null, saving: false, formError: '', error: '', options: {}, debounceTimer: null }),
  computed: {
    config() { return resources[this.resource]; },
    list() { return this.$store.state.records.lists[this.resource]; },
    isAdmin() { return this.$store.getters['auth/isAdmin']; },
  },
  created() { this.$store.commit('records/ensure', this.resource); this.load(); this.loadOptions(); },
  beforeDestroy() { clearTimeout(this.debounceTimer); },
  methods: {
    cellText,
    load() { return this.$store.dispatch('records/load', this.resource); },
    patch(values) { this.$store.commit('records/patch', { resource: this.resource, values }); },
    filter(field, value) { this.patch({ filters: { ...this.list.filters, [field]: value }, page: 1 }); clearTimeout(this.debounceTimer); this.debounceTimer = setTimeout(this.load, 300); },
    page(page) { this.patch({ page }); this.load(); },
    size(perPage) { this.patch({ perPage, page: 1 }); this.load(); },
    edit(item) { this.formError = ''; this.editing = { ...item, classrooms: scheduleRooms(item) === '—' ? '' : scheduleRooms(item) }; },
    async loadOptions() {
      const endpoints = { teachers: { department: 'departments', degree: 'degrees', title: 'titles', employment_type: 'employment-types' }, classrooms: { room_type: 'room-types' }, software: { building: 'buildings' } };
      try {
        await Promise.all(Object.entries(endpoints[this.resource] || {}).map(async ([field, endpoint]) => {
          const response = await education.options(this.resource, endpoint);
          this.$set(this.options, field, response.data.map(value => ({ value, label: value })));
        }));
      } catch (error) { this.error = error.message; }
    },
    async save(record) {
      this.saving = true; this.formError = '';
      try { await this.$store.dispatch('records/save', { resource: this.resource, record }); this.editing = null; await this.loadOptions(); }
      catch (error) { this.formError = error.message; }
      finally { this.saving = false; }
    },
    async remove(id) {
      if (!confirm('Удалить запись?')) return;
      try { await this.$store.dispatch('records/remove', { resource: this.resource, id }); }
      catch (error) { this.error = error.message; }
    },
    async clearAll() {
      if (!confirm(`Очистить таблицу «${this.config.title}»? Это удалит все её записи.`)) return;
      try { await education.clear(this.resource); this.patch({ page: 1 }); await this.load(); }
      catch (error) { this.error = error.message; }
    },
    async exportFile(resource) {
      try { const response = await education.export(resource); window.location.assign(response.data.url); }
      catch (error) { this.error = error.message; }
    },
  },
};
</script>
