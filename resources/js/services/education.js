import { request } from './http';

/** Пустые фильтры исключаются, но числовой ноль и false сохраняются. */
export function queryString(filters) {
  return new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined)).toString();
}

export const education = {
  list: (resource, filters) => request('GET', `/${resource}?${queryString(filters)}`),
  save: (resource, record) => request(record.id ? 'PUT' : 'POST', `/${resource}${record.id ? '/' + record.id : ''}`, record),
  remove: (resource, id) => request('DELETE', `/${resource}/${id}`),
  clear: resource => request('POST', `/${resource}/truncate`),
  options: (resource, field) => request('GET', `/${resource}/${field}`),
  stats: () => request('GET', '/dashboard/stats'),
  freeRooms: filters => request('GET', `/classrooms/free?${queryString(filters)}`),
  export: resource => request('GET', `/export/${resource}`),
  import: form => request('POST', '/import', form),
};
