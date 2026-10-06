<template>
  <form class="card" @submit.prevent="upload"><h3>Импорт данных</h3>
    <div class="form-group"><label>Тип данных <select v-model="type" :disabled="busy"><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></label></div>
    <div class="form-group"><label>Файл (.xlsx, .xls) <input type="file" accept=".xlsx,.xls" required :disabled="busy" @change="file = $event.target.files[0]"></label></div>
    <template v-if="type === 'schedule'">
      <div class="form-group"><label><input v-model="createMissing" type="checkbox"> Создавать отсутствующих преподавателей</label></div>
      <div class="form-group"><label><input v-model="replace" type="checkbox"> Заменить расписание групп из файла в его диапазоне дат</label></div>
    </template>
    <button class="primary" :disabled="busy">{{ busy ? 'Импорт данных…' : 'Загрузить' }}</button>
    <p v-if="result" role="status" class="alert" :class="failed ? 'alert-danger' : 'alert-success'">{{ result }}</p>
  </form>
</template>
<script>
import { education } from '../services/education';
export default {
  data: () => ({ type: 'teachers', file: null, createMissing: false, replace: false, busy: false, failed: false, result: '', types: { teachers: 'Преподаватели', classrooms: 'Аудитории', schedule: 'Расписание', software: 'Программное обеспечение' } }),
  methods: {
    async upload() {
      if (!this.file) return;
      if (this.replace && this.type === 'schedule' && !confirm('Заменить расписание групп из файла? Отсутствующие в файле занятия в его диапазоне дат будут удалены.')) return;
      const form = new FormData();
      form.append('file', this.file); form.append('type', this.type);
      if (this.type === 'schedule') { form.append('create_missing', this.createMissing ? '1' : '0'); form.append('replace', this.replace ? '1' : '0'); }
      this.busy = true; this.result = ''; this.failed = false;
      try { this.result = (await education.import(form)).message; this.$store.commit('records/reset'); }
      catch (error) { this.failed = true; this.result = error.message; }
      finally { this.busy = false; }
    },
  },
};
</script>
