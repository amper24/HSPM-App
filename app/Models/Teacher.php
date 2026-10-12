<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Преподаватель: ФИО, кафедра, форма занятости и контакты.
 *
 * Уникальность задана по тройке «фамилия + имя + отчество», поэтому пустое отчество
 * хранится как пустая строка, а не NULL: иначе NULL не участвует в сравнении UNIQUE
 * и повторный импорт порождал бы дубликаты. Сопоставление преподавателя при импорте
 * расписания выполняется по фамилии и инициалам в памяти (см. ImportWriter::teacher()).
 *
 * @property int         $id             Первичный ключ.
 * @property string      $last_name      Фамилия.
 * @property string      $first_name     Имя.
 * @property string      $middle_name    Отчество (пустая строка, если отсутствует).
 * @property string|null $position       Должность.
 * @property string|null $degree         Учёная степень.
 * @property string|null $title          Учёное звание.
 * @property string|null $department     Кафедра.
 * @property string|null $employment_type Форма занятости: «штатный», «внешний совместитель», «ГПХ» и т. п.
 * @property string|null $email          Электронная почта.
 * @property string|null $phone          Телефон.
 * @property string|null $notes          Примечания.
 */
class Teacher extends Model
{
    /**
     * Приводит NULL-отчество к пустой строке перед сохранением.
     *
     * Необходимо для корректной работы составного UNIQUE-индекса
     * (last_name, first_name, middle_name): одинаковые записи с отсутствующим
     * отчеством должны сравниваться как равные.
     *
     * @param string|null $value Отчество из входных данных (может быть null).
     */
    public function setMiddleNameAttribute(?string $value): void
    {
        $this->attributes['middle_name'] = $value ?? '';
    }

    /** Значения по умолчанию для новых экземпляров; отчество всегда строка. */
    protected $attributes = ['middle_name' => ''];

    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'teachers';

    /** Поля, разрешённые для записи из проверенных данных (массовое присваивание). */
    protected $fillable = ['last_name', 'first_name', 'middle_name', 'position', 'degree', 'title', 'department', 'employment_type', 'email', 'phone', 'notes'];

    /**
     * Приведение типов на границе БД и объекта.
     *
     * @return array<string, string> Карта «поле => каст» (сейчас пустая — типы стандартные).
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Занятия, где преподаватель указан экзаменатором (teacher_id).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function lessons()
    {
        return $this->hasMany(ScheduleEntry::class, 'teacher_id');
    }
}
