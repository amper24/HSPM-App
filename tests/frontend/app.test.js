import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import RecordForm from '../../resources/js/components/RecordForm.vue';
import { queryString } from '../../resources/js/services/education';
import { lessonTime, cellText } from '../../resources/js/utils/format';
import records from '../../resources/js/store/records';
import { education } from '../../resources/js/services/education';

describe('Клиент учебного отдела', () => {
  it('сохраняет ноль в фильтрах', () => {
    expect(queryString({ has_projector: 0, empty: '', missing: null })).toBe('has_projector=0');
  });
  it('не добавляет null к времени и не интерпретирует текст как HTML', () => {
    expect(lessonTime({ time_start: '08:30:00', time_end: null })).toBe('08:30');
    expect(cellText({ value: '<img onerror="bad()">' }, { field: 'value' })).toContain('<img');
  });
  it('отмена редактирования не меняет исходную запись', async () => {
    const record = { id: 1, name: 'Исходное' };
    const wrapper = mount(RecordForm, { propsData: { record, fields: [{ field: 'name', label: 'Название' }] } });
    await wrapper.find('input').setValue('Новое');
    await wrapper.find('button[type="button"]').trigger('click');
    expect(record.name).toBe('Исходное');
    expect(wrapper.emitted('cancel')).toHaveLength(1);
  });
  it('поздний ответ старого поиска не заменяет новый результат', async () => {
    let finishOld;
    const old = new Promise(resolve => { finishOld = resolve; });
    vi.spyOn(education, 'list').mockReturnValueOnce(old).mockResolvedValueOnce({ data: { items: [{ id: 2 }], pagination: { total: 1, pages: 1 } } });
    const state = records.state();
    const commit = (name, payload) => records.mutations[name](state, payload);
    const first = records.actions.load({ state, commit }, 'teachers');
    await records.actions.load({ state, commit }, 'teachers');
    finishOld({ data: { items: [{ id: 1 }], pagination: { total: 1, pages: 1 } } });
    await first;
    expect(state.lists.teachers.items[0].id).toBe(2);
    vi.restoreAllMocks();
  });
});
