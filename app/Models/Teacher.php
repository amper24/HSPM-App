<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Преподаватель: ФИО, кафедра, занятость и контакты. */
class Teacher extends Model
{
    /** Пустое отчество хранится единообразно, чтобы UNIQUE защищал повторный импорт. */
    public function setMiddleNameAttribute(?string $value): void
    {
        $this->attributes['middle_name'] = $value ?? '';
    }

    protected $attributes = ['middle_name' => ''];

    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'teachers';

    /** Поля, разрешённые для записи из проверенных данных. */
    protected $fillable = ['last_name', 'first_name', 'middle_name', 'position', 'degree', 'title', 'department', 'employment_type', 'email', 'phone', 'notes'];

    protected function casts(): array
    {
        return [];
    }

    public function lessons()
    {
        return $this->hasMany(ScheduleEntry::class, 'teacher_id');
    }
}
