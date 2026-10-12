/**
 * Общий HTTP-клиент приложения.
 *
 * Компоненты не знают о cookie, CSRF-токене и формате ошибок API — всё это
 * инкапсулировано здесь. Клиент автоматически добавляет CSRF-токен к изменяющим
 * запросам, при 419 повторяет запрос с обновлённым токеном, а при 401 рассылает
 * событие session-expired для сброса состояния авторизации.
 */
let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Обновляет CSRF-токен из эндпоинта /api/csrf.
 *
 * Необходим после входа/выхода, т. к. Laravel пересоздаёт токен сессии.
 *
 * @returns {Promise<void>}
 */
export async function refreshCsrf() {
  const response = await fetch('/api/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  const payload = await response.json();
  csrfToken = payload.data.token;
}

/**
 * Выполняет запрос к внутреннему API и возвращает распакованный payload.
 *
 * @param {string} method  HTTP-метод (GET, POST, PUT, DELETE).
 * @param {string} path    Путь относительно /api, например '/teachers'.
 * @param {object|FormData} [body]  Тело запроса; объект сериализуется в JSON, FormData передаётся как есть.
 * @param {AbortSignal} [signal]    Сигнал отмены запроса.
 * @param {boolean} [refreshed=false] Внутренний флаг: запрос уже повторён после обновления CSRF.
 * @returns {Promise<object>} Ответ {success, data, message}.
 * @throws {Error} При неуспешном ответе; содержит поля status и fields.
 */
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
