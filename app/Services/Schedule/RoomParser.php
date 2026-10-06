<?php

namespace App\Services\Schedule;

/** Разбирает «Д234, В105», «Д 234» и «БМ101». ДО не создаёт аудиторию. */
class RoomParser
{
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
