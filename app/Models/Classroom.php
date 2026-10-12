<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Аудитория учебного корпуса.
 *
 * Справочник помещений. Пара «номер + корпус» уникальна (см. миграцию) и служит
 * естественным ключом, по которому аудитория опознаётся при импорте Excel и при
 * разборе строки «Д234, В105» в редакторе расписания. Оборудование хранится
 * отдельными флагами и счётчиками, а не свободным списком, чтобы поиск свободных
 * аудиторий (FreeClassroomController) выражался простыми условиями WHERE.
 *
 * @property int         $id               Первичный ключ (increments).
 * @property string      $room_number      Номер аудитории в корпусе, например «101», «008».
 *                                         Ведущий ноль важен: «008» и «8» — разные помещения.
 * @property string      $building         Код корпуса: «Д» (Джамбула), «В» (Вознесенский), «БМ» (Большая Морская).
 * @property string      $room_type        Тип помещения (лекционная, лаборатория и т. п.). По умолчанию пустая строка.
 * @property string|null $software_installed  Свободное текстовое описание установленного ПО (историческое поле).
 * @property int|null    $seats            Число посадочных мест; null означает «неизвестно».
 * @property bool        $has_projector    Наличие проектора (0/1).
 * @property bool        $has_speakers     Наличие колонок (0/1).
 * @property int         $computers_count  Количество компьютеров; 0 — нет.
 */
class Classroom extends Model
{
    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'classrooms';

    /** Поля, разрешённые для записи из проверенных данных (массовое присваивание). */
    protected $fillable = ['room_number', 'building', 'room_type', 'software_installed', 'seats', 'has_projector', 'has_speakers', 'computers_count'];

    /**
     * Приведение типов на границе БД и объекта.
     *
     * @return array<string, string> Карта «поле => каст». Числа и булевы значения
     *                                приходят из БД как строки и нуждаются в явном
     *                                приведении для корректной JSON-сериализации.
     */
    protected function casts(): array
    {
        return ['seats' => 'integer', 'has_projector' => 'boolean', 'has_speakers' => 'boolean', 'computers_count' => 'integer'];
    }

    /**
     * Занятия, где аудитория указана как дополнительная (таблица schedule_classrooms).
     *
     * Отношение «многие ко многим»; одна запись занятия может затрагивать несколько
     * аудиторий. Используется при поиске свободных помещений и в отчёте по занятости.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function lessons()
    {
        return $this->belongsToMany(ScheduleEntry::class, 'schedule_classrooms', 'classroom_id', 'schedule_id');
    }

    /**
     * Занятия, где аудитория указана как основная (поле schedule.classroom_id).
     *
     * Параллельно отношению lessons() существует потому, что основная аудитория
     * занятия исторически хранится отдельным внешним ключом; дополнительная связь
     * «многие ко многим» была добавлена позже. Обе связи учитываются вместе при
     * расчёте занятости помещения.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function primaryLessons()
    {
        return $this->hasMany(ScheduleEntry::class, 'classroom_id');
    }
}
