<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Аудитория: корпус, номер, оборудование и вместимость. */
class Classroom extends Model
{
    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'classrooms';

    /** Поля, разрешённые для записи из проверенных данных. */
    protected $fillable = ['room_number', 'building', 'room_type', 'software_installed', 'seats', 'has_projector', 'has_speakers', 'computers_count'];

    protected function casts(): array
    {
        return ['seats' => 'integer', 'has_projector' => 'boolean', 'has_speakers' => 'boolean', 'computers_count' => 'integer'];
    }

    public function lessons()
    {
        return $this->belongsToMany(ScheduleEntry::class, 'schedule_classrooms', 'classroom_id', 'schedule_id');
    }

    public function primaryLessons()
    {
        return $this->hasMany(ScheduleEntry::class, 'classroom_id');
    }
}
