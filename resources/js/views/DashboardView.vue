<template>
  <section>
    <div class="dashboard-header"><div class="dashboard-clock">{{ clock }}</div><div class="dashboard-filters">
      <input type="date" aria-label="Дата расписания" :value="dashboard.date" @change="filter({ date: $event.target.value })">
      <input list="groups" aria-label="Группа" placeholder="Все группы" :value="dashboard.group" @change="filter({ group: $event.target.value })">
      <datalist id="groups"><option v-for="group in dashboard.groups" :key="group" :value="group" /></datalist>
    </div></div>
    <div class="stats"><div v-for="(label, key) in labels" :key="key" class="stat-card"><div class="num">{{ dashboard.stats[key] || 0 }}</div><div class="label">{{ label }}</div></div></div>
    <p v-if="dashboard.error" role="alert" class="alert alert-danger">{{ dashboard.error }}</p>
    <div v-if="dashboard.loading" class="spinner"></div>
    <div v-else class="card"><h3>Расписание на {{ dashboard.date }}</h3>
      <p v-if="!dashboard.lessons.length">Нет занятий на выбранную дату</p>
      <table v-else class="dash-table"><thead><tr><th>Время</th><th>Группа</th><th>Дисциплина</th><th>Аудитория</th><th>Преподаватель</th><th>Перенос/отмена</th></tr></thead>
        <tbody><tr v-for="lesson in dashboard.lessons" :key="lesson.id" :class="{ 'row-cancel': lesson.transfer_cancel === 'отмена', 'row-transfer': lesson.transfer_cancel === 'перенос' }"><td>{{ lessonTime(lesson) }}</td><td>{{ lesson.group_code }}</td><td>{{ lesson.discipline }}</td><td>{{ scheduleRooms(lesson) }}</td><td>{{ lesson.teacher_name || lesson.examiner }}</td><td>{{ lesson.transfer_cancel }}</td></tr></tbody>
      </table>
    </div>
  </section>
</template>
<script>
import { mapState } from 'vuex';
import { lessonTime, scheduleRooms } from '../utils/format';
export default {
  data: () => ({ clock: '', clockTimer: null, labels: { teachers: 'Преподавателей', classrooms: 'Аудиторий', schedule: 'Записей расписания', software: 'Записей ПО' } }),
  computed: mapState(['dashboard']),
  created() { this.tick(); this.clockTimer = setInterval(this.tick, 1000); this.$store.dispatch('dashboard/init'); },
  beforeDestroy() { clearInterval(this.clockTimer); },
  methods: {
    lessonTime, scheduleRooms,
    tick() { this.clock = new Date().toLocaleString('ru-RU'); },
    filter(values) { this.$store.commit('dashboard/patch', values); this.$store.dispatch('dashboard/load'); },
  },
};
</script>
