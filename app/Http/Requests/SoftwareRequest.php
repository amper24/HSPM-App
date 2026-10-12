<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Проверка полей формы до выполнения запросов на изменение данных. */
class SoftwareRequest extends FormRequest
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
     * Правила валидации записи о ПО.
     *
     * Привязка возможна либо через classroom_id, либо через свободные поля
     * room_number/building (исторический формат). Название ПО обязательно.
     *
     * @return array<string, mixed> Карта «поле => правила валидации».
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'room_number' => 'nullable|string|max:20',
            'building' => 'nullable|string|max:5',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
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
