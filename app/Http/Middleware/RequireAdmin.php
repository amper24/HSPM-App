<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware проверки роли администратора.
 *
 * Права берутся из текущей записи пользователя в БД, а не из устаревшей роли в
 * cookie, которая могла остаться от старой версии приложения. Используется как
 * alias «admin» (см. bootstrap/app.php) на изменяющих маршрутах.
 */
class RequireAdmin
{
    /**
     * Проверяет роль и либо пропускает запрос дальше, либо прерывает его с 403.
     *
     * @param  Request  $request Текущий HTTP-запрос.
     * @param  Closure  $next    Следующий обработчик в цепочке middleware.
     * @return mixed Ответ следующего обработчика или abort(403) для не-администратора.
     */
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Требуются права администратора');

        return $next($request);
    }
}
