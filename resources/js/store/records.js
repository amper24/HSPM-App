import Vue from 'vue';
import { education } from '../services/education';

/** Каждый справочник хранит собственные фильтры и страницу. */
const emptyList = () => ({
  items: [], // Записи текущей страницы.
  filters: {}, // Условия поиска, выбранные пользователем.
  page: 1, // Номер текущей страницы, начиная с единицы.
  perPage: 50, // Максимальное число строк на странице.
  total: 0, // Число записей, подходящих под фильтры.
  pages: 1, // Число страниц в ответе сервера.
  loading: false, // Признак активного запроса списка.
  error: '', // Сообщение последней ошибки загрузки.
  requestId: 0, // Номер запроса для защиты от запоздалых ответов.
});
export default {
  namespaced: true,
  state: () => ({ lists: {} }),
  mutations: {
    ensure(state, resource) { if (!state.lists[resource]) Vue.set(state.lists, resource, emptyList()); },
    patch(state, { resource, values }) { Object.assign(state.lists[resource], values); },
    reset(state) { state.lists = {}; },
  },
  actions: {
    async load({ state, commit }, resource) {
      commit('ensure', resource);
      const list = state.lists[resource];
      const requestId = list.requestId + 1;
      commit('patch', { resource, values: { loading: true, error: '', requestId } });
      try {
        const response = await education.list(resource, { ...list.filters, page: list.page, per_page: list.perPage });
        // Поздний ответ на старый фильтр не должен заменять актуальный результат.
        if (state.lists[resource] !== list || list.requestId !== requestId) return;
        const { items, pagination } = response.data;
        commit('patch', { resource, values: { items, total: pagination.total, pages: pagination.pages } });
      } catch (error) {
        if (state.lists[resource] === list && list.requestId === requestId) commit('patch', { resource, values: { error: error.message } });
      } finally {
        if (state.lists[resource] === list && list.requestId === requestId) commit('patch', { resource, values: { loading: false } });
      }
    },
    async save({ dispatch }, { resource, record }) { await education.save(resource, record); await dispatch('load', resource); },
    async remove({ state, commit, dispatch }, { resource, id }) {
      await education.remove(resource, id);
      const list = state.lists[resource];
      if (list.items.length === 1 && list.page > 1) commit('patch', { resource, values: { page: list.page - 1 } });
      await dispatch('load', resource);
    },
  },
};
