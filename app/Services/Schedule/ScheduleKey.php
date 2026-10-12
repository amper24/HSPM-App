<?php

namespace App\Services\Schedule;

/**
 * Детерминированный ключ занятия для дедупликации.
 *
 * Один и тот же ключ формируется и при ручном вводе (ScheduleService), и при
 * импорте (ImportWriter), поэтому повторная загрузка не создаёт дубликаты.
 * Время приводится к формату HH:MM:SS перед расчётом md5.
 */
class ScheduleKey
{
    /**
     * Вычисляет md5-ключ занятия по значимым полям.
     *
     * Для занятий с датой ключ строится из даты/времени/дисциплины/группы/экзаменатора.
     * Для недельных сеток без даты добавляются день недели и числитель/знаменатель.
     * Если содержательных полей нет, используется запасной набор (время, тип занятия,
     * аудитории, номер пары), чтобы разные пустые строки не склеивались.
     *
     * @param  array<string, mixed>  $row Массив атрибутов занятия.
     * @return string md5-хэш длиной 32 символа.
     */
    public static function make(array $row): string
    {
        $time = $row['time_start'] ?? '';
        if (strlen($time) === 5) {
            $time .= ':00';
        }
        $parts = [$row['date'] ?? '', $time, $row['discipline'] ?? '', $row['group_code'] ?? '', $row['examiner'] ?? ''];
        if (empty($row['date'])) {
            $parts[] = $row['day_of_week'] ?? '';
            $parts[] = $row['numerator_denominator'] ?? '';
        }
        if (! trim(implode('', array_slice($parts, 2)))) {
            $parts = [$row['date'] ?? '', $time, $row['lesson_type'] ?? '', $row['classrooms_raw'] ?? (string) ($row['classroom_id'] ?? ''), $row['pair_number'] ?? ''];
        }

        return md5(implode('|', array_map(fn ($part) => trim((string) $part), $parts)));
    }
}
