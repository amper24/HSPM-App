<?php

namespace App\Services\Schedule;

/** Один ключ используется при ручном вводе и импорте. Время приводится к HH:MM:SS. */
class ScheduleKey
{
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
