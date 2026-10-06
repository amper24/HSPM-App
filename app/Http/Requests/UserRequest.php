<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Проверка полей формы до выполнения запросов на изменение данных. */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

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
