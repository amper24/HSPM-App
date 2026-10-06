<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Права берутся из текущей записи пользователя, а не из устаревшей роли в cookie. */
class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Требуются права администратора');

        return $next($request);
    }
}
