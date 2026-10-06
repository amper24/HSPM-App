<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Проверка полей формы до выполнения запросов на изменение данных. */
class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $rules = [
            'room_number' => 'required|string|max:20',
            'building' => 'required|string|max:5',
            'room_type' => 'required|string|max:50',
            'software_installed' => 'nullable|string|max:10000',
            'seats' => 'nullable|integer|min:0',
            'computers_count' => 'nullable|integer|min:0',
            'has_projector' => 'sometimes|boolean',
            'has_speakers' => 'sometimes|boolean',
        ];
        if (! $this->isMethod('POST')) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = 'sometimes|'.$rule;
            }
        }

        return $rules;
    }
}
