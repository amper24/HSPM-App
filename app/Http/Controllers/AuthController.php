<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Аутентификация через cookie-сессию Laravel.
 *
 * Вход проверяет учётные данные, пересоздаёт идентификатор сессии (защита от
 * фиксации сессии) и возвращает пользователя без пароля. Маршрут входа дополнительно
 * ограничен троттлингом (см. routes/web.php).
 */
class AuthController extends Controller
{
    /**
     * Вход по логину и паролю.
     *
     * @param  Request  $request Запрос с полями username и password.
     * @return \Illuminate\Http\JsonResponse Данные пользователя либо 401 при неверных учётных данных.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate(['username' => 'required|string|max:50', 'password' => 'required|string']);
        abort_unless(Auth::attempt($credentials), 401, 'Неверный логин или пароль');
        $request->session()->regenerate();

        return $this->success($request->user(), 'Вход выполнен');
    }

    /**
     * Возвращает текущего пользователя сессии (null для гостя).
     *
     * @param  Request  $request Текущий запрос.
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return $this->success($request->user());
    }

    /**
     * Выход: сбрасывает аутентификацию, сессию и CSRF-токен.
     *
     * @param  Request  $request Текущий запрос.
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success();
    }
}
