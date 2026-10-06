/** Общий HTTP-клиент. Компоненты не знают о cookie, CSRF и формате ошибок API. */
let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

export async function refreshCsrf() {
  const response = await fetch('/api/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  const payload = await response.json();
  csrfToken = payload.data.token;
}

export async function request(method, path, body, signal, refreshed = false) {
  const changesData = !['GET', 'HEAD'].includes(method);
  if (changesData && !csrfToken) await refreshCsrf();
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  if (changesData) headers['X-CSRF-TOKEN'] = csrfToken;
  const isForm = body instanceof FormData;
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
  const response = await fetch('/api' + path, {
    method, headers, credentials: 'same-origin', signal,
    body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
  });
  const payload = await response.json().catch(() => ({ error: 'Сервер вернул некорректный ответ' }));
  // При 419 сервер отклонил запрос до изменения данных; допустим один повтор с новым токеном.
  if (response.status === 419 && !refreshed) {
    await refreshCsrf();
    return request(method, path, body, signal, true);
  }
  if (!response.ok || !payload.success) {
    if (response.status === 401) window.dispatchEvent(new Event('session-expired'));
    const error = new Error(payload.error || 'Не удалось выполнить запрос');
    error.status = response.status;
    error.fields = payload.errors || {};
    throw error;
  }
  return payload;
}
