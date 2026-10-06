<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['username' => 'required|string|max:50', 'password' => 'required|string']);
        abort_unless(Auth::attempt($credentials), 401, 'Неверный логин или пароль');
        $request->session()->regenerate();

        return $this->success($request->user(), 'Вход выполнен');
    }

    public function me(Request $request)
    {
        return $this->success($request->user());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success();
    }
}
