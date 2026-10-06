<template>
  <div>
    <div v-if="!ready" class="login-wrapper"><div class="spinner"></div></div>
    <LoginForm v-else-if="!user" />
    <div v-else>
      <header class="header"><h2>Учебный отдел ВШПМ СПбГУПТД</h2><div class="header-user"><span>{{ user.full_name || user.username }} ({{ user.role }})</span><button class="outline" @click="logout">Выйти</button></div></header>
      <div class="layout">
        <nav class="aside"><button v-for="item in menu" :key="item.id" class="menu-link" :class="{ active: section === item.id }" @click="section = item.id">{{ item.title }}</button></nav>
        <main class="main">
          <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
          <DashboardView v-if="section === 'dashboard'" />
          <ImportView v-else-if="section === 'import' && isAdmin" />
          <RecordsView v-else :key="section" :resource="section" />
        </main>
      </div>
      <button class="theme-toggle" aria-label="Переключить тему" @click="toggleTheme">{{ theme === 'dark' ? '☾' : '☀' }}</button>
    </div>
  </div>
</template>
<script>
import { mapState, mapGetters } from 'vuex';
import LoginForm from './components/LoginForm.vue';
import DashboardView from './views/DashboardView.vue';
import RecordsView from './views/RecordsView.vue';
import ImportView from './views/ImportView.vue';
import { resources } from './config/resources';

export default {
  components: { LoginForm, DashboardView, RecordsView, ImportView },
  data: () => ({ section: 'dashboard', theme: localStorage.getItem('theme') || 'light', error: '' }),
  computed: {
    ...mapState('auth', ['user', 'ready']), ...mapGetters('auth', ['isAdmin']),
    menu() {
      const items = [{ id: 'dashboard', title: 'Главная' }, ...Object.entries(resources).filter(([id]) => id !== 'users' || this.isAdmin).map(([id, config]) => ({ id, title: config.title }))];
      if (this.isAdmin) items.push({ id: 'import', title: 'Импорт данных' });
      return items;
    },
  },
  watch: { user(value) { if (!value) { this.section = 'dashboard'; this.$store.commit('records/reset'); } } },
  async created() {
    document.documentElement.setAttribute('data-theme', this.theme);
    document.body.classList.toggle('dark-theme', this.theme === 'dark');
    window.addEventListener('session-expired', this.sessionExpired);
    try { await this.$store.dispatch('auth/restore'); } catch (error) { this.error = error.message; }
  },
  beforeDestroy() { window.removeEventListener('session-expired', this.sessionExpired); },
  methods: {
    sessionExpired() { this.$store.commit('auth/setUser', null); },
    async logout() { try { await this.$store.dispatch('auth/logout'); } catch (error) { this.error = error.message; } },
    toggleTheme() {
      this.theme = this.theme === 'dark' ? 'light' : 'dark';
      localStorage.setItem('theme', this.theme);
      document.documentElement.setAttribute('data-theme', this.theme);
      document.body.classList.toggle('dark-theme', this.theme === 'dark');
    },
  },
};
</script>
