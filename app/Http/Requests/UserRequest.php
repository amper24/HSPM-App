<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Проверка полей формы до выполнения запросов на изменение данных. */
class UserRequest extends FormRequest
{
    /**
     * Разрешает запрос только администратору (актуальная роль из БД).
     *
     * @return bool true, если текущий пользователь имеет роль «admin».
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Правила валидации учётной записи.
     *
     * Имя пользователя проверяется на уникальность с учётом редактируемой записи
     * (исключение по route('id')). Пустое поле пароля при редактировании означает
     * «не менять пароль» — правило пароля в этом случае снимается.
     *
     * @return array<string, mixed> Карта «поле => правила валидации».
     */
    public function rules(): array
    {
        $rules = [
            'username' => 'required|string|max:50',
            'password' => 'required|string|min:8|max:255',
            'role' => 'required|in:user,admin',
            'full_name' => 'nullable|string|max:255',
        ];
        if (! $this->isMethod('POST')) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = 'sometimes|'.$rule;
            }
        }

        $rules['username'] = ['sometimes', 'required', 'string', 'max:50', Rule::unique('users')->ignore($this->route('id'))];
        if ($this->isMethod('POST')) {
            array_shift($rules['username']);
        }
        // Пустое поле пароля при редактировании означает «не менять пароль».
        if (! $this->isMethod('POST') && ! $this->filled('password')) {
            unset($rules['password']);
        }

        return $rules;
    }
}
