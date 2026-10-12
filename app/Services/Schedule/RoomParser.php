<?php

namespace App\Services\Schedule;

/**
 * Разбор строки аудиторий: «Д234, В105», «Д 234» и «БМ101».
 *
 * Извлекает пары «корпус + номер» из произвольного текста, игнорируя прочие
 * символы. Строка «ДО» (дистанционное обучение) не содержит номера и поэтому не
 * создаёт аудиторию. Результат дедуплицируется по паре «корпус|номер».
 */
class RoomParser
{
    /**
     * Извлекает аудитории из свободной строки.
     *
     * @param  string  $raw Исходная строка, например «Д234, В105, ДО».
     * @return array<int, array{building: string, room_number: string}> Список уникальных
     *         аудиторий с кодом корпуса (по умолчанию «Д»).
     */
    public static function parse(string $raw): array
    {
        preg_match_all('/(?:(БМ|[ВД])\s*)?(\d{2,4}(?:\.\d+)?[а-яё]?)(?!\d)/ui', $raw, $matches, PREG_SET_ORDER);
        $rooms = [];
        foreach ($matches as $match) {
            $building = mb_strtoupper($match[1] ?: 'Д');
            $rooms[$building.'|'.$match[2]] = ['building' => $building, 'room_number' => $match[2]];
        }

        return array_values($rooms);
    }
}
