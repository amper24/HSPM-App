<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Программное обеспечение, закреплённое за аудиторией. */
class Software extends Model
{
    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'software';

    /** Поля, разрешённые для записи из проверенных данных. */
    protected $fillable = ['classroom_id', 'room_number', 'building', 'name', 'notes'];

    protected function casts(): array
    {
        return ['classroom_id' => 'integer'];
    }
}
