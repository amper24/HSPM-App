<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Занятие в расписании.
 *
 * Центральная сущность приложения. Описывает либо конкретное занятие с календарной
 * датой (сессии), либо строку недельной сетки без даты (тогда заполнены day_of_week
 * и, возможно, numerator_denominator). Одна запись может иметь основную аудиторию
 * (classroom_id) и произвольный набор дополнительных аудиторий (rooms()).
 *
 * Поле dedup_key — детерминированный md5 от набора значимых полей; он одинаков при
 * ручном вводе и при импорте (см. ScheduleKey), что делает повторный импорт
 * идемпотентным.
 *
 * @property int         $id                 Первичный ключ.
 * @property string      $dedup_key          Хэш-ключ дедупликации (md5, 32 символа).
 * @property int|null    $classroom_id       Основная аудитория; null для дистанционных занятий (ДО).
 * @property string|null $classrooms_raw     Исходная строка аудиторий до разбора, например «Д234, В105».
 * @property int|null    $teacher_id         Связь с преподавателем; null, если ФИО неоднозначно или не создано.
 * @property string|null $numerator_denominator  Чередование недель «числитель/знаменатель».
 * @property string|null $date               Календарная дата (Y-m-d); null у недельных сеток.
 * @property string|null $day_of_week        День недели для недельных сеток.
 * @property string|null $discipline         Название дисциплины (TEXT — вмещает описание и примечания сетки).
 * @property string|null $group_department   Кафедра группы.
 * @property string|null $group_code         Шифр группы, например «1-ТИД-1».
 * @property string|null $teacher_department Кафедра преподавателя.
 * @property string|null $teacher_position   Должность преподавателя.
 * @property string|null $examiner           ФИО экзаменатора (текст, если преподаватель неоднозначен).
 * @property string|null $exam_type          Вид: «экзамен», «консультация».
 * @property string|null $session_start      Начало сессии (для импорта сессионных книг).
 * @property string|null $session_end        Конец сессии.
 * @property int|null    $pair_number        Номер пары (1..20).
 * @property string|null $time_start         Время начала (H:i:s).
 * @property string|null $time_end           Время окончания (H:i:s).
 * @property bool        $is_nonstandard_time  Признак нестандартного времени.
 * @property string|null $lesson_type        Вид занятия: «лекция», «практика», «лабораторная».
 * @property bool        $is_occupied        Занята ли аудитория в это время.
 * @property string|null $transfer_cancel    «нет» | «перенос» | «отмена».
 */
class ScheduleEntry extends Model
{
    /** Имя существующей таблицы; сохраняется для переноса старой базы. */
    protected $table = 'schedule';

    /** Поля, разрешённые для записи из проверенных данных (массовое присваивание). */
    protected $fillable = ['dedup_key', 'classroom_id', 'classrooms_raw', 'teacher_id', 'numerator_denominator', 'date', 'day_of_week', 'discipline', 'group_department', 'group_code', 'teacher_department', 'teacher_position', 'examiner', 'exam_type', 'session_start', 'session_end', 'pair_number', 'time_start', 'time_end', 'is_nonstandard_time', 'lesson_type', 'is_occupied', 'transfer_cancel'];

    /**
     * Приведение типов на границе БД и объекта.
     *
     * @return array<string, string> Карта «поле => каст».
     */
    protected function casts(): array
    {
        return ['classroom_id' => 'integer', 'teacher_id' => 'integer', 'pair_number' => 'integer', 'is_nonstandard_time' => 'boolean', 'is_occupied' => 'boolean'];
    }

    /**
     * Преподаватель занятия (необязательная связь).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Основная аудитория занятия (необязательная связь).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Все аудитории занятия (дополнительные; основная хранится отдельно в classroom()).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function rooms()
    {
        return $this->belongsToMany(Classroom::class, 'schedule_classrooms', 'schedule_id', 'classroom_id');
    }
}
