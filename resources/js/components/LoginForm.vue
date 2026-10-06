<template>
  <div class="login-wrapper"><form class="login-box" @submit.prevent="submit">
    <h3>Учебный отдел ВШПМ</h3><p class="login-sub">СПбГУПТД</p>
    <div class="form-group"><label for="username">Логин</label><input id="username" v-model="username" autocomplete="username" required></div>
    <div class="form-group"><label for="password">Пароль</label><input id="password" v-model="password" type="password" autocomplete="current-password" required></div>
    <button class="primary login-btn" :disabled="busy">Войти</button>
    <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p>
  </form></div>
</template>
<script>
export default {
  data: () => ({ username: '', password: '', busy: false, error: '' }),
  methods: {
    async submit() {
      this.busy = true; this.error = '';
      try { await this.$store.dispatch('auth/login', { username: this.username, password: this.password }); }
      catch (error) { this.error = error.message; }
      finally { this.busy = false; }
    },
  },
};
</script>
