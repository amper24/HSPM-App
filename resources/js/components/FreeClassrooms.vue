<template>
  <section class="free-search card"><h3>Поиск свободной аудитории</h3>
    <form class="filters-bar" @submit.prevent="search">
      <label>Дата <input v-model="filters.date" type="date" required></label>
      <label>Пара <select v-model="filters.pair_number"><option value="">Весь день</option><option v-for="number in 8" :key="number" :value="number">{{ number }}</option></select></label>
      <label>Корпус <select v-model="filters.building"><option value="">Любой</option><option v-for="building in ['Д','В','БМ']" :key="building">{{ building }}</option></select></label>
      <label>Тип <input v-model="filters.room_type" placeholder="Любой"></label>
      <label>Мест от <input v-model="filters.seats_min" type="number" min="0"></label>
      <label v-for="(label, key) in equipment" :key="key">{{ label }} <select v-model="filters[key]"><option value="">Не важно</option><option value="1">Есть</option><option value="0">Нет</option></select></label>
      <button class="primary" :disabled="loading">Найти</button>
    </form>
    <p v-if="error" class="alert alert-danger">{{ error }}</p>
    <p v-if="notice" class="alert" role="status">{{ notice }}</p>
    <div v-if="searched"><p v-if="!rooms.length">Свободных аудиторий не найдено</p>
      <table v-else><thead><tr><th>Аудитория</th><th>Тип</th><th>Мест</th><th>ПК</th><th>Проектор</th><th>Колонки</th></tr></thead><tbody><tr v-for="room in rooms" :key="room.id"><td>{{ room.building }}{{ room.room_number }}</td><td>{{ room.room_type }}</td><td>{{ room.seats }}</td><td>{{ room.computers_count }}</td><td>{{ room.has_projector ? 'Да' : 'Нет' }}</td><td>{{ room.has_speakers ? 'Да' : 'Нет' }}</td></tr></tbody></table>
    </div>
  </section>
</template>
<script>
import { education } from '../services/education';
import { localDate } from '../utils/format';
export default {
  data: () => ({ filters: { date: localDate(), pair_number: '', building: '', room_type: '', seats_min: '', has_projector: '', has_speakers: '' }, equipment: { has_projector: 'Проектор', has_speakers: 'Колонки' }, rooms: [], loading: false, searched: false, error: '', notice: '' }),
  methods: {
    async search() {
      this.loading = true; this.error = ''; this.searched = false;
      try { const response = await education.freeRooms(this.filters); this.rooms = response.data; this.notice = response.message; this.searched = true; }
      catch (error) { this.error = error.message; }
      finally { this.loading = false; }
    },
  },
};
</script>
