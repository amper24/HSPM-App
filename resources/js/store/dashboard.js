import { education } from '../services/education';
import { localDate } from '../utils/format';

export default {
  namespaced: true,
  state: () => ({
    date: localDate(), // Выбранный календарный день.
    group: '', // Точный шифр группы; пустая строка означает все группы.
    groups: [], // Справочник доступных групп для подсказок.
    stats: {}, // Количество записей по каждому разделу.
    lessons: [], // Полный список занятий выбранного дня.
    loading: false, // Показывать индикатор загрузки расписания.
    error: '', // Понятное пользователю сообщение об ошибке.
    requestId: 0, // Последний запрос, которому разрешено менять состояние.
  }),
  mutations: { patch(state, values) { Object.assign(state, values); } },
  actions: {
    async init({ commit, dispatch }) {
      try {
        const [stats, groups] = await Promise.all([education.stats(), education.options('schedule', 'groups')]);
        commit('patch', { stats: stats.data, groups: groups.data });
        await dispatch('load');
      } catch (error) { commit('patch', { error: error.message }); }
    },
    async load({ state, commit }) {
      const requestId = state.requestId + 1;
      const filters = { date: state.date, group_code: state.group, per_page: 100 };
      commit('patch', { loading: true, error: '', requestId });
      try {
        // Дочитываем страницы, чтобы не обрезать расписание дня на 50 занятиях.
        const first = await education.list('schedule', filters);
        const lessons = [...first.data.items];
        for (let page = 2; page <= first.data.pagination.pages; page++) {
          if (state.requestId !== requestId) return;
          lessons.push(...(await education.list('schedule', { ...filters, page })).data.items);
        }
        if (state.requestId === requestId) commit('patch', { lessons });
      } catch (error) {
        if (state.requestId === requestId) commit('patch', { error: error.message });
      } finally {
        if (state.requestId === requestId) commit('patch', { loading: false });
      }
    },
  },
};
