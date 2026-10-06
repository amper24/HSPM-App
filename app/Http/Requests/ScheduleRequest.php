<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Проверка полей формы до выполнения запросов на изменение данных. */
class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $rules = [
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'classrooms' => 'nullable|string|max:255',
            'classrooms_raw' => 'nullable|string|max:255',
            'date' => 'nullable|date_format:Y-m-d',
            'time_start' => 'nullable|date_format:H:i,H:i:s',
            'time_end' => 'nullable|date_format:H:i,H:i:s',
            'session_start' => 'nullable|date_format:Y-m-d',
            'session_end' => 'nullable|date_format:Y-m-d',
            'pair_number' => 'nullable|integer|min:1|max:20',
            'is_occupied' => 'sometimes|boolean',
            'is_nonstandard_time' => 'sometimes|boolean',
            'transfer_cancel' => 'nullable|in:нет,перенос,отмена',
            'discipline' => 'nullable|string|max:10000',
            'examiner' => 'nullable|string|max:10000',
            'group_code' => 'nullable|string|max:50',
            'group_department' => 'nullable|string|max:100',
            'teacher_department' => 'nullable|string|max:100',
            'teacher_position' => 'nullable|string|max:100',
            'numerator_denominator' => 'nullable|string|max:20',
            'day_of_week' => 'nullable|string|max:20',
            'lesson_type' => 'nullable|string|max:20',
            'exam_type' => 'nullable|string|max:20',
        ];
        if (! $this->isMethod('POST')) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = 'sometimes|'.$rule;
            }
        }

        return $rules;
    }
}
