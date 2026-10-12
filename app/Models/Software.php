<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Запись о программном обеспечении, установленном в аудитории.
 *
 * Один ряд таблицы = одна программа в одной аудитории. Привязка к аудитории может
 * быть как через внешний ключ classroom_id, так и через денормализованные поля
 * room_number/building (исторический формат листа «ПО»). Импорт ПО заменяет состав
 * программ только для аудиторий, перечисленных в файле.
 *
 * @property int         $id           Первичный ключ.
 * @property int|null    $classroom_id Связь с аудиторией; null, если запись пришла со старым форматом.
 * @property string|null $room_number  Денормализованный номер аудитории.
 * @property string|null $building     Денормализованный код корпуса.
 * @property string      $name         Название программы.
 * @property string|null $notes        Примечания.
 */
class Software extends Model
{
    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'software';

    /** Поля, разрешённые для записи из проверенных данных (массовое присваивание). */
    protected $fillable = ['classroom_id', 'room_number', 'building', 'name', 'notes'];

    /**
     * Приведение типов на границе БД и объекта.
     *
     * @return array<string, string> Карта «поле => каст».
     */
    protected function casts(): array
    {
        return ['classroom_id' => 'integer'];
    }
}
