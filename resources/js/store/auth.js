import { request, refreshCsrf } from '../services/http';

export default {
  namespaced: true,
  state: () => ({
    user: null, // Пользователь из текущей серверной сессии; null для гостя.
    ready: false, // Завершена ли проверка сессии при открытии страницы.
  }),
  getters: { isAdmin: state => state.user?.role === 'admin' },
  mutations: {
    setUser(state, user) { state.user = user; },
    setReady(state, ready) { state.ready = ready; },
  },
  actions: {
    async restore({ commit }) {
      try { commit('setUser', (await request('GET', '/auth/me')).data); }
      finally { commit('setReady', true); }
    },
    async login({ commit }, credentials) {
      const response = await request('POST', '/auth/login', credentials);
      // Laravel regenerates the session token during login.
      await refreshCsrf();
      commit('setUser', response.data);
    },
    async logout({ commit }) {
      await request('POST', '/auth/logout');
      commit('setUser', null);
      await refreshCsrf();
    },
  },
};
