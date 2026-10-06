<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Проверка полей формы до выполнения запросов на изменение данных. */
class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $rules = [
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:255',
            'degree' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'employment_type' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:10000',
        ];
        if (! $this->isMethod('POST')) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = 'sometimes|'.$rule;
            }
        }

        return $rules;
    }
}
