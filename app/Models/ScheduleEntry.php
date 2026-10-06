<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Занятие: время, группа, преподаватель и аудитории. */
class ScheduleEntry extends Model
{
    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'schedule';

    /** Поля, разрешённые для записи из проверенных данных. */
    protected $fillable = ['dedup_key', 'classroom_id', 'classrooms_raw', 'teacher_id', 'numerator_denominator', 'date', 'day_of_week', 'discipline', 'group_department', 'group_code', 'teacher_department', 'teacher_position', 'examiner', 'exam_type', 'session_start', 'session_end', 'pair_number', 'time_start', 'time_end', 'is_nonstandard_time', 'lesson_type', 'is_occupied', 'transfer_cancel'];

    protected function casts(): array
    {
        return ['classroom_id' => 'integer', 'teacher_id' => 'integer', 'pair_number' => 'integer', 'is_nonstandard_time' => 'boolean', 'is_occupied' => 'boolean'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Classroom::class, 'schedule_classrooms', 'schedule_id', 'classroom_id');
    }
}
