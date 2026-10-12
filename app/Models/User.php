<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Учётная запись пользователя приложения.
 *
 * Аутентификация построена на сессионном guard Laravel (config/auth.php) и модели
 * Authenticatable. Роль ограничена значениями «user» и «admin»; право на запись
 * проверяется либо middleware RequireAdmin, либо методом authorize() FormRequest.
 * Пароль хранится только в виде bcrypt-хеша и никогда не попадает в JSON-ответы.
 *
 * @property int         $id        Первичный ключ.
 * @property string      $username  Уникальный логин (до 50 символов).
 * @property string      $password  bcrypt-хеш пароля (каст 'hashed').
 * @property string      $role      Роль: «user» или «admin».
 * @property string|null $full_name Отображаемое имя.
 */
class User extends Authenticatable
{
    /** Поля, разрешённые для записи из проверенных данных (массовое присваивание). */
    protected $fillable = ['username', 'password', 'role', 'full_name'];

    /** Поля, исключаемые из любой сериализации модели (JSON-ответы, массивы). */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Приведение типов; 'hashed' автоматически хеширует пароль при присваивании.
     *
     * @return array<string, string> Карта «поле => каст».
     */
    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
